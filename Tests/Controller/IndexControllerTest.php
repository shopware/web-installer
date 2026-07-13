<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Controller\IndexController;
use Shopware\WebInstaller\Services\LanguageProvider;
use Shopware\WebInstaller\Services\RecoveryManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(IndexController::class)]
class IndexControllerTest extends TestCase
{
    public function testIndexSeedsInstallerModeAndRenders(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);
        $recoveryManager->method('getMode')->willReturn('update');

        $controller = new IndexController($this->createMock(LanguageProvider::class), $recoveryManager);
        $container = new Container();
        $router = $this->createMock(Router::class);
        $router->method('generate')->willReturnArgument(0);
        $container->set('router', $router);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnArgument(0);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->index($request);

        // The flow is resolved on the very first screen so the stepper is correct from the start.
        static::assertSame('update', $request->getSession()->get('installerMode'));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('index.html.twig', $response->getContent());
    }
}
