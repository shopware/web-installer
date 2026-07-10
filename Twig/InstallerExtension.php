<?php

declare(strict_types=1);

namespace Shopware\WebInstaller\Twig;

use Shopware\WebInstaller\Services\StepProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the data-driven stepper to the layout via `installer_steps()`, reading
 * the current route and the `installerMode` session flag so no controller has to
 * pass the navigation explicitly.
 *
 * @internal
 *
 * @phpstan-import-type Step from StepProvider
 */
class InstallerExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly StepProvider $stepProvider,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('installer_steps', $this->getSteps(...)),
        ];
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return $this->stepProvider->getSteps('install', null);
        }

        $mode = 'install';
        if ($request->hasSession() && $request->getSession()->has('installerMode')) {
            $mode = (string) $request->getSession()->get('installerMode');
        }

        $route = $request->attributes->get('_route');

        return $this->stepProvider->getSteps($mode, \is_string($route) ? $route : null);
    }
}
