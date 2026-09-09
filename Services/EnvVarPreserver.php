<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

/**
 * Preserves project specific env variables which would be lost when the
 * flex recipes rewrite the .env file (composer symfony:recipes:install --reset).
 *
 * @internal
 */
class EnvVarPreserver
{
    /**
     * @var array<string>
     */
    private const PRESERVED_VARS = [
        'COMPOSE_PROJECT_NAME',
    ];

    /**
     * @return array<string, string> the raw values (as written in the file) of the preserved variables
     */
    public function collect(string $envPath): array
    {
        if (!is_file($envPath)) {
            return [];
        }

        $content = (string) file_get_contents($envPath);
        $values = [];

        foreach (self::PRESERVED_VARS as $name) {
            if (preg_match($this->buildPattern($name), $content, $matches) !== 1) {
                continue;
            }

            $value = rtrim($matches[1]);

            if ($value !== '') {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $values
     */
    public function restore(string $envPath, array $values): void
    {
        if ($values === [] || !is_file($envPath)) {
            return;
        }

        $content = (string) file_get_contents($envPath);

        foreach ($values as $name => $value) {
            $line = $name . '=' . $value;
            $pattern = $this->buildPattern($name);

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace_callback($pattern, static fn(array $matches): string => $line, $content, 1);

                continue;
            }

            if ($content !== '' && !str_ends_with($content, "\n")) {
                $content .= "\n";
            }

            $content .= $line . "\n";
        }

        file_put_contents($envPath, $content);
    }

    private function buildPattern(string $name): string
    {
        return '/^[ \t]*(?:export[ \t]+)?' . preg_quote($name, '/') . '[ \t]*=(.*)$/m';
    }
}
