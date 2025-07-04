<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Composer\MetadataMinifier\MetadataMinifier;
use Composer\Semver\VersionParser;
use Composer\Util\Platform;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
class ProjectComposerJsonUpdater
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    public function update(string $file, string $latestVersion): void
    {
        $shopwarePackages = [
            'shopware/core',
            'shopware/administration',
            'shopware/storefront',
            'shopware/elasticsearch',
        ];

        /** @var array{minimum-stability?: string, require: array<string, string>} $composerJson */
        $composerJson = json_decode((string) file_get_contents($file), true, \JSON_THROW_ON_ERROR);

        if (str_contains(strtolower($latestVersion), 'rc')) {
            $composerJson['minimum-stability'] = 'RC';
        } else {
            unset($composerJson['minimum-stability']);
        }

        // We require symfony runtime now directly in src/Core, so we remove the max version constraint
        if (isset($composerJson['require']['symfony/runtime'])) {
            $composerJson['require']['symfony/runtime'] = '>=5';
        }

        // Lock the composer version to that major version
        $version = $this->getVersion($latestVersion);

        if ($conflictPackageVersion = $this->getConflictMinVersion($latestVersion)) {
            $composerJson['require']['shopware/conflicts'] = '>=' . $conflictPackageVersion;
        } else {
            unset($composerJson['require']['shopware/conflicts']);
        }

        foreach ($shopwarePackages as $shopwarePackage) {
            if (!isset($composerJson['require'][$shopwarePackage])) {
                continue;
            }

            $composerJson['require'][$shopwarePackage] = $version;
        }

        $composerJson = $this->configureRepositories($composerJson);

        file_put_contents($file, json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    private function getVersion(string $latestVersion): string
    {
        $nextVersion = Platform::getEnv('SW_RECOVERY_NEXT_VERSION');
        if (\is_string($nextVersion)) {
            $nextBranch = Platform::getEnv('SW_RECOVERY_NEXT_BRANCH');
            if ($nextBranch === false) {
                $nextBranch = 'dev-trunk';
            }

            if ($nextBranch === $nextVersion) {
                return $nextBranch;
            }

            return $nextBranch . ' as ' . $nextVersion;
        }

        return $latestVersion;
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function configureRepositories(array $config): array
    {
        $repoString = Platform::getEnv('SW_RECOVERY_REPOSITORY');
        if (\is_string($repoString)) {
            try {
                $repo = json_decode($repoString, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return $config;
            }

            $config['repositories']['recovery'] = $repo;
        }

        return $config;
    }

    private function getConflictMinVersion(string $shopwareVersion): ?string
    {
        /**
         * Since Shopware 6.6.10.1, we pin the conflicts version in Shopware to an excact version.
         * So this does not make sense anymore
         * @see https://github.com/shopware/conflicts/blob/main/USAGES.md
         */
        if (version_compare($shopwareVersion, '6.6.10.1', '>=')) {
            return null;
        }

        /** @var array{packages: array{"shopware/conflicts": array{version: string, require: array{"shopware/core": string}}[]}} $data */
        $data = $this->httpClient->request('GET', 'https://repo.packagist.org/p2/shopware/conflicts.json')->toArray();

        $data['packages']['shopware/conflicts'] = MetadataMinifier::expand($data['packages']['shopware/conflicts']);

        $versions = $data['packages']['shopware/conflicts'];

        $parser = new VersionParser();
        $updateToVersion = $parser->parseConstraints($parser->normalize($shopwareVersion));

        $requirePart = [];

        foreach ($versions as $version) {
            if (array_key_exists('require', $version)) {
                if (is_array($version['require'])) {
                    $requirePart = $version['require'];
                } elseif ($version['require'] === '__unset') {
                    $requirePart = [];
                }
            }

            $shopwareVersionConstraint = $requirePart['shopware/core'] ?? null;

            if ($shopwareVersionConstraint === null) {
                continue;
            }

            if ($parser->parseConstraints($shopwareVersionConstraint)->matches($updateToVersion)) {
                return $version['version'];
            }
        }

        return null;
    }
}
