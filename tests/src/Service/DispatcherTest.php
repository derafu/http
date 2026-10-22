<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Service;

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Exception\DispatcherException;
use Derafu\Http\Request;
use Derafu\Http\Response;
use Derafu\Http\Service\Dispatcher;
use Derafu\Http\Service\RequestStack;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouteMatchInterface;
use Derafu\Routing\ValueObject\Route;
use Derafu\Routing\ValueObject\RouteMatch;
use Derafu\TestsHttp\Fixture\Controller\ActionController;
use Derafu\TestsHttp\Fixture\EchoRenderer;
use Derafu\TestsHttp\Fixture\UnusedRenderer;
use Invoker\Invoker;
use Invoker\InvokerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * `DispatcherException` is translatable (`TranslatableLogicException`), so
 * these lock in that its message is built via the `[message, ...params]`
 * form — not `sprintf()`, which would bake a fixed, non-translatable string
 * into the exception before it ever reaches the translator.
 */
#[CoversClass(Dispatcher::class)]
#[UsesClass(RequestStack::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
class DispatcherTest extends TestCase
{
    private function dispatch(mixed $handler): mixed
    {
        $dispatcher = new Dispatcher(
            $this->createStub(InvokerInterface::class),
            $this->createStub(RendererInterface::class),
            new RequestStack()
        );

        $match = $this->createStub(RouteMatchInterface::class);
        $match->method('getHandler')->willReturn($handler);
        $match->method('getParameters')->willReturn([]);

        $request = $this->createStub(RequestInterface::class);

        return $dispatcher->dispatch($match, $request);
    }

    public function testThrowsWithTheInvalidHandlerStringInTheMessageForAnUnrecognizedStringHandler(): void
    {
        $this->expectException(DispatcherException::class);
        $this->expectExceptionMessageMatches('/not-a-valid-handler/');

        $this->dispatch('not-a-valid-handler');
    }

    public function testThrowsWithTheHandlerTypeInTheMessageForAnUnsupportedHandlerType(): void
    {
        $this->expectException(DispatcherException::class);
        $this->expectExceptionMessageMatches('/array/');

        $this->dispatch(['not', 'a', 'valid', 'handler']);
    }

    private function match(\Closure $handler): RouteMatch
    {
        return new RouteMatch(new Route('test', '/test', $handler));
    }

    public function testTheHandlerIsCalledWithItsRequestAtTheTopOfTheStack(): void
    {
        $stack = new RequestStack();
        $dispatcher = new Dispatcher(new Invoker(), new UnusedRenderer(), $stack);
        $request = new Request('GET', 'http://localhost/test');

        $seen = $dispatcher->dispatch(
            $this->match(fn (): mixed => $stack->getCurrentRequest()),
            $request
        );

        $this->assertSame($request, $seen);
        $this->assertNull($stack->getCurrentRequest());
    }

    public function testTheRequestIsTakenOutOfTheStackWhenTheHandlerFails(): void
    {
        $stack = new RequestStack();
        $dispatcher = new Dispatcher(new Invoker(), new UnusedRenderer(), $stack);

        try {
            $dispatcher->dispatch(
                $this->match(function (): never {
                    throw new RuntimeException('Boom.');
                }),
                new Request('GET', 'http://localhost/test')
            );
            $this->fail('The exception must go on.');
        } catch (RuntimeException) {
            $this->assertNull($stack->getCurrentRequest());
        }
    }

    public function testADispatchInsideAnotherOneGivesTheStackBack(): void
    {
        $stack = new RequestStack();
        $dispatcher = new Dispatcher(new Invoker(), new UnusedRenderer(), $stack);
        $outer = new Request('GET', 'http://localhost/outer');
        $inner = new Request('GET', 'http://localhost/inner');

        $seen = $dispatcher->dispatch(
            $this->match(function () use ($stack, $dispatcher, $inner): array {
                $inside = $dispatcher->dispatch(
                    $this->match(fn (): mixed => $stack->getCurrentRequest()),
                    $inner
                );

                return [$inside, $stack->getCurrentRequest()];
            }),
            $outer
        );

        $this->assertSame([$inner, $outer], $seen);
        $this->assertNull($stack->getCurrentRequest());
    }

    private function real(?RequestStack $stack = null): Dispatcher
    {
        // The invoker takes the controllers from the container.
        $container = new ContainerBuilder();
        $container->register(ActionController::class, ActionController::class)->setPublic(true);
        $container->compile();

        return new Dispatcher(new Invoker(null, $container), new EchoRenderer(), $stack ?? new RequestStack());
    }

    public function testATemplateIsRenderedWithTheParametersOfTheHandler(): void
    {
        $match = new RouteMatch(new Route('page', '/page/{id}', __FILE__), ['id' => '7']);

        $rendered = $this->real()->dispatch($match, new Request('GET', 'http://localhost/page/7'), ['error' => 'x']);

        $this->assertIsString($rendered);
        $this->assertSame(
            ['template' => __FILE__, 'data' => [RequestInterface::class, RouteMatchInterface::class, 'id', 'request', 'route', 'params', 'context']],
            json_decode($rendered, true)
        );
    }

    public function testAControllerActionIsCalledWithTheRouteParameters(): void
    {
        $handler = ActionController::class . '::show';
        $match = new RouteMatch(new Route('post', '/posts/{id}', $handler), ['id' => '42']);

        $result = $this->real()->dispatch($match, new Request('GET', 'http://localhost/posts/42'));

        $this->assertSame('post 42 at /posts/42', $result);
    }

    public function testARedirectIsAResponseWithTheLocation(): void
    {
        $match = new RouteMatch(new Route('old', '/old', 'redirect:/new'));

        $response = $this->real()->dispatch($match, new Request('GET', 'http://localhost/old'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/new', $response->getHeaderLine('Location'));
    }

    public function testAClosureGetsTheRequestTheRouteAndTheContext(): void
    {
        $handler = fn (RequestInterface $request, RouteMatchInterface $route, array $context): array => [
            $request->getUri()->getPath(),
            $route->getParameters(),
            $context,
        ];
        $match = new RouteMatch(new Route('page', '/page/{id}', $handler), ['id' => '7']);

        $result = $this->real()->dispatch($match, new Request('GET', 'http://localhost/page'), ['a' => 1]);

        $this->assertSame(['/page', ['id' => '7'], ['a' => 1]], $result);
    }
}
