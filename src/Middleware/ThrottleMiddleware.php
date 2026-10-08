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
 *
 * It counts by the network of the client: put `ClientIpMiddleware` before it in
 * the pipeline, with the proxies that the application trusts (see
 * `getIdentifier()`).
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
     * Gets the identifier for the request: who the limit is counted for.
     *
     * It is the network of the client that `ClientIpMiddleware` decided (with
     * it in the pipeline before this middleware), or the network of the address
     * of the connection if the pipeline does not have it. The headers of the
     * request (`X-Forwarded-For`...) are never read here: they are written by the
     * client, so counting by them would let it choose its own counter.
     *
     * @param ServerRequestInterface $request The request.
     * @return string The identifier.
     */
    protected function getIdentifier(ServerRequestInterface $request): string
    {
        return 'throttle_' . hash('sha256', ClientIpMiddleware::networkOf($request));
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
