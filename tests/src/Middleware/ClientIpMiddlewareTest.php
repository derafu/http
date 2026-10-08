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

use Derafu\Http\Middleware\ClientIpMiddleware;
use Derafu\Support\Ip;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Who the client of a request is. The headers of a request are written by whoever
 * sends it, so they are believed only when the connection comes from a proxy that
 * the application trusts; and when they are, the list of addresses is read from
 * its end, because what the client wrote is at the start.
 */
#[CoversClass(ClientIpMiddleware::class)]
class ClientIpMiddlewareTest extends TestCase
{
    /**
     * Runs a request through the middleware and gives what the handler got.
     *
     * @param array<string, string|list<string>> $headers
     * @return array{ip: mixed, network: mixed}
     */
    private function resolve(ClientIpMiddleware $middleware, ?string $remote, array $headers = []): array
    {
        $server = $remote === null ? [] : ['REMOTE_ADDR' => $remote];
        $request = new ServerRequest('GET', '/', $headers, null, '1.1', $server);

        $handler = new CapturingHandler();
        $middleware->process($request, $handler);
        $seen = $handler->request;

        $this->assertInstanceOf(ServerRequestInterface::class, $seen);

        return [
            'ip' => $seen->getAttribute(ClientIpMiddleware::ATTRIBUTE),
            'network' => $seen->getAttribute(ClientIpMiddleware::NETWORK_ATTRIBUTE),
        ];
    }

    // -------------------------------------------------------------------------
    // Without trusted proxies: the client is the connection.
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function provideHeadersThatAClientCanSend(): array
    {
        return [
            'X-Forwarded-For' => ['203.0.113.7', ['X-Forwarded-For' => '1.1.1.1']],
            'a chain in X-Forwarded-For' => ['203.0.113.7', ['X-Forwarded-For' => '1.1.1.1, 2.2.2.2']],
            'CF-Connecting-IP' => ['203.0.113.7', ['CF-Connecting-IP' => '1.1.1.1']],
            'X-Real-IP' => ['203.0.113.7', ['X-Real-IP' => '1.1.1.1']],
            'Forwarded' => ['203.0.113.7', ['Forwarded' => 'for=1.1.1.1']],
            'all of them' => ['203.0.113.7', [
                'X-Forwarded-For' => '1.1.1.1',
                'CF-Connecting-IP' => '2.2.2.2',
                'X-Real-IP' => '3.3.3.3',
                'Forwarded' => 'for=4.4.4.4',
            ]],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[Test]
    #[DataProvider('provideHeadersThatAClientCanSend')]
    public function shouldNotBelieveAnyHeaderWhenThereAreNoTrustedProxies(string $expected, array $headers): void
    {
        $result = $this->resolve(new ClientIpMiddleware(), '203.0.113.7', $headers);

        $this->assertSame($expected, $result['ip']);
    }

    #[Test]
    public function shouldNotLetAClientChooseItsOwnAddressByRotatingTheHeader(): void
    {
        // The hole that this middleware closes: a different header in each
        // request is still the same client.
        $middleware = new ClientIpMiddleware();

        foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $forged) {
            $result = $this->resolve($middleware, '203.0.113.7', ['X-Forwarded-For' => $forged]);
            $this->assertSame('203.0.113.7', $result['ip']);
            $this->assertSame('203.0.113.7', $result['network']);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideConnections(): array
    {
        return [
            'ipv4' => ['203.0.113.7', '203.0.113.7'],
            'ipv6' => ['2001:DB8::1', '2001:db8::1'],
            'a loopback is an address, not an unknown' => ['127.0.0.1', '127.0.0.1'],
            'an ipv6 loopback' => ['::1', '::1'],
            'an address of a private network' => ['172.18.0.5', '172.18.0.5'],
            'ipv4 written as ipv6 is ipv4' => ['::ffff:203.0.113.7', '203.0.113.7'],
            'with a port, as some runtimes give it' => ['203.0.113.7:51234', '203.0.113.7'],
        ];
    }

    #[Test]
    #[DataProvider('provideConnections')]
    public function shouldGiveTheAddressOfTheConnectionNormalized(string $remote, string $expected): void
    {
        $this->assertSame($expected, $this->resolve(new ClientIpMiddleware(), $remote)['ip']);
    }

    #[Test]
    public function shouldGiveUnknownWhenTheConnectionHasNoAddress(): void
    {
        $middleware = new ClientIpMiddleware(['private']);

        foreach ([null, '', 'not-an-ip', '/run/app.sock'] as $remote) {
            $result = $this->resolve($middleware, $remote, ['X-Forwarded-For' => '203.0.113.9']);

            $this->assertSame('unknown', $result['ip'], (string) $remote);
            $this->assertSame('unknown', $result['network'], (string) $remote);
        }
    }

    // -------------------------------------------------------------------------
    // With trusted proxies: the headers of a proxy are believed, the others not.
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldNotBelieveTheHeadersOfAConnectionThatIsNotATrustedProxy(): void
    {
        $middleware = new ClientIpMiddleware(['10.0.0.0/8']);

        // The proxy is 10.0.0.1, but this connection is not.
        $result = $this->resolve($middleware, '203.0.113.7', ['X-Forwarded-For' => '1.1.1.1']);

        $this->assertSame('203.0.113.7', $result['ip']);
    }

    #[Test]
    public function shouldBelieveTheHeaderOfATrustedProxy(): void
    {
        $middleware = new ClientIpMiddleware(['10.0.0.1']);

        $result = $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9']);

        $this->assertSame('203.0.113.9', $result['ip']);
    }

    #[Test]
    public function shouldReadTheListFromTheEndNotFromTheStart(): void
    {
        // The client wrote "1.1.1.1" to pass for someone else: the proxy added
        // what it saw at the end.
        $middleware = new ClientIpMiddleware(['private']);

        $result = $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => '1.1.1.1, 203.0.113.9']);

        $this->assertSame('203.0.113.9', $result['ip']);
    }

    /**
     * @return array<string, array{string, string, array<string, string|list<string>>}>
     */
    public static function provideChains(): array
    {
        // Expected client, connection, headers. The trusted proxies are `private`.
        return [
            'one proxy' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9']],
            'two proxies' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9, 10.0.0.2']],
            'three proxies' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9, 10.0.0.3, 10.0.0.2']],
            'a forged start and two proxies' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '1.1.1.1, 203.0.113.9, 10.0.0.2']],
            'a client of ipv6' => ['2001:db8::1', '10.0.0.1', ['X-Forwarded-For' => '2001:DB8:0::1']],
            'the client with a port' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9:51234']],
            'the client of ipv6 in brackets with a port' => ['2001:db8::1', '10.0.0.1', ['X-Forwarded-For' => '[2001:db8::1]:443']],
            'ipv4 written as ipv6' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '::ffff:203.0.113.9']],
            'spaces and a quoted value' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '  "203.0.113.9" ,10.0.0.2 ']],
            'what is not an address is skipped, at the start' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => 'unknown, 203.0.113.9']],
            'what is not an address is skipped, at the end' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9, _hidden']],
            'the header in lowercase' => ['203.0.113.9', '10.0.0.1', ['x-forwarded-for' => '203.0.113.9']],
            'several lines of the header are one list' => ['203.0.113.9', '10.0.0.1', ['X-Forwarded-For' => ['1.1.1.1', '203.0.113.9, 10.0.0.2']]],
            'all of them are proxies: the first' => ['10.0.0.5', '10.0.0.1', ['X-Forwarded-For' => '10.0.0.5, 10.0.0.2']],
            'a proxy of the list that is not trusted is the client' => ['198.51.100.4', '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9, 198.51.100.4, 10.0.0.2']],
        ];
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    #[Test]
    #[DataProvider('provideChains')]
    public function shouldFindTheClientInTheChainOfAddresses(string $expected, string $remote, array $headers): void
    {
        $result = $this->resolve(new ClientIpMiddleware(['private']), $remote, $headers);

        $this->assertSame($expected, $result['ip']);
    }

    #[Test]
    public function shouldUseTheConnectionWhenTheTrustedProxyDoesNotSayAnything(): void
    {
        $middleware = new ClientIpMiddleware(['private']);

        $this->assertSame('10.0.0.1', $this->resolve($middleware, '10.0.0.1')['ip']);
        $this->assertSame('10.0.0.1', $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => 'unknown, _hidden'])['ip']);
        $this->assertSame('10.0.0.1', $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => ''])['ip']);
    }

    // -------------------------------------------------------------------------
    // The headers: their order, headers with one address, and Forwarded.
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldReadTheHeadersInTheOrderThatWasConfigured(): void
    {
        $middleware = new ClientIpMiddleware(['private'], ['CF-Connecting-IP', 'X-Forwarded-For']);
        $both = ['CF-Connecting-IP' => '203.0.113.9', 'X-Forwarded-For' => '198.51.100.4'];

        $this->assertSame('203.0.113.9', $this->resolve($middleware, '10.0.0.1', $both)['ip']);

        // The first that has no address does not stop the next one.
        $this->assertSame(
            '198.51.100.4',
            $this->resolve($middleware, '10.0.0.1', ['CF-Connecting-IP' => 'garbage', 'X-Forwarded-For' => '198.51.100.4'])['ip']
        );
        $this->assertSame(
            '198.51.100.4',
            $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => '198.51.100.4'])['ip']
        );
    }

    #[Test]
    public function shouldIgnoreTheHeadersThatWereNotConfigured(): void
    {
        $middleware = new ClientIpMiddleware(['private'], ['CF-Connecting-IP']);

        $result = $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9']);

        $this->assertSame('10.0.0.1', $result['ip']);
    }

    #[Test]
    public function shouldReadTheHeadersOfTheRequestNotTheParametersOfTheServer(): void
    {
        // The parameters of the server (HTTP_X_FORWARDED_FOR) are a copy of the
        // headers; the headers of the request are what is read.
        $request = new ServerRequest('GET', '/', [], null, '1.1', [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        $handler = new CapturingHandler();
        (new ClientIpMiddleware(['private']))->process($request, $handler);
        $seen = $handler->request?->getAttribute(ClientIpMiddleware::ATTRIBUTE);

        $this->assertSame('10.0.0.1', $seen);
    }

    #[Test]
    public function shouldIgnoreTheHeadersWhenNoneWasConfigured(): void
    {
        $middleware = new ClientIpMiddleware(['private'], []);

        $this->assertSame('10.0.0.1', $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9'])['ip']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideForwarded(): array
    {
        return [
            'one element' => ['203.0.113.9', 'for=203.0.113.9'],
            'with the other parameters' => ['203.0.113.9', 'for=203.0.113.9;proto=https;by=10.0.0.1'],
            'quoted, with a port' => ['203.0.113.9', 'for="203.0.113.9:4711"'],
            'ipv6, quoted and in brackets' => ['2001:db8::1', 'for="[2001:db8::1]:4711"'],
            'the parameter in capitals' => ['203.0.113.9', 'FOR=203.0.113.9'],
            'a chain, from the end' => ['203.0.113.9', 'for=1.1.1.1, for=203.0.113.9;by=10.0.0.1, for=10.0.0.2'],
            'the by of a proxy is not the client' => ['203.0.113.9', 'for=203.0.113.9;by=198.51.100.99'],
            'an identifier that is not an address is skipped' => ['203.0.113.9', 'for=_hidden, for=203.0.113.9'],
            'unknown is skipped' => ['203.0.113.9', 'for=203.0.113.9, for=unknown'],
            'an element without for is skipped' => ['203.0.113.9', 'by=10.0.0.9, for=203.0.113.9'],
        ];
    }

    #[Test]
    #[DataProvider('provideForwarded')]
    public function shouldReadTheForParametersOfTheHeaderForwarded(string $expected, string $forwarded): void
    {
        $middleware = new ClientIpMiddleware(['private'], ['Forwarded']);

        $result = $this->resolve($middleware, '10.0.0.1', ['Forwarded' => $forwarded]);

        $this->assertSame($expected, $result['ip']);
    }

    // -------------------------------------------------------------------------
    // The trusted proxies: addresses, ranges and shortcuts.
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{list<string|null>, string, bool}>
     */
    public static function provideTrustedProxies(): array
    {
        // The proxies, the connection, whether its header is believed.
        return [
            'an address' => [['198.51.100.1'], '198.51.100.1', true],
            'an address that is another' => [['198.51.100.1'], '198.51.100.2', false],
            'a range' => [['198.51.100.0/24'], '198.51.100.77', true],
            'out of the range' => [['198.51.100.0/24'], '198.51.101.1', false],
            'several' => [['192.0.2.1', '198.51.100.0/24'], '198.51.100.9', true],
            'a range of ipv6' => [['2001:db8::/32'], '2001:db8:1::5', true],
            'out of a range of ipv6' => [['2001:db8::/32'], '2001:db9::1', false],
            'the shortcut private, 10.0.0.0/8' => [['private'], '10.20.30.40', true],
            'the shortcut private, 172.16.0.0/12' => [['private'], '172.31.0.1', true],
            'the shortcut private, 192.168.0.0/16' => [['private'], '192.168.1.1', true],
            'the shortcut private, ipv6' => [['private'], 'fd12:3456::1', true],
            'the shortcut private is not a public address' => [['private'], '203.0.113.7', false],
            'the shortcut private is not the loopback' => [['private'], '127.0.0.1', false],
            'the shortcut loopback' => [['loopback'], '127.0.0.1', true],
            'the shortcut loopback, ipv6' => [['loopback'], '::1', true],
            'the shortcut link-local' => [['link-local'], '169.254.1.1', true],
            'the shortcut link-local, ipv6' => [['link-local'], 'fe80::1', true],
            'the shortcuts in capitals' => [['PRIVATE'], '10.0.0.1', true],
            'a shortcut and a range' => [['loopback', '203.0.113.0/24'], '203.0.113.5', true],
            'a connection of ipv4 written as ipv6' => [['10.0.0.0/8'], '::ffff:10.0.0.1', true],
            'the empty values are not proxies' => [['', null, '  '], '10.0.0.1', false],
            'spaces around a proxy' => [[' 198.51.100.1 '], '198.51.100.1', true],
            'no proxies' => [[], '10.0.0.1', false],
        ];
    }

    /**
     * @param list<string|null> $proxies
     */
    #[Test]
    #[DataProvider('provideTrustedProxies')]
    public function shouldBelieveTheHeaderOnlyOfTheProxiesThatWereConfigured(array $proxies, string $connection, bool $believed): void
    {
        $result = $this->resolve(
            new ClientIpMiddleware($proxies),
            $connection,
            ['X-Forwarded-For' => '203.0.113.9']
        );

        // A connection that is not a trusted proxy is the client itself.
        $this->assertSame($believed ? '203.0.113.9' : Ip::normalize($connection), $result['ip']);
    }

    #[Test]
    public function shouldAcceptTheProxiesAsAnyIterable(): void
    {
        $proxies = (static function () {
            yield 'loopback';
            yield '198.51.100.0/24';
        })();

        $result = $this->resolve(new ClientIpMiddleware($proxies), '198.51.100.9', ['X-Forwarded-For' => '203.0.113.9']);

        $this->assertSame('203.0.113.9', $result['ip']);
    }

    // -------------------------------------------------------------------------
    // The network of the client.
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, int, string}>
     */
    public static function provideNetworks(): array
    {
        // Client, bits of ipv4, bits of ipv6, network.
        return [
            'ipv4 is the address by default' => ['203.0.113.77', 32, 64, '203.0.113.77'],
            'ipv6 is the /64 by default' => ['2001:db8:1:2:3:4:5:6', 32, 64, '2001:db8:1:2::'],
            'ipv4 with a prefix' => ['203.0.113.77', 24, 64, '203.0.113.0'],
            'ipv6 with a prefix' => ['2001:db8:1:2:3:4:5:6', 32, 48, '2001:db8:1::'],
            'ipv4 with prefix zero' => ['203.0.113.77', 0, 64, '0.0.0.0'],
        ];
    }

    #[Test]
    #[DataProvider('provideNetworks')]
    public function shouldGiveTheNetworkOfTheClient(string $client, int $ipv4Prefix, int $ipv6Prefix, string $network): void
    {
        $result = $this->resolve(new ClientIpMiddleware([], ['X-Forwarded-For'], $ipv4Prefix, $ipv6Prefix), $client);

        $this->assertSame(Ip::normalize($client), $result['ip']);
        $this->assertSame($network, $result['network']);
    }

    #[Test]
    public function shouldGiveTheSameNetworkToTheClientsThatShareIt(): void
    {
        $middleware = new ClientIpMiddleware();

        $first = $this->resolve($middleware, '2001:db8:1:2::1');
        $second = $this->resolve($middleware, '2001:db8:1:2:ffff::9');
        $other = $this->resolve($middleware, '2001:db8:1:3::1');

        $this->assertSame($first['network'], $second['network']);
        $this->assertNotSame($first['network'], $other['network']);
        $this->assertNotSame($first['ip'], $second['ip']);
    }

    #[Test]
    public function shouldGiveTheNetworkOfTheClientThatTheProxySays(): void
    {
        $middleware = new ClientIpMiddleware(['private']);

        $result = $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => '2001:db8:1:2:3:4:5:6']);

        $this->assertSame('2001:db8:1:2:3:4:5:6', $result['ip']);
        $this->assertSame('2001:db8:1:2::', $result['network']);
    }

    // -------------------------------------------------------------------------
    // What the pipeline gets, and the readers.
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldPassTheRequestOnAndGiveBackTheResponseOfTheHandler(): void
    {
        $request = new ServerRequest('GET', '/', [], null, '1.1', ['REMOTE_ADDR' => '203.0.113.7']);
        $response = new Response(418);

        $result = (new ClientIpMiddleware())->process($request, new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        });

        $this->assertSame($response, $result);
        // The request that came is not changed.
        $this->assertNull($request->getAttribute(ClientIpMiddleware::ATTRIBUTE));
    }

    #[Test]
    public function shouldReadTheAddressAndTheNetworkThatTheMiddlewareLeft(): void
    {
        $request = (new ServerRequest('GET', '/', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']))
            ->withAttribute(ClientIpMiddleware::ATTRIBUTE, '203.0.113.9')
            ->withAttribute(ClientIpMiddleware::NETWORK_ATTRIBUTE, '203.0.113.0');

        $this->assertSame('203.0.113.9', ClientIpMiddleware::ipOf($request));
        $this->assertSame('203.0.113.0', ClientIpMiddleware::networkOf($request));
    }

    #[Test]
    public function shouldReadTheConnectionWhenTheMiddlewareIsNotInThePipeline(): void
    {
        // Never the headers: without knowing which proxies to trust, they are the
        // client's.
        $request = new ServerRequest(
            'GET',
            '/',
            ['X-Forwarded-For' => '1.1.1.1', 'CF-Connecting-IP' => '2.2.2.2'],
            null,
            '1.1',
            ['REMOTE_ADDR' => '2001:db8:1:2:3:4:5:6']
        );

        $this->assertSame('2001:db8:1:2:3:4:5:6', ClientIpMiddleware::ipOf($request));
        $this->assertSame('2001:db8:1:2::', ClientIpMiddleware::networkOf($request));
    }

    #[Test]
    public function shouldReadUnknownWhenThereIsNothingToRead(): void
    {
        $request = new ServerRequest('GET', '/');

        $this->assertSame('unknown', ClientIpMiddleware::ipOf($request));
        $this->assertSame('unknown', ClientIpMiddleware::networkOf($request));
    }

    // -------------------------------------------------------------------------
    // A configuration that is wrong is an error.
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function provideWrongProxies(): array
    {
        return [
            'a name' => ['proxy.example.com'],
            'a range that is not valid' => ['10.0.0.0/33'],
            'an address that is not valid' => ['10.0.0.256'],
            'a shortcut that does not exist' => ['cloudflare'],
            'a wildcard' => ['*'],
        ];
    }

    #[Test]
    #[DataProvider('provideWrongProxies')]
    public function shouldNotAcceptATrustedProxyThatIsNotValid(string $proxy): void
    {
        try {
            new ClientIpMiddleware(['private', $proxy]);
            $this->fail('The trusted proxy "' . $proxy . '" is not valid.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertStringContainsString('The trusted proxy "' . $proxy . '" is not valid', $e->getMessage());
            $this->assertStringContainsString('loopback, private, link-local', $e->getMessage());
        }
    }

    #[Test]
    public function shouldNotAcceptAHeaderThatIsNotAName(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The header name "X Forwarded For" is not valid.');

        new ClientIpMiddleware(['private'], ['X Forwarded For']);
    }

    #[Test]
    public function shouldIgnoreTheEmptyHeaderNames(): void
    {
        $middleware = new ClientIpMiddleware(['private'], ['', null, ' X-Forwarded-For ']);

        $this->assertSame('203.0.113.9', $this->resolve($middleware, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9'])['ip']);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function provideWrongPrefixes(): array
    {
        return [
            'ipv4 too big' => [33, 64],
            'ipv4 negative' => [-1, 64],
            'ipv6 too big' => [32, 129],
            'ipv6 negative' => [32, -1],
        ];
    }

    #[Test]
    #[DataProvider('provideWrongPrefixes')]
    public function shouldNotAcceptAPrefixThatIsNotValid(int $ipv4Prefix, int $ipv6Prefix): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        new ClientIpMiddleware([], ['X-Forwarded-For'], $ipv4Prefix, $ipv6Prefix);
    }
}

/**
 * A handler that keeps the request that it was given.
 */
final class CapturingHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response();
    }
}
