<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @internal
 */
#[CoversClass(Filesystem::class)]
class FilesystemTest extends TestCase
{
    private Filesystem $filesystem;

    private string $file;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->file = $this->filesystem->tempnam(sys_get_temp_dir(), $this->name());
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->file);
    }

    public function testReadContents(): void
    {
        $this->filesystem->dumpFile($this->file, 'shopware');

        static::assertSame('shopware', $this->filesystem->readContents($this->file));
    }

    public function testReadContentsMissing(): void
    {
        $this->filesystem->remove($this->file);

        $this->expectException(IOException::class);
        $this->expectExceptionMessage(\sprintf('Failed to read file "%s"', $this->file));

        $this->filesystem->readContents($this->file);
    }

    public function testReadContentsDirectory(): void
    {
        $this->expectException(IOException::class);
        $this->expectExceptionMessage('File is a directory');

        $this->filesystem->readContents(sys_get_temp_dir());
    }
}
