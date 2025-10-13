<?php

namespace Shopware\WebInstaller\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal
 * @phpstan-type SupportedLanguages array<string, array{id: string, label: string}>
 */
class LanguageProvider
{
    /** @var SupportedLanguages */
    private array $supportedLanguages = [];

    public function __construct(ParameterBagInterface $params)
    {
        $languages = $params->get('shopware.installer.supportedLanguages');
        if (is_array($languages)) {
            ksort($languages);
            /** @var SupportedLanguages $languages */
            $this->supportedLanguages = $languages;
        }
    }

    /**
     * @return SupportedLanguages
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }
}
