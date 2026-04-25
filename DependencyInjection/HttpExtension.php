<?php

declare(strict_types=1);

namespace Vortos\Http\DependencyInjection;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Vortos\Http\Controller\ErrorController;
use Vortos\Http\Attribute\ApiController;
use Vortos\Http\Kernel;

/**
 * Wires the HTTP kernel and all its dependencies.
 *
 * Replaces: packages/vortos.php, packages/route.php, packages/event.php
 *
 * Registers:
 *   - EventDispatcher
 *   - RequestStack, RequestContext
 *   - UrlMatcher (with synthetic RouteCollection)
 *   - RouterListener (tagged kernel.event_subscriber)
 *   - ResponseListener, ErrorListener (tagged kernel.event_subscriber)
 *   - ContainerControllerResolver, ArgumentResolver
 *   - Kernel as 'vortos'
 *   - ErrorController
 *   - Autoconfiguration: EventSubscriberInterface → kernel.event_subscriber
 *   - Autoconfiguration: #[ApiController] → public + vortos.api.controller tag
 */
final class HttpExtension extends Extension
{
    public function getAlias(): string
    {
        return 'vortos_http';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $charset = $container->hasParameter('charset') ? $container->getParameter('charset') : 'UTF-8';

        // Event dispatcher
        $container->register(EventDispatcher::class, EventDispatcher::class)
            ->setShared(true)
            ->setPublic(true);

        // Request infrastructure
        $container->register(RequestStack::class, RequestStack::class)
            ->setShared(true)
            ->setPublic(true);

        $container->register(RequestContext::class, RequestContext::class)
            ->setShared(true)
            ->setPublic(true);

        // Route collection — synthetic, filled by RouteCompilerPass
        $container->register(RouteCollection::class, RouteCollection::class)
            ->setSynthetic(true)
            ->setPublic(true);

        // URL matcher
        $container->register(UrlMatcher::class, UrlMatcher::class)
            ->setArguments([
                new Reference(RouteCollection::class),
                new Reference(RequestContext::class),
            ])
            ->setShared(true)
            ->setPublic(true);

        // RouterListener — tagged, registered with dispatcher by RegisterEventSubscribersPass
        $container->register(RouterListener::class, RouterListener::class)
            ->setArguments([
                new Reference(UrlMatcher::class),
                new Reference(RequestStack::class),
            ])
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->addTag('kernel.event_subscriber')
            ->setShared(true)
            ->setPublic(false);

        // ErrorController
        $container->register(ErrorController::class, ErrorController::class)
            ->setArguments([
                $container->hasParameter('kernel.debug') ? '%kernel.debug%' : false,
            ])
            ->setShared(true)
            ->setPublic(true);

        // Response and error listeners
        $container->register(ResponseListener::class, ResponseListener::class)
            ->setArguments([$charset])
            ->addTag('kernel.event_subscriber')
            ->setShared(true)
            ->setPublic(false);

        $container->register(ErrorListener::class, ErrorListener::class)
            ->setArguments([ErrorController::class])
            ->addTag('kernel.event_subscriber')
            ->setShared(true)
            ->setPublic(false);

        // Controller resolver and argument resolver
        $container->register(ContainerControllerResolver::class, ContainerControllerResolver::class)
            ->setArguments([new Reference('service_container')])
            ->setShared(true)
            ->setPublic(false);

        $container->register(ArgumentResolver::class, ArgumentResolver::class)
            ->setShared(true)
            ->setPublic(false);

        // HTTP Kernel registered as 'vortos'
        $container->register('vortos', Kernel::class)
            ->setArguments([
                new Reference(EventDispatcher::class),
                new Reference(ContainerControllerResolver::class),
                new Reference(RequestStack::class),
                new Reference(ArgumentResolver::class),
            ])
            ->setShared(true)
            ->setPublic(true);

        // Autoconfiguration
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber');

        $container->registerAttributeForAutoconfiguration(
            ApiController::class,
            static function (ChildDefinition $definition, ApiController $attribute): void {
                $definition->setPublic(true);
                $definition->addTag('vortos.api.controller');
            },
        );
    }
}
