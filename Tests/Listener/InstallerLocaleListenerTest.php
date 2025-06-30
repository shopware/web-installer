<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Listener\InstallerLocaleListener;
use Shopware\WebInstaller\Services\LanguageProvider;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(InstallerLocaleListener::class)]
class InstallerLocaleListenerTest extends TestCase
{
    #[DataProvider('installerLocaleProvider')]
    public function testSetInstallerLocale(Request $request, string $expectedLocale): void
    {
        $languageProvider = $this->createMock(LanguageProvider::class);
        $languageProvider->method('getSupportedLanguages')->willReturn([
            'en-US','en','de', 'cs', 'es-ES', 'fr', 'it', 'nl', 'pl', 'pt-PT', 'sv-SE', 'da', 'no',]);

        $listener = new InstallerLocaleListener($languageProvider);

        $listener->__invoke(
            new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        static::assertSame($expectedLocale, $request->attributes->get('_locale'));
        static::assertSame($expectedLocale, $request->getLocale());
    }

    public static function installerLocaleProvider(): \Generator
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        yield 'falls back to us if no locale can be found' => [
            $request,
            'en-US',
        ];

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'ru-RU']);

        yield 'falls back to us if browser header is not supported' => [
            $request,
            'en-US',
        ];

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->headers = new HeaderBag(['Accept-Language' => 'de-DE']);

        yield 'uses browser header if it is supported with long iso code' => [
            $request,
            'de',
        ];

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->headers = new HeaderBag(['Accept-Language' => 'de']);

        yield 'uses browser header if it is supported with short iso code' => [
            $request,
            'de',
        ];

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'ru');
        $request->setSession($session);
        $request->headers = new HeaderBag(['Accept-Language' => 'de']);

        yield 'falls back to browser header if session value is not supported' => [
            $request,
            'de',
        ];

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'de');
        $request->setSession($session);

        yield 'read language from session' => [
            $request,
            'de',
        ];
    }

    public function testItSavesLanguageChangeToSession(): void
    {
        $request = new Request(['language' => 'de']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'en');
        $request->setSession($session);

        $languageProvider = $this->createMock(LanguageProvider::class);
        $languageProvider->method('getSupportedLanguages')->willReturn([
            'en-US','en','de', 'cs', 'es-ES', 'fr', 'it', 'nl', 'pl', 'pt-PT', 'sv-SE', 'da', 'no',
        ]);

        $listener = new InstallerLocaleListener($languageProvider);

        $listener->__invoke(
            new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        static::assertSame('de', $request->attributes->get('_locale'));
        static::assertSame('de', $request->getLocale());
        static::assertSame('de', $session->get('language'));
    }
}
