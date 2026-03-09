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

    private \Socket|false $socket;

    private string $domain;

    public function __construct()
    {
        $this->socket = @socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);

        $domain = Platform::getEnv('SHOPWARE_TRACKING_DOMAIN');
        $this->domain = $domain !== false ? $domain : self::DEFAULT_TRACKING_DOMAIN;
    }

    /**
     * @param array<string, string|int|float|bool> $tags
     */
    public function track(string $eventName, string $userId, array $tags = []): void
    {
        if (Platform::getEnv('DO_NOT_TRACK') !== false) {
            return;
        }

        if ($this->socket === false) {
            return;
        }

        $payload = json_encode([
            'event' => 'web_installer.' . $eventName,
            'tags' => $tags,
            'user_id' => $userId,
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM),
        ], \JSON_THROW_ON_ERROR);

        @socket_sendto($this->socket, $payload, \strlen($payload), 0, $this->domain, 9000);
    }
}
