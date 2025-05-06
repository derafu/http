<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Middleware;

use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\ValueObject\RequestContext;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handles request routing using the router service.
 *
 * This middleware is responsible for:
 *
 *   - Creating a request context from the current request.
 *   - Matching the request path to a route.
 *   - Storing the matched route for downstream middlewares.
 *   - Handling routing errors through the problem handler.
 */
class RouterMiddleware implements MiddlewareInterface
{
    /**
     * The attribute name used to store the matched route.
     */
    public const ROUTE_ATTRIBUTE = 'derafu.route';

    /**
     * Creates a new router middleware.
     */
    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * Processes an incoming server request.
     *
     * @param PsrRequestInterface $request The PSR-7 request.
     * @param RequestHandlerInterface $handler The request handler.
     * @return PsrResponseInterface The response.
     */
    public function process(
        PsrRequestInterface $request,
        RequestHandlerInterface $handler
    ): PsrResponseInterface {
        // Create a request context from the current request and set it on the
        // router.
        $context = RequestContext::fromRequest($request);
        $this->router->setContext($context);

        // Match route for the request path.
        $route = $this->router->match($request->getUri()->getPath());

        // Store route and continue.
        return $handler->handle(
            $request->withAttribute(self::ROUTE_ATTRIBUTE, $route)
        );
    }
}
