<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http;

use Derafu\Http\Contract\KernelInterface;
use Derafu\Http\DependencyInjection\ControllerConfigurationCompilerPass;
use Derafu\Http\Factory\InvokerFactory;
use Derafu\Kernel\Config\Loader\PhpRoutesLoader;
use Derafu\Kernel\Config\Loader\YamlRoutesLoader;
use Derafu\Kernel\MicroKernel;
use Invoker\InvokerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * HTTP Kernel implementation.
 *
 * This kernel serves as the bootstrap for HTTP applications, providing:
 *
 *   - Configuration loading and management.
 *   - Service container setup.
 *   - Core service registration.
 *   - Request handling delegation.
 *
 * Supported configuration formats:
 *
 *   - PHP files (.php).
 *   - YAML files (.yaml).
 *   - Route configuration in both formats.
 *
 * @see Derafu\Kernel\MicroKernel
 */
class Kernel extends MicroKernel implements KernelInterface
{
    /**
     * Configuration files that will be loaded automatically.
     *
     * Maps file names to their loader type:
     *
     *   - 'php': PHP configuration files.
     *   - 'yaml': YAML configuration files.
     *   - 'routes': Route configuration files.
     *
     * @var array<string,string>
     */
    protected const CONFIG_FILES = [
        'services.php' => 'php',
        'routes.php' => 'routes',
        'services.yaml' => 'yaml',
        'routes.yaml' => 'routes',
    ];

    /**
     * Available configuration loaders.
     *
     * Lists the loader classes that can process different configuration formats.
     * Each loader is responsible for parsing and loading a specific format.
     *
     * @var array<class-string>
     */
    protected const CONFIG_LOADERS = [
        PhpFileLoader::class,
        PhpRoutesLoader::class,
        YamlFileLoader::class,
        YamlRoutesLoader::class,
    ];

    /**
     * Handles an HTTP request.
     *
     * Delegates the request handling to the RequestHandler instance from the
     * container. This separation of concerns allows the kernel to focus on
     * bootstrapping while the RequestHandler handles the actual request
     * processing.
     *
     * @param ServerRequestInterface $request The incoming HTTP request.
     * @return ResponseInterface The generated HTTP response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->getContainer()->get(RequestHandlerInterface::class);

        return $handler->handle($request);
    }

    /**
     * Configures core services in the container.
     *
     * Sets up essential services:
     *
     *   - Parameter bag for configuration access.
     *   - Invoker factory for dependency injection of Invoker service.
     *   - Invoker service for method calling and dependency injection.
     *
     * This configuration happens before conatiner compilation.
     *
     * @param ContainerConfigurator $configurator The container configurator.
     * @param ContainerBuilder $container The container builder.
     */
    protected function configure(
        ContainerConfigurator $configurator,
        ContainerBuilder $container
    ): void {
        $services = $configurator->services();

        // Register parameter bag service.
        $services
            ->set(ParameterBagInterface::class)
            ->factory([new Reference('service_container'), 'getParameterBag'])
        ;

        // Register invoker factory.
        $services->set(InvokerFactory::class);

        // Register and configure invoker service.
        $services
            ->set(InvokerInterface::class)
            ->factory([new Reference(InvokerFactory::class), 'create'])
            ->args([
                '$container' => new Reference('service_container'),
            ])
        ;

        // Agregar compiler passes.
        $container->addCompilerPass(
            new ControllerConfigurationCompilerPass()
        );
    }
}
