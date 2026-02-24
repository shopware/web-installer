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
        $this->installerLanguages = \array_keys($languageProvider->getSupportedLanguages());
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
        $language = $request->query->getString('language');
        if ($language !== '' && \in_array($language, $this->installerLanguages, true)) {
            $session->set('language', $language);

            return $language;
        }

        // language was already set
        $sessionLanguage = (string) $session->get('language');
        if ($sessionLanguage !== '' && \in_array($sessionLanguage, $this->installerLanguages, true)) {
            return $sessionLanguage;
        }

        $mappedLanguages = array_map(
            static fn(string $l): string => str_replace('-', '_', $l),
            $this->installerLanguages
        );

        // fallback: get preferred language from browser header, or default to first supported
        $preferredLanguage = $request->getPreferredLanguage($mappedLanguages) ?? $mappedLanguages[0];

        $preferredLanguage = str_replace('_', '-', $preferredLanguage);

        $session->set('language', $preferredLanguage);

        return $preferredLanguage;
    }
}
