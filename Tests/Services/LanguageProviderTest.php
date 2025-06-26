<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\LanguageProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[CoversClass(LanguageProvider::class)]
class LanguageProviderTest extends TestCase
{
    public function testGetSupportedLanguages(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('get')
            ->with('shopware.installer.supportedLanguages')
            ->willReturn([
                'en-GB'    => 'en-GB',
                'en-US' => 'en-US',
                'de'    => 'de',
                'cs'    => 'cs',
                'es-ES' => 'es-ES',
                'fr'    => 'fr',
                'it'    => 'it',
                'nl'    => 'nl',
                'pl'    => 'pl',
                'pt-PT' => 'pt-PT',
                'sv-SE' => 'sv-SE',
                'da'    => 'da-DK',
                'no'    => 'no',
            ]);

        $provider = new LanguageProvider($params);

        $this->assertSame(['en-GB', 'en-US', 'de', 'cs', 'es-ES', 'fr', 'it', 'nl', 'pl', 'pt-PT', 'sv-SE', 'da', 'no'], $provider->getSupportedLanguages());
    }
}
