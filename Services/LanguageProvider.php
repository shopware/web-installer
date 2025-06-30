<?php

namespace Shopware\WebInstaller\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LanguageProvider
{
    /** @var array<string, string> */
    private array $supportedLanguages = [];

    public function __construct(ParameterBagInterface $params)
    {
        $languages = $params->get('shopware.installer.supportedLanguages');
        if (is_array($languages)) {
            /** @var array<string, string> $languages */
            $this->supportedLanguages = $languages;
        }
    }

    /** @return array<string> */
    public function getSupportedLanguages(): array
    {
        return array_keys($this->supportedLanguages);
    }
}
