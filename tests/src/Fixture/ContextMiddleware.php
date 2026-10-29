<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Fixture;

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Middleware\ClientIpMiddleware;
use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Routing\Contract\RouteMatchInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Exposes the request context seen at a consumer-selected pipeline position.
 */
final class ContextMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $phase)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute(RouterMiddleware::ROUTE_ATTRIBUTE);
        $client = $request->getAttribute(ClientIpMiddleware::ATTRIBUTE);

        return $handler->handle($request)
            ->withHeader('X-' . $this->phase . '-Request', $request instanceof RequestInterface ? 'derafu' : 'psr')
            ->withHeader('X-' . $this->phase . '-Route', $route instanceof RouteMatchInterface ? $route->getName() : 'none')
            ->withHeader('X-' . $this->phase . '-Client', is_string($client) ? $client : 'missing');
    }
}
