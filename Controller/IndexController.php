<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Controller;

use Shopware\WebInstaller\Services\LanguageProvider;
use Shopware\WebInstaller\Services\RecoveryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
class IndexController extends AbstractController
{
    public function __construct(
        private readonly LanguageProvider $languageProvider,
        private readonly RecoveryManager $recoveryManager,
    ) {}

    #[Route('/', name: 'index', defaults: ['step' => 0])]
    public function index(Request $request): Response
    {
        $request->getSession()->set('installerMode', $this->recoveryManager->getMode());

        return $this->render('index.html.twig', [
            'supportedLanguages' => $this->languageProvider->getSupportedLanguages(),
        ]);
    }
}
