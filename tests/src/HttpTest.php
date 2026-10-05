<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp;

use Derafu\Http\Contract\ProblemDetailInterface;
use Derafu\Http\Contract\ProblemHandlerInterface;
use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Exception\TooManyRequestsException;
use Derafu\Http\Factory\ProblemFactory;
use Derafu\Http\Factory\RequestFactory;
use Derafu\Http\Factory\SafeThrowableFactory;
use Derafu\Http\Middleware\DispatcherMiddleware;
use Derafu\Http\Middleware\RequestFactoryMiddleware;
use Derafu\Http\Middleware\ResponseNormalizerMiddleware;
use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Http\Service\Dispatcher;
use Derafu\Http\Service\ProblemHandler;
use Derafu\Http\Service\RequestHandler;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Parser\DynamicParser;
use Derafu\Routing\Parser\StaticParser;
use Derafu\Routing\Router;
use Invoker\Invoker;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Sends requests through the real middleware chain (request factory, router,
 * dispatcher, response normalizer) and the real error handling, the way the
 * runtime does. Only the renderer is a stub: no template is involved because
 * every route here returns data.
 *
 * Integration tests: they exercise many classes on purpose, so they do not
 * declare which one they cover.
 */
#[CoversNothing]
class HttpTest extends TestCase
{
    /**
     * Number of times the problem handler ran in the last request. The handler
     * is the real one, wrapped only to count the calls.
     */
    private int $problemHandlerCalls = 0;

    /**
     * Builds the stack and handles a request.
     *
     * A new `RequestHandler` is built for each request on purpose: it keeps a
     * mutable position in the chain, so it cannot be reused.
     */
    private function send(string $path, string $method = 'GET'): PsrResponseInterface
    {
        $params = new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.debug' => false,
            'kernel.context' => [],
            'kernel.project_dir' => sys_get_temp_dir(),
        ]);

        $router = new Router([new StaticParser(), new DynamicParser()], [
            'hello' => [
                'path' => '/api/hello',
                'handler' => fn () => ['status' => 'ok'],
            ],
            'user' => [
                'path' => '/api/users/{id}',
                'handler' => fn (string $id) => ['id' => $id],
            ],
            'throttled' => [
                'path' => '/api/throttled',
                'handler' => function () {
                    throw new TooManyRequestsException(
                        ['Too many requests, wait {seconds} seconds.', 'seconds' => 30],
                        0,
                        null,
                        ['Retry-After' => '30']
                    );
                },
            ],
            'broken' => [
                'path' => '/api/broken',
                'handler' => function () {
                    throw new RuntimeException('Something broke.');
                },
            ],
        ]);

        $dispatcher = new Dispatcher(
            new Invoker(),
            $this->createStub(RendererInterface::class)
        );

        $this->problemHandlerCalls = 0;
        $problemHandler = new class (new ProblemHandler($router, $dispatcher), $this->problemHandlerCalls) implements ProblemHandlerInterface {
            public function __construct(
                private readonly ProblemHandlerInterface $handler,
                private int &$calls
            ) {
            }

            public function handle(ProblemDetailInterface $error): ResponseInterface
            {
                $this->calls++;

                return $this->handler->handle($error);
            }
        };

        $handler = new RequestHandler(
            new ProblemFactory($params, new SafeThrowableFactory($params)),
            $problemHandler,
            new RequestFactoryMiddleware(new RequestFactory(), $params),
            new RouterMiddleware($router),
            new DispatcherMiddleware($dispatcher),
            new ResponseNormalizerMiddleware(),
        );

        $request = new ServerRequest(
            $method,
            'http://localhost' . $path,
            ['Accept' => 'application/json'],
            null,
            '1.1',
            [
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => $method,
                'HTTP_HOST' => 'localhost',
            ]
        );

        return $handler->handle($request);
    }

    private function json(PsrResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSimulateHttpRequestWithResponse200(): void
    {
        $response = $this->send('/api/hello');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'application/json; charset=UTF-8',
            $response->getHeaderLine('Content-Type')
        );
        $this->assertSame(['status' => 'ok'], $this->json($response));
    }

    public function testPassesTheRouteParametersToTheHandler(): void
    {
        $response = $this->send('/api/users/42');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => '42'], $this->json($response));
    }

    public function testAnUnknownPathIsAProblemDetailWith404(): void
    {
        $response = $this->send('/api/does-not-exist');

        $this->assertSame(404, $response->getStatusCode());

        $problem = $this->json($response);
        $this->assertSame(404, $problem['status']);
        $this->assertSame('No route found for "/api/does-not-exist".', $problem['detail']);
    }

    public function testAnHttpExceptionKeepsItsStatusTitleAndHeaders(): void
    {
        $response = $this->send('/api/throttled');

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('30', $response->getHeaderLine('Retry-After'));

        $problem = $this->json($response);
        $this->assertSame(429, $problem['status']);
        $this->assertSame('Too Many Requests', $problem['title']);
        $this->assertSame('Too many requests, wait 30 seconds.', $problem['detail']);
    }

    public function testAnyOtherExceptionIsAProblemDetailWith500(): void
    {
        $response = $this->send('/api/broken');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Something broke.', $this->json($response)['detail']);
    }

    public function testAFailedRequestRunsTheProblemHandlerOnce(): void
    {
        $this->send('/api/broken');

        $this->assertSame(1, $this->problemHandlerCalls);
    }

    /**
     * `ResponseNormalizerMiddleware` calls `$handler->handle()` once the chain
     * is over, which throws a `LogicException` on purpose that
     * `RequestHandler` catches and turns into a problem. That response is then
     * discarded, so the problem handler (and, for HTML, the error page
     * rendering) runs on every successful request for nothing.
     *
     * This test fails until that is fixed.
     */
    public function testASuccessfulRequestDoesNotRunTheProblemHandler(): void
    {
        $response = $this->send('/api/hello');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            0,
            $this->problemHandlerCalls,
            'The problem handler must not run when the request succeeds.'
        );
    }
}
