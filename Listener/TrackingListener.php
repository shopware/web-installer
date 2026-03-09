<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Listener;

use Shopware\WebInstaller\Services\TrackingEvent;
use Shopware\WebInstaller\Services\TrackingService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @internal
 */
class TrackingListener
{
    public function __construct(private readonly TrackingService $trackingService) {}

    #[AsEventListener(RequestEvent::class, priority: 10)]
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        $session = $request->getSession();

        if ($session->has('trackingId')) {
            return;
        }

        $trackingId = bin2hex(random_bytes(16));
        $session->set('trackingId', $trackingId);

        $referer = $request->headers->get('referer', '');
        $source = 'direct';

        if ($referer !== '') {
            $path = parse_url($referer, \PHP_URL_PATH);

            if (\is_string($path) && str_contains($path, '/admin')) {
                $source = 'admin';
            }
        }

        $this->trackingService->track(TrackingEvent::Visit, $trackingId, [
            'source' => $source,
            'language' => $request->getLocale(),
            'php_version' => \PHP_VERSION,
            'os' => \PHP_OS_FAMILY,
        ]);
    }
}
