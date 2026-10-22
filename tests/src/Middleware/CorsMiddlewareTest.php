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

use Derafu\Http\Middleware\CorsMiddleware;
use Derafu\Http\Response;
use Derafu\TestsHttp\Fixture\CountingHandler;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The CORS rules decide, by path and origin, which responses get the headers
 * of CORS; a rule that is not well written is a configuration that fails when
 * the middleware is created, not when a request arrives.
 */
#[CoversClass(CorsMiddleware::class)]
#[UsesClass(Response::class)]
final class CorsMiddlewareTest extends TestCase
{
    /**
     * @param array<string,string> $headers
     */
    private function request(string $path, string $method = 'GET', array $headers = []): ServerRequestInterface
    {
        return new ServerRequest($method, 'http://localhost' . $path, $headers);
    }

    public function testWithoutOriginTheRequestGoesOnWithoutCorsHeaders(): void
    {
        $response = (new CorsMiddleware())->process($this->request('/api'), $handler = new CountingHandler());

        $this->assertSame(1, $handler->calls);
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testWithoutRulesAnyOriginIsAllowedOnAnyPath(): void
    {
        $response = (new CorsMiddleware())->process(
            $this->request('/any/path', 'GET', ['Origin' => 'https://app.example.com']),
            new CountingHandler()
        );

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, PUT, PATCH, DELETE, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type, Authorization, Accept', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
    }

    public function testAPathThatNoRuleCoversGoesOnWithoutCorsHeaders(): void
    {
        $middleware = new CorsMiddleware([['path' => '^/api']]);

        $response = $middleware->process(
            $this->request('/web', 'GET', ['Origin' => 'https://app.example.com']),
            $handler = new CountingHandler()
        );

        $this->assertSame(1, $handler->calls);
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testAnOriginThatTheRuleDoesNotAllowGoesOnWithoutCorsHeaders(): void
    {
        $middleware = new CorsMiddleware([['path' => '^/api', 'allowedOrigins' => ['https://app.example.com']]]);

        $response = $middleware->process(
            $this->request('/api', 'GET', ['Origin' => 'https://other.example.com']),
            $handler = new CountingHandler()
        );

        $this->assertSame(1, $handler->calls);
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testTheFirstRuleThatMatchesIsTheOneThatApplies(): void
    {
        $middleware = new CorsMiddleware([
            ['path' => '^/api', 'allowedOrigins' => ['https://one.example.com']],
            ['path' => '^/api', 'allowedOrigins' => ['*']],
        ]);

        $response = $middleware->process(
            $this->request('/api', 'GET', ['Origin' => 'https://two.example.com']),
            $handler = new CountingHandler()
        );

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testAnAllowedOriginIsReflectedWhenTheRuleAllowsCredentials(): void
    {
        $middleware = new CorsMiddleware([['path' => '.*', 'allowCredentials' => true]]);

        $response = $middleware->process(
            $this->request('/api', 'GET', ['Origin' => 'https://app.example.com']),
            new CountingHandler()
        );

        // With credentials, `*` is not valid: the origin is the one of the request.
        $this->assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testAnOptionThatIsNullTakesTheDefault(): void
    {
        $middleware = new CorsMiddleware([['path' => '.*', 'allowedHeaders' => null]]);

        $response = $middleware->process(
            $this->request('/api', 'GET', ['Origin' => 'https://app.example.com']),
            new CountingHandler()
        );

        $this->assertSame('Content-Type, Authorization, Accept', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testAPreflightIsAnsweredWithoutReachingTheHandler(): void
    {
        $middleware = new CorsMiddleware([[
            'path' => '^/api',
            'allowedOrigins' => ['https://app.example.com'],
            'allowedMethods' => ['GET', 'POST'],
            'allowedHeaders' => ['Content-Type'],
            'maxAge' => 600,
        ]]);

        $response = $middleware->process(
            $this->request('/api', 'OPTIONS', ['Origin' => 'https://app.example.com']),
            $handler = new CountingHandler()
        );

        $this->assertSame(0, $handler->calls);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertSame('600', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    /**
     * @return array<string, array{array<int, array<string, mixed>>, string}>
     */
    public static function provideRulesThatAreNotValid(): array
    {
        $empty = 'The CORS rule 0 must have a "path" that is not empty: the regular expression that is matched against the path.';

        return [
            'a rule without path' => [
                [['allowedOrigins' => ['*']]],
                'The key "rules.0.path" is required.',
            ],
            'a path that is empty' => [[['path' => ' ']], $empty],
            'a path that is not a text' => [
                [['path' => 5]],
                'The key "rules.0.path" must be of type string, int given.',
            ],
            'the second rule is the one that fails' => [
                [['path' => '^/a'], ['path' => '^/b', 'allowedOrigins' => 'https://app.example.com']],
                'The key "rules.1.allowedOrigins" must be of type list<string>, string given.',
            ],
            'origins that are not texts' => [
                [['path' => '.*', 'allowedOrigins' => ['https://app.example.com', 7]]],
                'The key "rules.0.allowedOrigins.1" must be of type string, int given.',
            ],
            'methods that are not a list' => [
                [['path' => '.*', 'allowedMethods' => ['a' => 'GET']]],
                'The key "rules.0.allowedMethods" must be of type list<string>, array given.',
            ],
            'credentials that are not true or false' => [
                [['path' => '.*', 'allowCredentials' => 'yes']],
                'The key "rules.0.allowCredentials" must be of type bool, string given.',
            ],
            'a max age that is not a number' => [
                [['path' => '.*', 'maxAge' => '3600']],
                'The key "rules.0.maxAge" must be of type int, string given.',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    #[DataProvider('provideRulesThatAreNotValid')]
    public function testARuleThatIsNotValidIsATranslatableError(array $rules, string $message): void
    {
        $exception = null;
        try {
            new CorsMiddleware($rules);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }
}
