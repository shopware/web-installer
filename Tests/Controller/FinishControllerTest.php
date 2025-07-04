<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\WebInstaller\Controller\FinishController;
use Shopware\WebInstaller\Services\LanguageProvider;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(FinishController::class)]
class FinishControllerTest extends TestCase
{
    public function testRendersTemplate(): void
    {
        $controller = new FinishController($this->createMock(LanguageProvider::class));
        $controller->setContainer($this->buildContainer());

        $response = $controller->default(new Request(), '');

        static::assertSame('finish.html.twig', $response->getContent());
    }

    private function buildContainer(): ContainerInterface
    {
        $container = new Container();

        $router = $this->createMock(Router::class);
        $router->method('generate')->willReturnArgument(0);

        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnArgument(0);

        $container->set('twig', $twig);

        return $container;
    }
}
