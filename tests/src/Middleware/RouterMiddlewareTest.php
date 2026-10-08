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

use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Routing\Contract\RouteMatchInterface;
use Derafu\Routing\Exception\InvalidPathException;
use Derafu\Routing\Exception\RouteNotFoundException;
use Derafu\Routing\Parser\DynamicParser;
use Derafu\Routing\Parser\StaticParser;
use Derafu\Routing\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The path that the rest of the pipeline sees is the canonical one. A rule that
 * decides by the text of the path (the protected paths of `derafu/auth`) and the
 * route that reads its meaning must be about the same path: `/api//index` is
 * `/api/index`, and a path that has no safe form is not served.
 */
#[CoversClass(RouterMiddleware::class)]
class RouterMiddlewareTest extends TestCase
{
    /**
     * A request as a server gives it.
     */
    private function request(Uri $uri): ServerRequest
    {
        return new ServerRequest('GET', $uri, [], null, '1.1', [
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => 443,
            'REQUEST_SCHEME' => 'https',
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
        ]);
    }

    private function middleware(): RouterMiddleware
    {
        $router = new Router(parsers: [new StaticParser(), new DynamicParser()]);
        $router->addRoute('home', '/', 'HomeController@index');
        $router->addRoute('index', '/api/index', 'IndexController@action');
        $router->addRoute('api', '/api/{resource:.+}', 'ApiController@dispatch');

        return new RouterMiddleware($router);
    }

    /**
     * Sends a path through the middleware and gives what the next handler got.
     */
    private function through(string $path, string $query = ''): ?ServerRequestInterface
    {
        $uri = (new Uri('https://example.com'))->withPath($path)->withQuery($query);
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        $this->middleware()->process($this->request($uri), $handler);

        return $handler->request;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideWaysOfWritingAPath(): array
    {
        // What the request has, what the next middleware must see.
        return [
            'as it is' => ['/api/index', '/api/index'],
            'a double slash at the start' => ['//api/index', '/api/index'],
            'a double slash in the middle' => ['/api//index', '/api/index'],
            'a dot segment' => ['/api/./index', '/api/index'],
            'a slash at the end' => ['/api/index/', '/api/index'],
            'an escaped letter' => ['/api/%69ndex', '/api/index'],
            'an escaped dot segment' => ['/api/%2E/index', '/api/index'],
            'escapes that stay, in capitals' => ['/api/ma%c3%b1ana', '/api/ma%C3%B1ana'],
            'the root' => ['/', '/'],
            'nothing is the root' => ['', '/'],
            'only slashes are the root' => ['///', '/'],
        ];
    }

    #[Test]
    #[DataProvider('provideWaysOfWritingAPath')]
    public function shouldGiveTheNextMiddlewareTheCanonicalPath(string $path, string $expected): void
    {
        $request = $this->through($path);

        $this->assertNotNull($request);
        $this->assertSame($expected, $request->getUri()->getPath());
    }

    #[Test]
    public function shouldMatchTheSameRouteWhateverTheWayOfWritingThePath(): void
    {
        foreach (['/api/index', '//api/index', '/api//index', '/api/./index', '/api/index/', '/api/%69ndex'] as $path) {
            $match = $this->through($path)?->getAttribute(RouterMiddleware::ROUTE_ATTRIBUTE);

            $this->assertInstanceOf(RouteMatchInterface::class, $match);
            $this->assertSame('index', $match->getName(), $path);
        }
    }

    #[Test]
    public function shouldGiveADynamicRouteTheCanonicalParameter(): void
    {
        // The API takes everything below /api: with the path as it came, `resource`
        // would be "/other" or "%6Ftext" and whoever reads it would have to guess.
        foreach (['/api/other', '/api//other', '/api/./other', '/api/%6Fther', '/api/other/'] as $path) {
            $match = $this->through($path)?->getAttribute(RouterMiddleware::ROUTE_ATTRIBUTE);

            $this->assertInstanceOf(RouteMatchInterface::class, $match);
            $this->assertSame(['resource' => 'other'], $match->getParameters(), $path);
        }
    }

    #[Test]
    public function shouldKeepTheRestOfTheUri(): void
    {
        $request = $this->through('/api//index/', 'a=1&b=%2F');

        $this->assertNotNull($request);
        $this->assertSame('/api/index', $request->getUri()->getPath());
        $this->assertSame('a=1&b=%2F', $request->getUri()->getQuery());
        $this->assertSame('example.com', $request->getUri()->getHost());
        $this->assertSame('https', $request->getUri()->getScheme());
    }

    #[Test]
    public function shouldNotChangeTheRequestThatIsAlreadyCanonical(): void
    {
        $uri = (new Uri('https://example.com'))->withPath('/api/index');
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        $this->middleware()->process($this->request($uri), $handler);

        $this->assertSame($uri, $handler->request?->getUri());
    }

    /**
     * The escapes that are not valid (`%zz`) are not here: the implementation of
     * PSR-7 writes them as `%25zz` when it makes the URI, so they are never a path
     * that reaches the middleware (they are refused by `Url` and the router).
     *
     * @return array<string, array{string}>
     */
    public static function providePathsThatAreNotServed(): array
    {
        return [
            'a parent segment' => ['/api/../index'],
            'an escaped parent segment' => ['/api/%2e%2e/index'],
            'an escaped slash' => ['/api%2Findex'],
            'an escaped backslash' => ['/api%5Cindex'],
            'an escaped null byte' => ['/api/index%00'],
        ];
    }

    #[Test]
    #[DataProvider('providePathsThatAreNotServed')]
    public function shouldRefuseAPathThatHasNoSafeFormWithoutCallingTheNextMiddleware(string $path): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public bool $called = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return new Response();
            }
        };

        $uri = new Uri('https://example.com' . $path);
        try {
            $this->middleware()->process($this->request($uri), $handler);
            $this->fail('The path was served.');
        } catch (InvalidPathException $e) {
            $this->assertSame(400, $e->getCode());
        }

        $this->assertFalse($handler->called);
    }

    #[Test]
    public function shouldStillRefuseAPathThatHasNoRoute(): void
    {
        $router = new Router(parsers: [new StaticParser()]);
        $router->addRoute('home', '/', 'HomeController@index');

        $this->expectException(RouteNotFoundException::class);

        (new RouterMiddleware($router))->process(
            $this->request(new Uri('https://example.com/nowhere')),
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response();
                }
            }
        );
    }
}
