<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Kernel;
use Shopware\WebInstaller\Services\LanguageProvider;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[CoversClass(LanguageProvider::class)]
class LanguageProviderTest extends TestCase
{
    public function testGetSupportedLanguages(): void
    {
        // Test for officially supported and communicated languages.
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('get')
            ->with('shopware.installer.supportedLanguages')
            ->willReturn([
                'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                'en'    => ['id' => 'en-GB', 'label' => 'English (UK)'],
                'de'    => ['id' => 'de-DE', 'label' => 'Deutsch'],
            ]);

        $provider = new LanguageProvider($params);
        $languages = $provider->getSupportedLanguages();

        // Verify the sorting
        static::assertIsArray($languages);
        static::assertSame(['de', 'en', 'en-US'], array_keys($languages));

        static::assertSame(['id' => 'de-DE', 'label' => 'Deutsch'], $languages['de']);
        static::assertSame(['id' => 'en-GB', 'label' => 'English (UK)'], $languages['en']);
        static::assertSame(['id' => 'en-US', 'label' => 'English (US)'], $languages['en-US']);
    }

    public function testGetSupportedLanguagesFromActualConfiguration(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        static::assertInstanceOf(Container::class, $container);
        $parameterBag = $container->getParameterBag();

        $provider = new LanguageProvider($parameterBag);
        $languages = $provider->getSupportedLanguages();

        static::assertIsArray($languages);
        static::assertNotEmpty($languages);

        // Verify all languages have the correct structure
        foreach ($languages as $key => $language) {
            static::assertIsString($key);
            static::assertIsArray($language);
            static::assertArrayHasKey('id', $language, "Language '$key' should have 'id' key");
            static::assertArrayHasKey('label', $language, "Language '$key' should have 'label' key");
            static::assertIsString($language['id'], "Language '$key' id should be a string");
            static::assertIsString($language['label'], "Language '$key' label should be a string");
        }

        $expectedKeys = ['cs', 'da-DK', 'de', 'en', 'en-US', 'es-ES', 'fr', 'it', 'nl', 'no', 'pl', 'pt-PT', 'sv-SE'];
        static::assertSame($expectedKeys, array_keys($languages));
    }
}
