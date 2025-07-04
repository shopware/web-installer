<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Controller;

use Shopware\WebInstaller\Services\LanguageProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
class IndexController extends AbstractController
{
    public function __construct(private readonly LanguageProvider $languageProvider) {}

    #[Route('/', name: 'index', defaults: ['step' => 0])]
    public function index(): Response
    {
        return $this->render('index.html.twig', [
            'supportedLanguages' => $this->languageProvider->getSupportedLanguages(),
        ]);
    }
}
