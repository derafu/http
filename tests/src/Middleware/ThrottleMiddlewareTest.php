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

use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Exception\TooManyRequestsException;
use Derafu\Http\Middleware\ClientIpMiddleware;
use Derafu\Http\Middleware\ThrottleMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * The limit of requests counts by the network of the client, that comes from
 * `ClientIpMiddleware` (or from the address of the connection if the pipeline does
 * not have it), and never from the headers of the request: they are the client's,
 * so counting by them would let it choose its own counter and have no limit.
 */
#[CoversClass(ThrottleMiddleware::class)]
#[UsesClass(ClientIpMiddleware::class)]
#[UsesClass(TooManyRequestsException::class)]
#[UsesClass(HttpStatus::class)]
class ThrottleMiddlewareTest extends TestCase
{
    private const LIMIT = 2;

    private ThrottleMiddleware $throttle;

    protected function setUp(): void
    {
        $this->throttle = $this->newThrottle();
    }

    /**
     * A limit with its own counters.
     */
    private function newThrottle(): ThrottleMiddleware
    {
        return new ThrottleMiddleware(new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'limit' => self::LIMIT, 'interval' => '1 hour'],
            new InMemoryStorage()
        ));
    }

    /**
     * Sends a request through the middlewares, in that order.
     *
     * @param array<string, string> $headers
     * @param list<MiddlewareInterface> $middlewares
     */
    private function send(array $middlewares, ?string $remote, array $headers = []): ResponseInterface
    {
        // As a server gives it: the headers are also in the parameters of the
        // server, as HTTP_X_FORWARDED_FOR for X-Forwarded-For.
        $server = $remote === null ? [] : ['REMOTE_ADDR' => $remote];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = new ServerRequest('GET', '/', $headers, null, '1.1', $server);

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        foreach (array_reverse($middlewares) as $middleware) {
            $handler = new class ($middleware, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private readonly MiddlewareInterface $middleware,
                    private readonly RequestHandlerInterface $next
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $handler->handle($request);
    }

    /**
     * Sends the same request until the limit is over, and says how many were
     * accepted.
     *
     * @param list<MiddlewareInterface> $middlewares
     * @param callable(int): array<string, string> $headers The headers of each
     * request, by its number.
     */
    private function accepted(array $middlewares, ?string $remote, ?callable $headers = null, int $tries = 10): int
    {
        for ($i = 0; $i < $tries; $i++) {
            try {
                $this->send($middlewares, $remote, $headers === null ? [] : $headers($i));
            } catch (TooManyRequestsException) {
                return $i;
            }
        }

        return $tries;
    }

    #[Test]
    public function shouldAcceptUpToTheLimitAndThenRefuse(): void
    {
        $this->assertSame(self::LIMIT, $this->accepted([$this->throttle], '203.0.113.7'));
    }

    #[Test]
    public function shouldTellTheLimitInTheHeadersOfTheResponse(): void
    {
        $first = $this->send([$this->throttle], '203.0.113.7');
        $second = $this->send([$this->throttle], '203.0.113.7');

        $this->assertSame((string) self::LIMIT, $first->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('1', $first->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame('0', $second->getHeaderLine('X-RateLimit-Remaining'));
    }

    #[Test]
    public function shouldTellWhenToTryAgainInTheErrorThatItThrows(): void
    {
        $this->accepted([$this->throttle], '203.0.113.7');

        try {
            $this->send([$this->throttle], '203.0.113.7');
            $this->fail('The limit is over.');
        } catch (TooManyRequestsException $e) {
            $headers = $e->getHeaders();
            $this->assertSame(self::LIMIT, $headers['X-RateLimit-Limit']);
            $this->assertSame(0, $headers['X-RateLimit-Remaining']);
            $this->assertGreaterThan(0, $headers['Retry-After']);
            $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        }
    }

    #[Test]
    public function shouldCountEachConnectionByItself(): void
    {
        $this->accepted([$this->throttle], '203.0.113.7');

        // Another client is not limited by the first one.
        $this->assertSame(self::LIMIT, $this->accepted([$this->throttle], '203.0.113.8'));
    }

    // -------------------------------------------------------------------------
    // The headers of the client do not choose the counter.
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldNotLetAClientRotateTheHeaderToHaveNoLimit(): void
    {
        // Before, each address in X-Forwarded-For had its own counter: sending a
        // different one in each request made the limit useless.
        $rotating = fn (int $i): array => ['X-Forwarded-For' => '198.51.100.' . ($i + 1)];

        $this->assertSame(self::LIMIT, $this->accepted([$this->throttle], '203.0.113.7', $rotating));
    }

    #[Test]
    public function shouldNotLetAClientRotateTheHeaderEvenWithTheMiddlewareInThePipeline(): void
    {
        $rotating = fn (int $i): array => [
            'X-Forwarded-For' => '198.51.100.' . ($i + 1),
            'CF-Connecting-IP' => '192.0.2.' . ($i + 1),
        ];

        // No trusted proxies: the headers are not believed.
        $this->assertSame(
            self::LIMIT,
            $this->accepted([new ClientIpMiddleware(), $this->throttle], '203.0.113.7', $rotating)
        );

        // The connection is not a trusted proxy: the same (with counters of its
        // own, so the ones above do not count).
        $this->assertSame(
            self::LIMIT,
            $this->accepted([new ClientIpMiddleware(['10.0.0.0/8']), $this->newThrottle()], '203.0.113.7', $rotating)
        );
    }

    #[Test]
    public function shouldNotLetAClientBehindATrustedProxyChooseItsCounterWithTheStartOfTheHeader(): void
    {
        // The proxy adds the address that it saw at the end: what the client
        // wrote at the start does not change who it is.
        $middlewares = [new ClientIpMiddleware(['private']), $this->throttle];
        $forging = fn (int $i): array => ['X-Forwarded-For' => '198.51.100.' . ($i + 1) . ', 203.0.113.9'];

        $this->assertSame(self::LIMIT, $this->accepted($middlewares, '10.0.0.1', $forging));
    }

    #[Test]
    public function shouldCountEachClientBehindATrustedProxy(): void
    {
        $middlewares = [new ClientIpMiddleware(['private']), $this->throttle];
        $client = fn (string $address): callable => fn (int $i): array => ['X-Forwarded-For' => $address];

        // Everyone comes from the proxy, but each one has its counter.
        $this->assertSame(self::LIMIT, $this->accepted($middlewares, '10.0.0.1', $client('203.0.113.9')));
        $this->assertSame(self::LIMIT, $this->accepted($middlewares, '10.0.0.1', $client('203.0.113.10')));
        $this->assertSame(0, $this->accepted($middlewares, '10.0.0.1', $client('203.0.113.9')));
    }

    #[Test]
    public function shouldCountTheAddressesOfAnIpv6NetworkTogether(): void
    {
        // A client of IPv6 has a whole /64: changing within it must not give it a
        // counter for each address.
        $inTheNetwork = fn (int $i): array => [];
        $middlewares = [new ClientIpMiddleware(), $this->throttle];

        $count = 0;
        foreach (['2001:db8:1:2::1', '2001:db8:1:2:aaaa::2', '2001:db8:1:2:bbbb:cccc::3', '2001:db8:1:2:ffff:ffff:ffff:ffff'] as $address) {
            try {
                $this->send($middlewares, $address);
                $count++;
            } catch (TooManyRequestsException) {
                break;
            }
        }

        $this->assertSame(self::LIMIT, $count);
        // Another network is another client.
        $this->assertSame(self::LIMIT, $this->accepted($middlewares, '2001:db8:1:3::1', $inTheNetwork));
    }

    #[Test]
    public function shouldCountTheClientsWithoutAddressTogether(): void
    {
        $this->accepted([$this->throttle], null);

        // What has no address is one client: it is not a way to have no limit.
        $this->assertSame(0, $this->accepted([$this->throttle], 'not-an-ip'));
    }
}
