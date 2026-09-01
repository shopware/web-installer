<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\ProjectComposerJsonUpdater;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(ProjectComposerJsonUpdater::class)]
#[BackupGlobals(true)]
class ProjectComposerJsonUpdaterTest extends TestCase
{
    private string $json;

    protected function setUp(): void
    {
        $this->json = __DIR__ . '/composer.json';

        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        unlink($this->json);
    }

    public function testUpdate(): void
    {
        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0'
        );

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.4.18.0',
                ],
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateIgnoresDompdfAdvisoriesForDependencyBlocking(): void
    {
        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
            ],
            'config' => [
                'audit' => [
                    'ignore' => ['GHSA-existing-advisory'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0'
        );

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);
        $ignoredAdvisories = $composerJson['config']['audit']['ignore'];

        static::assertIsArray($ignoredAdvisories);
        static::assertArrayHasKey('GHSA-existing-advisory', $ignoredAdvisories);
        static::assertNull($ignoredAdvisories['GHSA-existing-advisory']);
        unset($ignoredAdvisories['GHSA-existing-advisory']);

        $expectedAdvisories = [
            'CVE-2026-59943' => 'https://github.com/advisories/GHSA-j8qw-6jw8-r297',
            'CVE-2026-59942' => 'https://github.com/advisories/GHSA-f5gf-2cj8-52g2',
            'CVE-2026-59941' => 'https://github.com/advisories/GHSA-8hg6-c449-896m',
            'CVE-2026-56722' => 'https://github.com/advisories/GHSA-cx96-42px-69fm',
            'CVE-2026-55555' => 'https://github.com/advisories/GHSA-7x2p-4jvh-6384',
            'CVE-2026-55554' => 'https://github.com/advisories/GHSA-wvh6-f5jh-8gw4',
        ];

        static::assertSame(array_keys($expectedAdvisories), array_keys($ignoredAdvisories));

        foreach ($expectedAdvisories as $cve => $link) {
            static::assertSame([
                'apply' => 'block',
                'reason' => 'Shopware is not affected because only authenticated administration users can manipulate input rendered by dompdf. See ' . $link,
            ], $ignoredAdvisories[$cve]);
        }
    }

    public function testUpdateWithRC(): void
    {
        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0-rc1'
        );

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.4.18.0-rc1',
                ],
                'minimum-stability' => 'RC',
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateWithFixVersion(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => 'dev-trunk as 6.5.0.0',
                ],
                'minimum-stability' => 'RC',
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateWithFixVersionAndBranch(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';
        $_SERVER['SW_RECOVERY_NEXT_BRANCH'] = 'main';

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => 'main as 6.5.0.0',
                ],
                'minimum-stability' => 'RC',
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateWithFixVersionAndBranchSame(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';
        $_SERVER['SW_RECOVERY_NEXT_BRANCH'] = '6.5.0.0';

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.5.0.0',
                ],
                'minimum-stability' => 'RC',
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateWithSymfonyRuntimeRequirement(): void
    {
        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
                'symfony/runtime' => '^5.0|^6.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.6.0.0'
        );

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.6.0.0',
                    'symfony/runtime' => '>=5',
                ],
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdate67RemovesConflict(): void
    {
        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
                'symfony/runtime' => '^5.0|^6.0',
                'shopware/conflicts' => '>=2.0.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getVersionResponse()])))->update(
            $this->json,
            '6.7.0.0'
        );

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.7.0.0',
                    'symfony/runtime' => '>=5',
                ],
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateConflictPackageGetsAdded(): void
    {
        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
                'symfony/runtime' => '^5.0|^6.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getVersionResponse()])))->update(
            $this->json,
            '6.6.0.0'
        );

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.6.0.0',
                    'symfony/runtime' => '>=5',
                    'shopware/conflicts' => '>=2.0.0',
                ],
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateConflictPackageGetsAddedMinified(): void
    {
        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
                'symfony/runtime' => '^5.0|^6.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getMinifiedVersionResponse()])))->update(
            $this->json,
            '6.2.0.0'
        );

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.2.0.0',
                    'symfony/runtime' => '>=5',
                    'shopware/conflicts' => '>=1.0.0',
                ],
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateConflictPackageGetsAddedUsesOlderVersionWhenConstraintDoesNotMatch(): void
    {
        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
                'symfony/runtime' => '^5.0|^6.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getVersionResponse()])))->update(
            $this->json,
            '6.4.0.0'
        );

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.4.0.0',
                    'symfony/runtime' => '>=5',
                    'shopware/conflicts' => '>=1.0.0',
                ],
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                ],
            ],
            $composerJson
        );
    }

    public function testWithRecoveryRepository(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';
        $_SERVER['SW_RECOVERY_NEXT_BRANCH'] = '6.5.0.0';

        $customRepo = [
            'type' => 'path',
            'url' => '/my/custom/repo',
            'options' => [
                'symlink' => true,
            ],
        ];
        $_SERVER['SW_RECOVERY_REPOSITORY'] = json_encode($customRepo, JSON_THROW_ON_ERROR);

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = $this->readComposerJsonWithoutDompdfAdvisories();

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.5.0.0',
                ],
                'minimum-stability' => 'RC',
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://shopware.github.io/conflicts/',
                    ],
                    $customRepo,
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateKeepsExistingConflictsRepository(): void
    {
        $existingRepo = [
            'type' => 'composer',
            'url' => 'https://shopware.github.io/conflicts/',
        ];

        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
            ],
            'repositories' => [
                $existingRepo,
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0'
        );

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        // Existing conflicts repository is preserved and not duplicated
        static::assertSame(
            [
                [
                    'type' => 'composer',
                    'url' => 'https://shopware.github.io/conflicts/',
                ],
            ],
            $composerJson['repositories']
        );
    }

    public function testConflictsRepositoryPreservesIndexedListForm(): void
    {
        $pathRepo = [
            'type' => 'path',
            'url' => 'custom/plugins/*',
            'options' => ['symlink' => true],
        ];

        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
            ],
            'repositories' => [
                $pathRepo,
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0'
        );

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        // Existing list form is preserved; conflicts repo appended as a list item
        static::assertSame(
            [
                $pathRepo,
                [
                    'type' => 'composer',
                    'url' => 'https://shopware.github.io/conflicts/',
                ],
            ],
            $composerJson['repositories']
        );
    }

    public function testConflictsRepositoryPreservesKeyedMapForm(): void
    {
        $pathRepo = [
            'type' => 'path',
            'url' => 'custom/plugins/*',
            'options' => ['symlink' => true],
        ];

        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
            ],
            'repositories' => [
                'plugins' => $pathRepo,
            ],
        ], \JSON_THROW_ON_ERROR));

        (new ProjectComposerJsonUpdater(new MockHttpClient([$this->getEmptyVersionsResponse()])))->update(
            $this->json,
            '6.4.18.0'
        );

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        // Existing keyed map form is preserved; conflicts repo added under a named key
        static::assertSame(
            [
                'plugins' => $pathRepo,
                'shopware-conflicts' => [
                    'type' => 'composer',
                    'url' => 'https://shopware.github.io/conflicts/',
                ],
            ],
            $composerJson['repositories']
        );
    }

    /**
     * @return array<mixed>
     */
    private function readComposerJsonWithoutDompdfAdvisories(): array
    {
        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        unset($composerJson['config']['audit']['ignore']);

        if ($composerJson['config']['audit'] === []) {
            unset($composerJson['config']['audit']);
        }

        if ($composerJson['config'] === []) {
            unset($composerJson['config']);
        }

        return $composerJson;
    }

    private function getEmptyVersionsResponse(): MockResponse
    {
        $json = <<<JSON
{
    "packages": {
        "shopware/conflicts": [
        ]
    }
}
JSON;

        return new MockResponse($json);
    }

    private function getVersionResponse(): MockResponse
    {
        $json = <<<JSON
{
    "packages": {
        "shopware/conflicts": [
          {
            "version": "2.0.0",
            "require": {
                "shopware/core": ">=6.6.0"
            }
          },
          {
            "version": "1.9.0"
          },
          {
            "version": "1.0.0",
            "require": {
                "shopware/core": "*"
            }
          }
        ]
    }
}
JSON;

        return new MockResponse($json);
    }

    private function getMinifiedVersionResponse(): MockResponse
    {
        $json = <<<JSON
{
    "packages": {
        "shopware/conflicts": [
          {
            "version": "2.0.0",
            "require": {
                "shopware/core": ">=6.6.0"
            }
          },
          {
            "version": "1.9.0",
            "require": "__unset"
          },
          {
            "version": "1.0.0",
            "require": {
                "shopware/core": "*"
            }
          }
        ]
    }
}
JSON;

        return new MockResponse($json);
    }
}
