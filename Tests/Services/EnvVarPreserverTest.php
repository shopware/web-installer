<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\EnvVarPreserver;
use Shopware\WebInstaller\Services\Filesystem;

/**
 * @internal
 */
#[CoversClass(EnvVarPreserver::class)]
class EnvVarPreserverTest extends TestCase
{
    private const ENV_PATH = '/var/www/shop/.env';

    private Filesystem&MockObject $filesystem;

    private EnvVarPreserver $preserver;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->preserver = new EnvVarPreserver($this->filesystem);
    }

    public function testCollectMissingFile(): void
    {
        $this->filesystem->method('exists')->with(self::ENV_PATH)->willReturn(false);
        $this->filesystem->expects($this->never())->method('readContents');

        static::assertSame([], $this->preserver->collect(self::ENV_PATH));
    }

    public function testCollectWithoutPreservedVars(): void
    {
        $this->givenEnvFile("APP_ENV=prod\n");

        static::assertSame([], $this->preserver->collect(self::ENV_PATH));
    }

    public function testCollectIgnoresEmptyValue(): void
    {
        $this->givenEnvFile("COMPOSE_PROJECT_NAME=\n");

        static::assertSame([], $this->preserver->collect(self::ENV_PATH));
    }

    public function testCollect(): void
    {
        $this->givenEnvFile("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop \nAPP_URL=http://localhost\n");

        static::assertSame(['COMPOSE_PROJECT_NAME' => 'my-shop'], $this->preserver->collect(self::ENV_PATH));
    }

    public function testCollectExported(): void
    {
        $this->givenEnvFile("export COMPOSE_PROJECT_NAME='my-shop'\n");

        static::assertSame(['COMPOSE_PROJECT_NAME' => "'my-shop'"], $this->preserver->collect(self::ENV_PATH));
    }

    public function testRestoreAppendsVar(): void
    {
        $this->givenEnvFile("APP_ENV=prod\n");
        $this->expectEnvFileWritten("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n");

        $this->preserver->restore(self::ENV_PATH, ['COMPOSE_PROJECT_NAME' => 'my-shop']);
    }

    public function testRestoreAppendsVarWithoutTrailingNewline(): void
    {
        $this->givenEnvFile('APP_ENV=prod');
        $this->expectEnvFileWritten("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n");

        $this->preserver->restore(self::ENV_PATH, ['COMPOSE_PROJECT_NAME' => 'my-shop']);
    }

    public function testRestoreOverwritesExistingVar(): void
    {
        $this->givenEnvFile("COMPOSE_PROJECT_NAME=shopware\nAPP_ENV=prod\n");
        $this->expectEnvFileWritten("COMPOSE_PROJECT_NAME=my-shop\nAPP_ENV=prod\n");

        $this->preserver->restore(self::ENV_PATH, ['COMPOSE_PROJECT_NAME' => 'my-shop']);
    }

    public function testRestoreKeepsSpecialCharacters(): void
    {
        $this->givenEnvFile("COMPOSE_PROJECT_NAME=shopware\n");
        $this->expectEnvFileWritten("COMPOSE_PROJECT_NAME=my\$0\\shop\n");

        $this->preserver->restore(self::ENV_PATH, ['COMPOSE_PROJECT_NAME' => 'my$0\\shop']);
    }

    public function testRestoreWithoutValues(): void
    {
        $this->filesystem->expects($this->never())->method('readContents');
        $this->filesystem->expects($this->never())->method('dumpFile');

        $this->preserver->restore(self::ENV_PATH, []);
    }

    public function testRestoreMissingFile(): void
    {
        $this->filesystem->method('exists')->with(self::ENV_PATH)->willReturn(false);
        $this->filesystem->expects($this->never())->method('readContents');
        $this->filesystem->expects($this->never())->method('dumpFile');

        $this->preserver->restore(self::ENV_PATH, ['COMPOSE_PROJECT_NAME' => 'my-shop']);
    }

    public function testCollectAndRestoreRoundTrip(): void
    {
        $this->filesystem->method('exists')->with(self::ENV_PATH)->willReturn(true);
        // the recipes reset rewrites the file between collect and restore
        $this->filesystem
            ->method('readContents')
            ->with(self::ENV_PATH)
            ->willReturnOnConsecutiveCalls("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n", "APP_ENV=prod\n");
        $this->expectEnvFileWritten("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n");

        $this->preserver->restore(self::ENV_PATH, $this->preserver->collect(self::ENV_PATH));
    }

    private function givenEnvFile(string $content): void
    {
        $this->filesystem->method('exists')->with(self::ENV_PATH)->willReturn(true);
        $this->filesystem->method('readContents')->with(self::ENV_PATH)->willReturn($content);
    }

    private function expectEnvFileWritten(string $content): void
    {
        $this->filesystem->expects($this->once())->method('dumpFile')->with(self::ENV_PATH, $content);
    }
}
