<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Listener;

use Shopware\WebInstaller\Services\LanguageProvider;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @internal
 */
class InstallerLocaleListener
{
    /**
     * @var list<string>
     */
    private array $installerLanguages;

    public function __construct(LanguageProvider $languageProvider)
    {
        $this->installerLanguages = array_values($languageProvider->getSupportedLanguages());
    }

    #[AsEventListener(RequestEvent::class, priority: 15)]
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $locale = $this->detectLanguage($request);
        $request->attributes->set('_locale', $locale);
        $request->setLocale($locale);
    }

    private function detectLanguage(Request $request): string
    {
        $session = $request->getSession();

        // language is changed
        if ($request->query->has('language') && \in_array((string) $request->query->get('language'), $this->installerLanguages, true)) {
            $session->set('language', (string) $request->query->get('language'));

            return (string) $request->query->get('language');
        }

        // language was already set
        if ($session->has('language') && \in_array((string) $session->get('language'), $this->installerLanguages, true)) {
            return (string) $session->get('language');
        }

        $mappedLanguages = array_map(
            fn(string $l): string => str_replace('-', '_', $l),
            $this->installerLanguages
        );

        // fallback: get preferred language from browser header, or default to first supported
        $preferredLanguage = $request->getPreferredLanguage($mappedLanguages)
            ?? $mappedLanguages[0];

        $preferredLanguage = str_replace('_', '-', $preferredLanguage);

        $session->set('language', $preferredLanguage);

        return $preferredLanguage;
    }
}
