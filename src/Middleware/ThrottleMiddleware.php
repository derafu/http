<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Middleware;

use Derafu\Http\Exception\TooManyRequestsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Middleware for rate limiting requests.
 */
class ThrottleMiddleware implements MiddlewareInterface
{
    /**
     * Creates a new throttle middleware.
     *
     * @param RateLimiterFactory $rateLimiterFactory The rate limiter factory.
     */
    public function __construct(
        private readonly RateLimiterFactory $rateLimiterFactory
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Skip the throttle middleware if the request should not be processed.
        if (!$this->shouldProcess($request)) {
            return $handler->handle($request);
        }

        // Consume the tokens needed for this request.
        $limit = $this->validateRequest($request);

        // Validate the result of the limit.
        $this->enforceLimit($limit);

        // Continue with the request.
        $response = $handler->handle($request);

        // Add the headers to the response.
        $headers = $this->getHeaders($limit);
        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        // Return the response with the rate limiting headers.
        return $response;
    }

    /**
     * Checks if the request should be processed.
     *
     * @param ServerRequestInterface $request The request.
     * @return bool True if the request should be processed, false otherwise.
     */
    protected function shouldProcess(ServerRequestInterface $request): bool
    {
        return true;
    }

    /**
     * Gets the identifier for the request.
     *
     * @param ServerRequestInterface $request The request.
     * @return string The identifier.
     */
    protected function getIdentifier(ServerRequestInterface $request): string
    {
        $ip = $this->getClientIp($request);

        return 'throttle_' . hash('sha256', $ip);
    }

    /**
     * Gets the real client IP address from various sources.
     *
     * This method checks multiple headers and server parameters to find the real
     * client IP address, which is especially important when using proxies,
     * load balancers, or CDNs.
     *
     * @param ServerRequestInterface $request The request.
     * @return string The client IP address.
     */
    protected function getClientIp(ServerRequestInterface $request): string
    {
        $headers = $request->getHeaders();
        $serverParams = $request->getServerParams();

        // List of headers to check for the real client IP (in order of preference).
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare.
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header.
            'HTTP_X_REAL_IP',            // Nginx proxy.
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster environments.
            'HTTP_X_FORWARDED',          // Alternative forwarded header.
            'HTTP_FORWARDED_FOR',        // Alternative forwarded header.
            'HTTP_FORWARDED',            // RFC 7239.
            'HTTP_CLIENT_IP',            // Some proxies.
            'REMOTE_ADDR',               // Direct connection (fallback)
        ];

        foreach ($ipHeaders as $header) {
            $ip = $this->getIpFromSource($headers, $serverParams, $header);
            if ($ip !== null && $this->isValidIp($ip)) {
                return $ip;
            }
        }

        return 'unknown';
    }

    /**
     * Gets IP address from a specific header or server parameter.
     *
     * @param array $headers The request headers.
     * @param array $serverParams The server parameters.
     * @param string $source The header or server parameter name.
     * @return string|null The IP address or null if not found.
     */
    protected function getIpFromSource(array $headers, array $serverParams, string $source): ?string
    {
        // Check server parameters first (for headers like HTTP_X_FORWARDED_FOR).
        if (isset($serverParams[$source])) {
            $value = $serverParams[$source];
        }
        // Check headers array (for headers like X-Forwarded-For).
        elseif (isset($headers[$source])) {
            $value = is_array($headers[$source]) ? $headers[$source][0] : $headers[$source];
        }
        // Check headers with HTTP_ prefix removed.
        elseif (isset($headers[str_replace('HTTP_', '', $source)])) {
            $headerName = str_replace('HTTP_', '', $source);
            $value = is_array($headers[$headerName]) ? $headers[$headerName][0] : $headers[$headerName];
        } else {
            return null;
        }

        // Handle comma-separated IPs (like X-Forwarded-For: 192.168.1.1, 10.0.0.1).
        if (str_contains($value, ',')) {
            $ips = array_map('trim', explode(',', $value));
            // Return the first valid IP (usually the original client).
            foreach ($ips as $ip) {
                if ($this->isValidIp($ip)) {
                    return $ip;
                }
            }
        }

        return trim($value);
    }

    /**
     * Validates if the given string is a valid IP address.
     *
     * @param string $ip The IP address to validate.
     * @return bool True if valid, false otherwise.
     */
    protected function isValidIp(string $ip): bool
    {
        // Filter out private IPs and localhost for security
        $filtered = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        return $filtered !== false;
    }

    /**
     * Gets the rate limiter for the request.
     *
     * @param ServerRequestInterface $request The request.
     * @return LimiterInterface The rate limiter.
     */
    protected function getRateLimiter(ServerRequestInterface $request): LimiterInterface
    {
        $identifier = $this->getIdentifier($request);

        return $this->rateLimiterFactory->create($identifier);
    }

    /**
     * Gets the number of tokens needed for the request.
     *
     * @param ServerRequestInterface $request The request.
     * @return int The number of tokens needed.
     */
    protected function getTokensNeeded(ServerRequestInterface $request): int
    {
        return 1;
    }

    /**
     * Validates the request.
     *
     * @param ServerRequestInterface $request The request.
     * @return RateLimit The rate limit.
     */
    protected function validateRequest(ServerRequestInterface $request): RateLimit
    {
        $rateLimiter = $this->getRateLimiter($request);

        $limit = $rateLimiter->consume($this->getTokensNeeded($request));

        return $limit;
    }

    /**
     * Gets the headers for the rate limit.
     *
     * @param RateLimit $limit The rate limit.
     * @return array The headers.
     */
    protected function getHeaders(RateLimit $limit): array
    {
        return [
            'X-RateLimit-Limit' => $limit->getLimit(),
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
        ];
    }

    /**
     * Enforces the rate limit.
     *
     * @param RateLimit $limit The rate limit.
     */
    protected function enforceLimit(RateLimit $limit): void
    {
        if ($limit->isAccepted()) {
            return;
        }

        $headers = $this->getHeaders($limit);
        $headers['Retry-After'] = $limit->getRetryAfter()->getTimestamp() - time();
        $headers['X-RateLimit-Reset'] = $limit->getRetryAfter()->getTimestamp();

        throw new TooManyRequestsException(headers: $headers);
    }
}
