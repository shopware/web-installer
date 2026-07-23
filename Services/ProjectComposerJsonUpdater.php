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
    /**
     * Shopware is not affected because only authenticated administration users can manipulate input rendered by dompdf.
     *
     * @var array<string, string>
     */
    private const DOMPDF_ADVISORIES = [
        'CVE-2026-59943' => 'https://github.com/advisories/GHSA-j8qw-6jw8-r297',
        'CVE-2026-59942' => 'https://github.com/advisories/GHSA-f5gf-2cj8-52g2',
        'CVE-2026-59941' => 'https://github.com/advisories/GHSA-8hg6-c449-896m',
        'CVE-2026-56722' => 'https://github.com/advisories/GHSA-cx96-42px-69fm',
        'CVE-2026-55555' => 'https://github.com/advisories/GHSA-7x2p-4jvh-6384',
        'CVE-2026-55554' => 'https://github.com/advisories/GHSA-wvh6-f5jh-8gw4',
    ];

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
        $composerJson = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);

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

        $composerJson = $this->ensureConflictsRepository($composerJson);
        $composerJson = $this->configureRepositories($composerJson);
        $composerJson = $this->ignoreDompdfAdvisories($composerJson);

        file_put_contents($file, json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function ignoreDompdfAdvisories(array $config): array
    {
        /** @var list<string>|array<string, array{apply?: string, reason?: string}|string|null> $ignoredAdvisories */
        $ignoredAdvisories = $config['config']['audit']['ignore'] ?? [];

        if (array_is_list($ignoredAdvisories)) {
            $ignoredAdvisoryIds = $ignoredAdvisories;
            $ignoredAdvisories = [];

            foreach ($ignoredAdvisoryIds as $ignoredAdvisory) {
                if (\is_string($ignoredAdvisory)) {
                    $ignoredAdvisories[$ignoredAdvisory] = null;
                }
            }
        }

        foreach (self::DOMPDF_ADVISORIES as $advisory => $link) {
            if (array_key_exists($advisory, $ignoredAdvisories)) {
                continue;
            }

            $ignoredAdvisories[$advisory] = [
                'apply' => 'block',
                'reason' => 'Shopware is not affected because only authenticated administration users can manipulate input rendered by dompdf. See ' . $link,
            ];
        }

        $config['config']['audit']['ignore'] = $ignoredAdvisories;

        return $config;
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
     * Ensures the shopware/conflicts composer repository is registered so the
     * shopware/conflicts package can be resolved during install and update.
     *
     * Existing `repositories` layouts are preserved: an indexed list stays a
     * list and a keyed map stays a map. If the conflicts repository is already
     * present (matched by URL) it is left untouched.
     *
     * @see https://github.com/shopware/conflicts/blob/main/USAGES.md
     *
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function ensureConflictsRepository(array $config): array
    {
        $conflictsRepository = [
            'type' => 'composer',
            'url' => 'https://shopware.github.io/conflicts/',
        ];

        if ($this->hasRepository($config['repositories'] ?? [], $conflictsRepository['url'])) {
            return $config;
        }

        $config['repositories'] = $this->addRepository(
            $config['repositories'] ?? [],
            'shopware-conflicts',
            $conflictsRepository
        );

        return $config;
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
            } catch (\JsonException) {
                return $config;
            }

            $config['repositories'] = $this->addRepository(
                $config['repositories'] ?? [],
                'recovery',
                $repo
            );
        }

        return $config;
    }

    /**
     * Returns true when a repository with the given URL is already registered,
     * no matter whether `repositories` is an indexed list or a keyed map.
     *
     * @param array<mixed> $repositories
     */
    private function hasRepository(array $repositories, string $url): bool
    {
        foreach ($repositories as $repository) {
            if (!\is_array($repository)) {
                continue;
            }

            if (($repository['url'] ?? null) === $url) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a repository entry while preserving the existing `repositories`
     * structure: an indexed list stays a list (entry appended), a keyed map
     * stays a map (entry added under `$name`).
     *
     * @param array<int|string, mixed>              $repositories
     * @param array{type: string, url: string, ...} $repository
     *
     * @return array<int|string, mixed>
     */
    private function addRepository(array $repositories, string $name, array $repository): array
    {
        if (array_is_list($repositories)) {
            $repositories[] = $repository;
        } else {
            $repositories[$name] = $repository;
        }

        return $repositories;
    }

    private function getConflictMinVersion(string $shopwareVersion): ?string
    {
        /**
         * Since Shopware 6.6.10.1, we pin the conflicts version in Shopware to an exact version.
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
