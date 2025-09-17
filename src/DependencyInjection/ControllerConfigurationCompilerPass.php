<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\DependencyInjection;

use Derafu\Http\Contract\ControllerInterface;
use Derafu\Routing\Attribute\Route;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass for loading controller configuration.
 */
class ControllerConfigurationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            // Skip synthetic and abstract services.
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }

            // Omit classes that not exist or are not controllers.
            $class = $definition->getClass() ?? $id;
            if (
                !class_exists($class) ||
                !is_subclass_of($class, ControllerInterface::class)
            ) {
                continue;
            }

            // Process controller.
            $this->processController($definition->getClass(), $container);
        }
    }

    protected function processController(
        string $class,
        ContainerBuilder $container
    ): void {
        // Get the reflection of the class.
        $reflection = new ReflectionClass($class);

        // Load actions from controller.
        $actions = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        // Process actions.
        foreach ($actions as $action) {
            $this->processAction($action, $container);
        }
    }

    protected function processAction(
        ReflectionMethod $action,
        ContainerBuilder $container
    ): void {
        $this->processRouteAttribute($action, $container);
    }

    protected function processRouteAttribute(
        ReflectionMethod $action,
        ContainerBuilder $container
    ): void {
        // Get routes from container.
        $routes = $container->getParameter('routes');

        // Load route attributes from action.
        $routeAttributes = $action->getAttributes(Route::class);
        if (empty($routeAttributes)) {
            return;
        }

        // Process route attributes.
        foreach ($routeAttributes as $routeAttribute) {
            $route = $routeAttribute->newInstance();
            assert($route instanceof Route);

            $routes[$route->name] = [
                'path' => $route->path,
                'handler' => $route->handler ?? ($action->class . '::' . $action->name),
                'methods' => $route->methods,
                'defaults' => $route->defaults,
            ];
        }

        // Add routes to container.
        $container->setParameter('routes', $routes);
    }
}
