<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Controller;

use Shopware\WebInstaller\Services\LanguageProvider;
use Shopware\WebInstaller\Services\ProjectComposerJsonUpdater;
use Shopware\WebInstaller\Services\RecoveryManager;
use Shopware\WebInstaller\Services\ReleaseInfoProvider;
use Shopware\WebInstaller\Services\StreamedCommandResponseGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
class InstallController extends AbstractController
{
    public function __construct(
        private readonly RecoveryManager $recoveryManager,
        private readonly StreamedCommandResponseGenerator $streamedCommandResponseGenerator,
        private readonly ReleaseInfoProvider $releaseInfoProvider,
        private readonly ProjectComposerJsonUpdater $projectComposerJsonUpdater,
        private readonly LanguageProvider $languageProvider,
    ) {}

    #[Route('/install', name: 'install', defaults: ['step' => 2])]
    public function index(): Response
    {
        $versions = $this->releaseInfoProvider->fetchVersions();

        return $this->render('install.html.twig', [
            'versions' => $versions,
            'supportedLanguages' => $this->languageProvider->getSupportedLanguages(),
        ]);
    }

    #[Route('/install/_run', name: 'install_run', methods: ['POST'])]
    public function run(Request $request): StreamedResponse
    {
        $shopwareVersion = $request->query->get('shopwareVersion', '');
        $folder = $this->recoveryManager->getProjectDir();

        $fs = new Filesystem();
        $fs->copy(\dirname(__DIR__) . '/Resources/install-template/composer.json', $folder . '/composer.json');
        $fs->dumpFile($folder . '/.env', \PHP_EOL);
        $fs->dumpFile($folder . '/.gitignore', '/.idea
/vendor/
');
        $fs->mkdir($folder . '/custom/plugins');
        $fs->mkdir($folder . '/custom/static-plugins');

        $this->projectComposerJsonUpdater->update(
            $folder . '/composer.json',
            $shopwareVersion
        );

        $finish = function (Process $process) use ($request): void {
            $data = [
                'success' => $process->isSuccessful(),
            ];

            if ($process->isSuccessful()) {
                $locale = $request->getLocale();
                $langQuery = $locale
                    ? '?language=' . rawurlencode($locale)
                    : '';

                $data['newLocation'] = $request->getBasePath() . '/public/' . $langQuery;
            }

            echo json_encode($data);
        };

        return $this->streamedCommandResponseGenerator->run([
            $this->recoveryManager->getPHPBinary($request),
            '-dmemory_limit=1G',
            $this->recoveryManager->getBinary(),
            'install',
            '-d',
            $folder,
            '--no-interaction',
            '--no-ansi',
            '-v',
        ], $finish);
    }

    /**
     * @codeCoverageIgnore
     */
    #[Route('/install/_cleanup', name: 'install_cleanup', methods: ['POST'])]
    public function cleanup(): StreamedResponse
    {
        $folder = $this->recoveryManager->getProjectDir();

        $fs = new Filesystem();
        $htaccessFile = $folder . '/public/.htaccess';

        // Shopware 6.4 does not contain a htaccess by default
        if (!$fs->exists($htaccessFile)) {
            $fs->copy(\dirname(__DIR__) . '/Resources/install-template/htaccess', $htaccessFile);
        }

        $self = $_SERVER['SCRIPT_FILENAME'];
        \assert(\is_string($self));

        // Below this line call only php native functions as we deleted our own files already
        unlink($self);

        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        exit;
    }
}
