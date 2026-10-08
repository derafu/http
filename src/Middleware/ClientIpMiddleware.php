<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Middleware;

use Derafu\Support\Ip;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Decides which is the IP address of the client of a request, once, and leaves
 * it in the request for everything that comes after (the limit of requests, the
 * limit of failed logins, the logs...).
 *
 * The address of the connection (`REMOTE_ADDR`) is the one of whoever connected:
 * the client, or the proxy that is in front of the application. The headers that
 * proxies add (`X-Forwarded-For`, `CF-Connecting-IP`...) are written by whoever
 * sends the request, so a client can send them with any address. They are only
 * to be believed when they come from a proxy that the application trusts, and
 * which proxies those are depends on where the application runs: it is not
 * something that the code can guess. So:
 *
 *   - **Without trusted proxies** (the default) the client is the address of the
 *     connection, and no header is read. It is the right answer when the
 *     application is the one that receives the connections.
 *   - **With trusted proxies**, the headers are read only if the connection
 *     comes from one of them. A connection from any other address is the client,
 *     whatever its headers say.
 *
 * The trusted proxies are addresses or ranges (CIDR, see `Ip::inRange()`), and
 * there are shortcuts for the common ones: `loopback`, `private` (the networks
 * that are not routed on the Internet: `10.0.0.0/8`, `172.16.0.0/12`,
 * `192.168.0.0/16` and `fc00::/7`, the ones of a network of containers) and
 * `link-local`. The ranges of a service (the ones that a CDN publishes) are
 * written as they are: they are not in the code because they change.
 *
 * The headers are the ones that carry the address, in order of preference, and
 * the first that gives an address wins. Each one is read as the list of the
 * addresses that the request went through, from the client to the proxy that is
 * closest to the application (the headers that have only one address, like
 * `CF-Connecting-IP`, are a list of one), and `Forwarded` (RFC 7239) is read by
 * its `for` parameters. The list is read **from the end**: the client is the
 * first address that is not a trusted proxy. The first address of a list is
 * the one that the client wrote, so it is not to be believed when there is a
 * proxy between; each proxy adds to the end what it saw. If all the addresses
 * are trusted proxies, the client is the first one. What is not an address
 * (`unknown`, `_hidden`) is skipped.
 *
 * The request gets two attributes:
 *
 *   - `client_ip`: the address of the client, normalized (see `Ip::normalize()`),
 *     or `unknown` if the connection has none.
 *   - `client_network`: the network of the client, in CIDR notation: the address
 *     with the bits of its host set to zero, and the prefix (`203.0.113.77/32`,
 *     `2001:db8:1:2::/64`). It is what a limit should count by: a client of IPv6
 *     has a whole `/64` (or more) and changing its address within it costs
 *     nothing. By default it is the `/32` for IPv4 (the address, a network of
 *     one) and the `/64` for IPv6. It is `unknown` if the connection has none.
 *
 * Whoever reads them without this middleware in the pipeline can use `ipOf()`
 * and `networkOf()`, which give the address of the connection.
 */
class ClientIpMiddleware implements MiddlewareInterface
{
    /**
     * The attribute of the request with the address of the client.
     */
    public const ATTRIBUTE = 'client_ip';

    /**
     * The attribute of the request with the network of the client.
     */
    public const NETWORK_ATTRIBUTE = 'client_network';

    /**
     * What is given when the connection has no address.
     */
    public const UNKNOWN = 'unknown';

    /**
     * The shortcuts for the ranges of the trusted proxies.
     *
     * @var array<string, list<string>>
     */
    public const SHORTCUTS = [
        'loopback' => ['127.0.0.0/8', '::1'],
        'private' => ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'],
        'link-local' => ['169.254.0.0/16', 'fe80::/10'],
    ];

    /**
     * The ranges of the trusted proxies.
     *
     * @var list<string>
     */
    private readonly array $trustedProxies;

    /**
     * The names of the headers that carry the address of the client.
     *
     * @var list<string>
     */
    private readonly array $headers;

    /**
     * Creates the middleware.
     *
     * @param iterable<string|null> $trustedProxies The proxies that are trusted:
     * addresses, ranges (CIDR) and the shortcuts (`loopback`, `private`,
     * `link-local`). The empty values are ignored (what an empty environment
     * variable gives). None by default: no header is read.
     * @param iterable<string|null> $headers The headers that carry the address of
     * the client, in order of preference. `X-Forwarded-For` by default.
     * @param int $ipv4Prefix The bits of the network of an IPv4 client, from 0 to
     * 32. By default 32: the address.
     * @param int $ipv6Prefix The bits of the network of an IPv6 client, from 0 to
     * 128. By default 64.
     * @throws InvalidArgumentException If a trusted proxy, a header or a prefix is
     * not valid: a configuration that is wrong is not ignored.
     */
    public function __construct(
        iterable $trustedProxies = [],
        iterable $headers = ['X-Forwarded-For'],
        private readonly int $ipv4Prefix = 32,
        private readonly int $ipv6Prefix = 64
    ) {
        $this->trustedProxies = $this->ranges($trustedProxies);
        $this->headers = $this->headerNames($headers);

        // The prefixes are checked now, not with the first request.
        Ip::network('0.0.0.0', $this->ipv4Prefix, $this->ipv6Prefix);
        Ip::network('::', $this->ipv4Prefix, $this->ipv6Prefix);
    }

    /**
     * {@inheritDoc}
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $ip = $this->resolve($request);
        $network = $ip === self::UNKNOWN
            ? self::UNKNOWN
            : (string) Ip::cidr($ip, $this->ipv4Prefix, $this->ipv6Prefix)
        ;

        return $handler->handle(
            $request
                ->withAttribute(self::ATTRIBUTE, $ip)
                ->withAttribute(self::NETWORK_ATTRIBUTE, $network)
        );
    }

    /**
     * Gets the address of the client of a request.
     *
     * It is the attribute that this middleware leaves. If the middleware is not
     * in the pipeline, it is the address of the connection: the headers are never
     * believed without knowing which proxies to trust.
     *
     * @param ServerRequestInterface $request The request.
     * @return string The address, or `unknown`.
     */
    public static function ipOf(ServerRequestInterface $request): string
    {
        $ip = $request->getAttribute(self::ATTRIBUTE);

        return is_string($ip) && $ip !== ''
            ? $ip
            : (self::connectionOf($request) ?? self::UNKNOWN)
        ;
    }

    /**
     * Gets the network of the client of a request, in CIDR notation: what to
     * count by when a limit is applied to the client.
     *
     * It is the attribute that this middleware leaves. If the middleware is not
     * in the pipeline, it is the network of the address of the connection, with
     * the defaults (the `/32` for IPv4, the `/64` for IPv6).
     *
     * @param ServerRequestInterface $request The request.
     * @return string The network (`203.0.113.77/32`), or `unknown`.
     */
    public static function networkOf(ServerRequestInterface $request): string
    {
        $network = $request->getAttribute(self::NETWORK_ATTRIBUTE);
        if (is_string($network) && $network !== '') {
            return $network;
        }

        $ip = self::ipOf($request);

        return $ip === self::UNKNOWN ? self::UNKNOWN : (string) Ip::cidr($ip);
    }

    /**
     * Decides the address of the client.
     */
    private function resolve(ServerRequestInterface $request): string
    {
        $connection = self::connectionOf($request);
        if ($connection === null) {
            return self::UNKNOWN;
        }

        // Only a proxy that is trusted is believed about who sent the request.
        if ($this->trustedProxies === [] || !$this->isTrusted($connection)) {
            return $connection;
        }

        foreach ($this->headers as $header) {
            $client = $this->clientIn($this->chain($request, $header));
            if ($client !== null) {
                return $client;
            }
        }

        return $connection;
    }

    /**
     * Gets the address of the connection, normalized.
     */
    private static function connectionOf(ServerRequestInterface $request): ?string
    {
        $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($address) ? Ip::parse($address) : null;
    }

    /**
     * Whether an address is one of a trusted proxy.
     */
    private function isTrusted(string $ip): bool
    {
        return Ip::inAnyRange($ip, $this->trustedProxies);
    }

    /**
     * Reads a header as the list of the addresses that the request went through,
     * from the client to the closest proxy. The values that are not an address
     * are left out.
     *
     * @return list<string>
     */
    private function chain(ServerRequestInterface $request, string $header): array
    {
        $isForwarded = strtolower($header) === 'forwarded';

        $addresses = [];
        // A header can come in several lines: they are one list, in order.
        foreach ($request->getHeader($header) as $line) {
            foreach (explode(',', $line) as $element) {
                $value = $isForwarded ? $this->forParameter($element) : $element;
                $ip = $value === null ? null : Ip::parse($value);
                if ($ip !== null) {
                    $addresses[] = $ip;
                }
            }
        }

        return $addresses;
    }

    /**
     * Takes the value of the parameter `for` out of an element of the header
     * `Forwarded` (`for=192.0.2.60;proto=http;by=203.0.113.43`).
     */
    private function forParameter(string $element): ?string
    {
        foreach (explode(';', $element) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2 && strtolower(trim($parts[0])) === 'for') {
                return trim($parts[1]);
            }
        }

        return null;
    }

    /**
     * Finds the client in a list of addresses: the first one, from the end, that
     * is not a trusted proxy. If they all are, the first of the list.
     *
     * @param list<string> $addresses The addresses, from the client to the proxy
     * that is closest to the application.
     */
    private function clientIn(array $addresses): ?string
    {
        for ($i = count($addresses) - 1; $i >= 0; $i--) {
            if (!$this->isTrusted($addresses[$i])) {
                return $addresses[$i];
            }
        }

        return $addresses[0] ?? null;
    }

    /**
     * Expands and checks the trusted proxies.
     *
     * @param iterable<string|null> $proxies
     * @return list<string>
     */
    private function ranges(iterable $proxies): array
    {
        $ranges = [];
        foreach ($proxies as $proxy) {
            $proxy = trim((string) $proxy);
            if ($proxy === '') {
                continue;
            }

            $shortcut = strtolower($proxy);
            if (isset(self::SHORTCUTS[$shortcut])) {
                array_push($ranges, ...self::SHORTCUTS[$shortcut]);
                continue;
            }

            if (!Ip::isRange($proxy)) {
                throw new InvalidArgumentException([
                    'The trusted proxy "{proxy}" is not valid: it must be an IP address, a range (CIDR) or one of: {shortcuts}.',
                    'proxy' => $proxy,
                    'shortcuts' => implode(', ', array_keys(self::SHORTCUTS)),
                ]);
            }
            $ranges[] = $proxy;
        }

        return $ranges;
    }

    /**
     * Checks the names of the headers.
     *
     * @param iterable<string|null> $headers
     * @return list<string>
     */
    private function headerNames(iterable $headers): array
    {
        $names = [];
        foreach ($headers as $header) {
            $header = trim((string) $header);
            if ($header === '') {
                continue;
            }

            if (!preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $header)) {
                throw new InvalidArgumentException([
                    'The header name "{header}" is not valid.',
                    'header' => $header,
                ]);
            }
            $names[] = $header;
        }

        return $names;
    }
}
