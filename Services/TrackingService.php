<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Composer\Util\Platform;

/**
 * @internal
 */
class TrackingService
{
    private const DEFAULT_TRACKING_DOMAIN = 'udp.usage.shopware.io';

    /** @var \Socket|false|null */
    private $socket = null;

    private string $domain;

    public function __construct()
    {
        if (\function_exists('socket_create')) {
            $this->socket = @socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);
        }

        $domain = Platform::getEnv('SHOPWARE_TRACKING_DOMAIN');
        $this->domain = $domain !== false ? $domain : self::DEFAULT_TRACKING_DOMAIN;
    }

    /**
     * @param array<string, string|int|float|bool> $tags
     */
    public function track(TrackingEvent $eventName, string $userId, array $tags = []): void
    {
        if (Platform::getEnv('DO_NOT_TRACK') !== false) {
            return;
        }

        if (Platform::getEnv('SW_RECOVERY_NEXT_VERSION') !== false || Platform::getEnv('SW_RECOVERY_NEXT_BRANCH') !== false) {
            return;
        }

        if (!$this->socket instanceof \Socket) {
            return;
        }

        $payload = json_encode([
            'event' => 'web_installer.' . $eventName->value,
            'tags' => $tags,
            'user_id' => $userId,
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM),
        ], \JSON_THROW_ON_ERROR);

        @socket_sendto($this->socket, $payload, \strlen($payload), 0, $this->domain, 9000);
    }
}
