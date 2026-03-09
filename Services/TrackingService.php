<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

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
        $this->domain = $_ENV['SHOPWARE_TRACKING_DOMAIN'] ?? $_SERVER['SHOPWARE_TRACKING_DOMAIN'] ?? self::DEFAULT_TRACKING_DOMAIN;
    }

    /**
     * @param array<string, string|int|float|bool> $tags
     */
    public function track(string $eventName, string $userId, array $tags = []): void
    {
        if (isset($_ENV['DO_NOT_TRACK']) || isset($_SERVER['DO_NOT_TRACK'])) {
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
