<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Adds a read method to the Symfony Filesystem (only available upstream since symfony/filesystem 7.1 as readFile()).
 * Named differently on purpose, so a future upgrade does not turn this into an override of the upstream method.
 *
 * @internal
 */
class Filesystem extends SymfonyFilesystem
{
    public function readContents(string $filename): string
    {
        if (is_dir($filename)) {
            throw new IOException(\sprintf('Failed to read file "%s": File is a directory.', $filename), 0, null, $filename);
        }

        $content = @file_get_contents($filename);

        if ($content === false) {
            throw new IOException(\sprintf('Failed to read file "%s": ', $filename) . (error_get_last()['message'] ?? ''), 0, null, $filename);
        }

        return $content;
    }
}
