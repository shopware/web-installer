<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

/**
 * Builds the ordered list of stepper entries for the current flow. The install
 * flow shows the full journey (the trailing steps happen in the core installer
 * after the PHAR hands off); the update flow only ever runs start → PHP binary →
 * download → finish, so the install-only setup steps are omitted.
 *
 * @internal
 *
 * @phpstan-type Step array{key: string, active: bool}
 */
class StepProvider
{
    /**
     * @var array<string, list<string>>
     */
    private const STEPS = [
        'install' => ['start', 'configure_php', 'download', 'requirements', 'license', 'database-configuration', 'database-import', 'configuration', 'finish'],
        'update' => ['start', 'configure_php', 'download', 'finish'],
    ];

    /**
     * Maps a controller route name to the stepper entry it activates. Steps with
     * no route here (the core-installer setup steps) are never active in this tool.
     *
     * @var array<string, string>
     */
    private const ROUTE_TO_STEP = [
        'index' => 'start',
        'configure' => 'configure_php',
        'install' => 'download',
        'update' => 'download',
        'finish' => 'finish',
    ];

    /**
     * @return list<Step>
     */
    public function getSteps(string $mode, ?string $route): array
    {
        $keys = self::STEPS[$mode] ?? self::STEPS['install'];
        $activeKey = $route === null ? null : (self::ROUTE_TO_STEP[$route] ?? null);

        return array_map(
            static fn(string $key): array => ['key' => $key, 'active' => $key === $activeKey],
            $keys,
        );
    }
}
