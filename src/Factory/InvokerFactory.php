<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Factory;

use Invoker\Invoker;
use Invoker\InvokerInterface;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\NumericArrayResolver;
use Invoker\ParameterResolver\ResolverChain;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating configured Invoker instances.
 *
 * Creates Invoker instances with a predefined set of parameter resolvers for
 * dependency injection. The resolvers are configured in the following order:
 *
 *   1. TypeHintContainerResolver: Resolves type-hinted dependencies from
 *      container.
 *   2. AssociativeArrayResolver: Resolves named parameters.
 *   3. NumericArrayResolver: Resolves positional parameters.
 *   4. DefaultValueResolver: Provides default values for optional parameters.
 */
class InvokerFactory
{
    /**
     * Creates a new Invoker instance.
     *
     * Configures the Invoker with a chain of parameter resolvers and the
     * provided container for dependency resolution.
     *
     * @param ContainerInterface $container The DI container for resolving
     * dependencies.
     * @return InvokerInterface The configured Invoker instance.
     */
    public function create(ContainerInterface $container): InvokerInterface
    {
        $resolvers = [
            new TypeHintContainerResolver($container),
            new AssociativeArrayResolver(),
            new NumericArrayResolver(),
            new DefaultValueResolver(),
        ];

        return new Invoker(new ResolverChain($resolvers), $container);
    }
}
