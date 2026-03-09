<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

/**
 * @internal
 */
enum TrackingEvent: string
{
    case Visit = 'visit';
    case InstallStarted = 'install.started';
    case InstallCompleted = 'install.completed';
    case InstallFailed = 'install.failed';
    case UpdateStarted = 'update.started';
    case UpdateCompleted = 'update.completed';
    case UpdateFailed = 'update.failed';
}
