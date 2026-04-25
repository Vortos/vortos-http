<?php

declare(strict_types=1);

namespace Vortos\Http\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Vortos\Foundation\Contract\PackageInterface;
use Vortos\Http\DependencyInjection\Compiler\HttpListenerCompilerPass;
use Vortos\Http\DependencyInjection\Compiler\RegisterEventSubscribersPass;
use Vortos\Http\DependencyInjection\Compiler\RouteCompilerPass;

/**
 * HTTP package — kernel, routing, event dispatcher.
 *
 * Replaces: packages/vortos.php, packages/route.php, packages/event.php
 *
 * Add to Container.php first — before all other packages.
 * The HTTP kernel ('vortos') must be registered before other packages
 * add event subscribers that depend on it.
 */
final class HttpPackage implements PackageInterface
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new HttpExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            new HttpListenerCompilerPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            100,
        );

        $container->addCompilerPass(
            new RegisterEventSubscribersPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            90,
        );

        $container->addCompilerPass(
            new RouteCompilerPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            80,
        );
    }
}
