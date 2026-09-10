<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\EnvVarPreserver;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(EnvVarPreserver::class)]
class EnvVarPreserverTest extends TestCase
{
    private string $tmpDir;

    private string $envPath;

    private EnvVarPreserver $preserver;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/' . uniqid('env-preserver', true);
        $this->envPath = $this->tmpDir . '/.env';
        $this->preserver = new EnvVarPreserver(new Filesystem());

        (new Filesystem())->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tmpDir);
    }

    public function testCollectMissingFile(): void
    {
        static::assertSame([], $this->preserver->collect($this->envPath));
    }

    public function testCollectWithoutPreservedVars(): void
    {
        file_put_contents($this->envPath, "APP_ENV=prod\n");

        static::assertSame([], $this->preserver->collect($this->envPath));
    }

    public function testCollectIgnoresEmptyValue(): void
    {
        file_put_contents($this->envPath, "COMPOSE_PROJECT_NAME=\n");

        static::assertSame([], $this->preserver->collect($this->envPath));
    }

    public function testCollect(): void
    {
        file_put_contents($this->envPath, "APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop \nAPP_URL=http://localhost\n");

        static::assertSame(['COMPOSE_PROJECT_NAME' => 'my-shop'], $this->preserver->collect($this->envPath));
    }

    public function testCollectExported(): void
    {
        file_put_contents($this->envPath, "export COMPOSE_PROJECT_NAME='my-shop'\n");

        static::assertSame(['COMPOSE_PROJECT_NAME' => "'my-shop'"], $this->preserver->collect($this->envPath));
    }

    public function testRestoreAppendsVar(): void
    {
        file_put_contents($this->envPath, "APP_ENV=prod\n");

        $this->preserver->restore($this->envPath, ['COMPOSE_PROJECT_NAME' => 'my-shop']);

        static::assertSame("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n", (string) file_get_contents($this->envPath));
    }

    public function testRestoreAppendsVarWithoutTrailingNewline(): void
    {
        file_put_contents($this->envPath, 'APP_ENV=prod');

        $this->preserver->restore($this->envPath, ['COMPOSE_PROJECT_NAME' => 'my-shop']);

        static::assertSame("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n", (string) file_get_contents($this->envPath));
    }

    public function testRestoreOverwritesExistingVar(): void
    {
        file_put_contents($this->envPath, "COMPOSE_PROJECT_NAME=shopware\nAPP_ENV=prod\n");

        $this->preserver->restore($this->envPath, ['COMPOSE_PROJECT_NAME' => 'my-shop']);

        static::assertSame("COMPOSE_PROJECT_NAME=my-shop\nAPP_ENV=prod\n", (string) file_get_contents($this->envPath));
    }

    public function testRestoreKeepsSpecialCharacters(): void
    {
        file_put_contents($this->envPath, "COMPOSE_PROJECT_NAME=shopware\n");

        $this->preserver->restore($this->envPath, ['COMPOSE_PROJECT_NAME' => 'my$0\\shop']);

        static::assertSame("COMPOSE_PROJECT_NAME=my\$0\\shop\n", (string) file_get_contents($this->envPath));
    }

    public function testRestoreWithoutValues(): void
    {
        file_put_contents($this->envPath, "APP_ENV=prod\n");

        $this->preserver->restore($this->envPath, []);

        static::assertSame("APP_ENV=prod\n", (string) file_get_contents($this->envPath));
    }

    public function testRestoreMissingFile(): void
    {
        $this->preserver->restore($this->envPath, ['COMPOSE_PROJECT_NAME' => 'my-shop']);

        static::assertFileDoesNotExist($this->envPath);
    }

    public function testCollectAndRestoreRoundTrip(): void
    {
        file_put_contents($this->envPath, "APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n");

        $values = $this->preserver->collect($this->envPath);

        // the recipes reset rewrites the file
        file_put_contents($this->envPath, "APP_ENV=prod\n");

        $this->preserver->restore($this->envPath, $values);

        static::assertSame("APP_ENV=prod\nCOMPOSE_PROJECT_NAME=my-shop\n", (string) file_get_contents($this->envPath));
    }
}
