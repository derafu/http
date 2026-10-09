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

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Factory\RequestFactory;
use Derafu\Http\Middleware\RequestFactoryMiddleware;
use Derafu\Http\Request;
use Derafu\Http\Service\RequestHolder;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * The request of the pipeline is given to the holder while it is handled, and
 * taken back when it ends, also with an exception.
 */
#[CoversClass(RequestFactoryMiddleware::class)]
#[UsesClass(RequestHolder::class)]
#[UsesClass(RequestFactory::class)]
#[UsesClass(Request::class)]
final class RequestFactoryMiddlewareTest extends TestCase
{
    private RequestHolder $holder;

    private RequestFactoryMiddleware $middleware;

    protected function setUp(): void
    {
        $this->holder = new RequestHolder();
        $this->middleware = new RequestFactoryMiddleware(
            new RequestFactory(),
            new ParameterBag(['kernel.context' => []]),
            $this->holder
        );
    }

    #[Test]
    public function theHolderHasTheRequestWhileItIsHandled(): void
    {
        $handler = new class ($this->holder) implements RequestHandlerInterface {
            public ?RequestInterface $request = null;

            public ?RequestInterface $held = null;

            public function __construct(private readonly RequestHolder $holder)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request instanceof RequestInterface ? $request : null;
                $this->held = $this->holder->get();

                return new Response();
            }
        };

        $this->middleware->process(new ServerRequest('GET', 'https://example.com/'), $handler);

        $this->assertInstanceOf(RequestInterface::class, $handler->request);
        $this->assertSame($handler->request, $handler->held);
        $this->assertNull($this->holder->get());
    }

    #[Test]
    public function theHolderIsEmptiedWhenTheRequestFails(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Boom.');
            }
        };

        try {
            $this->middleware->process(new ServerRequest('GET', 'https://example.com/'), $handler);
            $this->fail('The exception must go on.');
        } catch (RuntimeException) {
            $this->assertNull($this->holder->get());
        }
    }
}
