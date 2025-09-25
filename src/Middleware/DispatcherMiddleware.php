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

use Derafu\Http\Contract\DispatcherInterface;
use Derafu\Http\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dispatches the request to the appropriate handler.
 *
 * This middleware is responsible for:
 *
 *   - Executing the matched route's handler (controller, closure, or template).
 *   - Storing the handler's response for downstream middlewares.
 *
 * This middleware depends on having the matched route, so it should be executed
 * after RouterMiddleware.
 */
class DispatcherMiddleware implements MiddlewareInterface
{
    /**
     * The attribute name used to store the handler response.
     */
    public const RESPONSE_ATTRIBUTE = 'derafu.response';

    /**
     * Creates a new dispatcher middleware.
     */
    public function __construct(
        private readonly DispatcherInterface $dispatcher
    ) {
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
        // Dispatch the request to its handler.
        assert($request instanceof RequestInterface);
        $route = $request->route();
        $response = $this->dispatcher->dispatch($route, $request);

        // Store response and continue.
        return $handler->handle(
            $request->withAttribute(self::RESPONSE_ATTRIBUTE, $response)
        );
    }
}
