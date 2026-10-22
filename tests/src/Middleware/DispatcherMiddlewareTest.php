<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Middleware;

use Derafu\Http\Middleware\DispatcherMiddleware;
use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Http\Request;
use Derafu\Http\Response;
use Derafu\Http\Service\Dispatcher;
use Derafu\Http\Service\RequestStack;
use Derafu\Routing\ValueObject\Route;
use Derafu\Routing\ValueObject\RouteMatch;
use Derafu\TestsHttp\Fixture\CapturingHandler;
use Derafu\TestsHttp\Fixture\UnusedRenderer;
use Invoker\Invoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The handler of the route that matched is dispatched, and what it returns goes
 * on in an attribute of the request for the next middlewares.
 */
#[CoversClass(DispatcherMiddleware::class)]
#[UsesClass(Dispatcher::class)]
#[UsesClass(RequestStack::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class DispatcherMiddlewareTest extends TestCase
{
    public function testWhatTheHandlerReturnsIsAnAttributeOfTheNextRequest(): void
    {
        $dispatcher = new Dispatcher(new Invoker(), new UnusedRenderer(), new RequestStack());
        $route = new RouteMatch(new Route('home', '/', fn (): array => ['status' => 'ok']));
        $request = (new Request('GET', 'http://localhost/'))
            ->withAttribute(RouterMiddleware::ROUTE_ATTRIBUTE, $route);
        $next = new CapturingHandler();

        $response = (new DispatcherMiddleware($dispatcher))->process($request, $next);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($next->request);
        $this->assertSame(['status' => 'ok'], $next->request->getAttribute(DispatcherMiddleware::RESPONSE_ATTRIBUTE));
    }

    public function testAHandlerThatReturnsNullIsAnAttributeThatIsThere(): void
    {
        $dispatcher = new Dispatcher(new Invoker(), new UnusedRenderer(), new RequestStack());
        $route = new RouteMatch(new Route('home', '/', fn (): mixed => null));
        $request = (new Request('GET', 'http://localhost/'))
            ->withAttribute(RouterMiddleware::ROUTE_ATTRIBUTE, $route);
        $next = new CapturingHandler();

        (new DispatcherMiddleware($dispatcher))->process($request, $next);

        $this->assertNotNull($next->request);
        $this->assertArrayHasKey(DispatcherMiddleware::RESPONSE_ATTRIBUTE, $next->request->getAttributes());
    }
}
