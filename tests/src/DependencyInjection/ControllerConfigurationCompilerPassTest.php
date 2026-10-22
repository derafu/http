<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\DependencyInjection;

use Derafu\Http\DependencyInjection\ControllerConfigurationCompilerPass;
use Derafu\Http\Support\KernelParameters;
use Derafu\TestsHttp\Fixture\Controller\HomeController;
use Derafu\TestsHttp\Fixture\Controller\NotAController;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Throwable;

/**
 * The routes of the controllers (the services that implement
 * `ControllerInterface`) are read from their `#[Route]` attributes and
 * become the parameter `routes` of the container.
 */
#[CoversClass(ControllerConfigurationCompilerPass::class)]
#[UsesClass(KernelParameters::class)]
final class ControllerConfigurationCompilerPassTest extends TestCase
{
    /**
     * @param array<string, Definition> $definitions
     * @param array<mixed> $routes
     */
    private function container(array $definitions, array $routes = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('routes', $routes);
        foreach ($definitions as $id => $definition) {
            $container->setDefinition($id, $definition);
        }

        return $container;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function routesOf(ContainerBuilder $container): array
    {
        (new ControllerConfigurationCompilerPass())->process($container);
        $routes = KernelParameters::array($container->getParameterBag(), 'routes');

        /** @var array<string, array<string, mixed>> $routes */
        return $routes;
    }

    public function testTheRoutesOfAControllerAreInTheParameter(): void
    {
        $routes = $this->routesOf($this->container([
            HomeController::class => new Definition(HomeController::class),
        ]));

        $this->assertSame(['home', 'post'], array_keys($routes));
        $this->assertSame([
            'path' => '/',
            'handler' => HomeController::class . '::index',
            'methods' => [],
            'defaults' => [],
            'roles' => [],
        ], $routes['home']);
        $this->assertSame([
            'path' => '/posts/{id}',
            'handler' => 'App\\Other::show',
            'methods' => ['GET', 'HEAD'],
            'defaults' => ['id' => '1'],
            'roles' => ['ROLE_USER'],
        ], $routes['post']);
    }

    public function testAControllerWhoseDefinitionHasNoClassIsTheOneOfItsId(): void
    {
        // `App\Controller\Home: ~` in a services file: the id is the class.
        $routes = $this->routesOf($this->container([
            HomeController::class => new Definition(),
        ]));

        $this->assertSame(['home', 'post'], array_keys($routes));
    }

    public function testTheRoutesThatWereThereAreKept(): void
    {
        $routes = $this->routesOf($this->container(
            [HomeController::class => new Definition(HomeController::class)],
            ['other' => ['path' => '/other']]
        ));

        $this->assertSame(['other', 'home', 'post'], array_keys($routes));
    }

    public function testWhatIsNotAControllerIsNotRead(): void
    {
        $routes = $this->routesOf($this->container([
            NotAController::class => new Definition(NotAController::class),
            'not.a.class' => new Definition(),
        ]));

        $this->assertSame([], $routes);
    }

    public function testTheSyntheticAndTheAbstractServicesAreNotRead(): void
    {
        $synthetic = (new Definition(HomeController::class))->setSynthetic(true);
        $abstract = (new Definition(HomeController::class))->setAbstract(true);

        $routes = $this->routesOf($this->container(['synthetic' => $synthetic, 'abstract' => $abstract]));

        $this->assertSame([], $routes);
    }

    public function testARoutesParameterThatIsNotAnArrayIsATranslatableError(): void
    {
        $container = $this->container([HomeController::class => new Definition(HomeController::class)]);
        $container->setParameter('routes', 'not an array');

        $exception = null;
        try {
            (new ControllerConfigurationCompilerPass())->process($container);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('The key "routes" must be of type array, string given.', $exception->getMessage());
    }
}
