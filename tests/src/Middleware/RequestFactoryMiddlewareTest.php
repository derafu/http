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
use Derafu\Http\Response as DerafuResponse;
use Derafu\TestsHttp\Fixture\CountingHandler;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * The request of the pipeline is the one of Derafu, with the context of the
 * kernel when there is one.
 */
#[CoversClass(RequestFactoryMiddleware::class)]
#[UsesClass(RequestFactory::class)]
#[UsesClass(Request::class)]
#[UsesClass(DerafuResponse::class)]
final class RequestFactoryMiddlewareTest extends TestCase
{
    /**
     * @param array<string, mixed> $context
     */
    private function receivedWith(array $context): ServerRequestInterface
    {
        $middleware = new RequestFactoryMiddleware(
            new RequestFactory(),
            new ParameterBag(['kernel.context' => $context])
        );
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        $middleware->process(new ServerRequest('GET', 'https://example.com/'), $handler);
        $this->assertInstanceOf(ServerRequestInterface::class, $handler->request);

        return $handler->request;
    }

    #[Test]
    public function theNextMiddlewareGetsTheRequestOfDerafu(): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->receivedWith([]));
    }

    #[Test]
    public function theContextOfTheKernelIsAnAttributeOfTheRequest(): void
    {
        $request = $this->receivedWith(['name' => 'app']);

        $this->assertSame(['name' => 'app'], $request->getAttribute(RequestFactoryMiddleware::CONTEXT_ATTRIBUTE));
    }

    #[Test]
    public function withoutContextThereIsNoAttribute(): void
    {
        $request = $this->receivedWith([]);

        $this->assertArrayNotHasKey(RequestFactoryMiddleware::CONTEXT_ATTRIBUTE, $request->getAttributes());
    }

    #[Test]
    public function theResponseOfTheNextMiddlewareIsReturned(): void
    {
        $middleware = new RequestFactoryMiddleware(new RequestFactory(), new ParameterBag(['kernel.context' => []]));
        $handler = new CountingHandler();

        $response = $middleware->process(new ServerRequest('GET', 'https://example.com/'), $handler);

        $this->assertSame(1, $handler->calls);
        $this->assertSame(200, $response->getStatusCode());
    }
}
