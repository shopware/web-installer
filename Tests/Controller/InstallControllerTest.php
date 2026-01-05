<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\WebInstaller\Controller\InstallController;
use Shopware\WebInstaller\Services\LanguageProvider;
use Shopware\WebInstaller\Services\ProjectComposerJsonUpdater;
use Shopware\WebInstaller\Services\RecoveryManager;
use Shopware\WebInstaller\Services\ReleaseInfoProvider;
use Shopware\WebInstaller\Services\StreamedCommandResponseGenerator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(InstallController::class)]
#[CoversClass(ProjectComposerJsonUpdater::class)]
class InstallControllerTest extends TestCase
{
    public function testStartPage(): void
    {
        $recovery = $this->createMock(RecoveryManager::class);
        $recovery->method('getShopwareLocation')->willReturn('asd');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator->method('runJSON')->willReturn(new StreamedResponse());

        $controller = new InstallController($recovery, $responseGenerator, $this->createMock(ReleaseInfoProvider::class), $this->createMock(ProjectComposerJsonUpdater::class), $this->createMock(LanguageProvider::class));
        $controller->setContainer($this->buildContainer());

        $response = $controller->index();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('install.html.twig', $response->getContent());
    }

    public function testInstall(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('test', true);

        $recovery = $this->createMock(RecoveryManager::class);
        $recovery->method('getShopwareLocation')->willReturn('location');
        $recovery->method('getPHPBinary')->willReturn('php');
        $recovery->method('getProjectDir')->willReturn($tmpDir);

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects($this->once())
            ->method('run')
            ->with([
                'php',
                '-dmemory_limit=1G',
                '',
                'install',
                '-d',
                $tmpDir,
                '--no-interaction',
                '--no-ansi',
                '--no-security-blocking',
                '-v',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new InstallController($recovery, $responseGenerator, $this->createMock(ReleaseInfoProvider::class), $this->createMock(ProjectComposerJsonUpdater::class), $this->createMock(LanguageProvider::class));
        $controller->setContainer($this->buildContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->query->set('shopwareVersion', '6.4.10.0');

        $controller->run($request);

        (new Filesystem())->remove($tmpDir);
    }

    public function testRunFinishCallableOnSuccess(): void
    {
        $finishCallable = null;
        [$tmpDir, $installController, $request] = $this->createInstallControllerAndRequestAndTemporaryDirectory($finishCallable);

        $request->setLocale('de');
        $installController->run($request);

        static::assertNotNull($finishCallable);
        static::assertIsCallable($finishCallable);

        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(true);

        ob_start();
        $finishCallable($process);
        $output = ob_get_clean();
        static::assertIsString($output);

        $data = json_decode($output, true);
        static::assertIsArray($data);
        static::assertArrayHasKey('success', $data);
        static::assertTrue($data['success']);
        static::assertArrayHasKey('newLocation', $data);
        static::assertStringContainsString('/public/', $data['newLocation']);
        static::assertStringContainsString('ext_steps=1', $data['newLocation']);
        static::assertStringContainsString('language=de', $data['newLocation']);

        (new Filesystem())->remove($tmpDir);
    }

    public function testRunFinishCallableOnFailure(): void
    {
        $finishCallable = null;
        [$tmpDir, $installController, $request] = $this->createInstallControllerAndRequestAndTemporaryDirectory($finishCallable);

        $installController->run($request);

        static::assertNotNull($finishCallable, 'Finish callable should be captured');
        static::assertIsCallable($finishCallable);

        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(false);

        ob_start();
        $finishCallable($process);
        $output = ob_get_clean();
        static::assertIsString($output);

        $data = json_decode($output, true);
        static::assertIsArray($data);
        static::assertArrayHasKey('success', $data);
        static::assertFalse($data['success']);
        static::assertArrayNotHasKey('newLocation', $data);

        (new Filesystem())->remove($tmpDir);
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

    /**
     * @return array{0: string, 1: InstallController, 2: Request}
     */
    private function createInstallControllerAndRequestAndTemporaryDirectory(
        ?callable &$finishCallable
    ): array {
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('test', true);

        $recoveryManagerMock = $this->createMock(RecoveryManager::class);
        $recoveryManagerMock->method('getProjectDir')->willReturn($tmpDir);
        $recoveryManagerMock->method('getPHPBinary')->willReturn('php');
        $recoveryManagerMock->method('getBinary')->willReturn('binary');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($command, $finish) use (&$finishCallable) {
                $finishCallable = $finish;

                return new StreamedResponse();
            });

        $installController = new InstallController(
            $recoveryManagerMock,
            $responseGenerator,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(ProjectComposerJsonUpdater::class),
            $this->createMock(LanguageProvider::class)
        );
        $installController->setContainer($this->buildContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->query->set('shopwareVersion', '6.7.3.0');
        $request->server->set('REQUEST_URI', '/install/_run');

        return [$tmpDir, $installController, $request];
    }
}
