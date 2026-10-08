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

use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Exception\InvalidPathException;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\Support\Url;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handles request routing using the router service.
 *
 * This middleware is responsible for:
 *
 *   - Giving the request the canonical form of its path (see
 *     `Derafu\Support\Url::normalizePath()`): `/api//index`, `/api/./index` and
 *     `/api/%69ndex` are `/api/index`. Every middleware after this one, and the
 *     handler of the route, sees that path, so a rule that decides by the text
 *     of the path (the protected paths of `derafu/auth`) and the one that
 *     reads its meaning (the route) are about the same path. A path that has
 *     no safe form (it climbs a directory, it has an escaped separator, a
 *     control character...) is refused with an `InvalidPathException` (a 400).
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
        // The path that everything after this middleware sees is the canonical
        // one. A path that has no safe form is not served.
        $path = $request->getUri()->getPath();
        $canonical = Url::normalizePath($path) ?? throw new InvalidPathException($path);
        if ($canonical !== $path) {
            $request = $request->withUri($request->getUri()->withPath($canonical));
        }

        // Create a request context from the current request and set it on the
        // router.
        $context = RequestContext::fromRequest($request);
        $this->router->setContext($context);

        // Match route for the request path.
        $route = $this->router->match($canonical);

        // Store route and continue.
        return $handler->handle(
            $request->withAttribute(self::ROUTE_ATTRIBUTE, $route)
        );
    }
}
