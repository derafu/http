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

use Derafu\Http\Contract\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Converts PSR-7 server requests into Derafu request objects.
 *
 * This middleware is responsible for:
 *
 *   - Converting PSR-7 ServerRequestInterface into Derafu's custom Request.
 *   - Pass the custom request as the request argument for downstream middlewares.
 *
 * This should be one of the first middlewares in the stack as other
 * middlewares might depend on having access to the Derafu request object.
 */
class RequestFactoryMiddleware implements MiddlewareInterface
{
    /**
     * Creates a new request factory middleware.
     */
    public function __construct(
        private readonly RequestFactoryInterface $requestFactory
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
        $customRequest = $this->requestFactory->createFromPsrRequest($request);

        return $handler->handle($customRequest);
    }
}
