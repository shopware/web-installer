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
                'de' => 'Deutsch',
                'en' => 'English',
            ]);

        $provider = new LanguageProvider($params);

        $this->assertSame(['de', 'en'], $provider->getSupportedLanguages());
    }
}
