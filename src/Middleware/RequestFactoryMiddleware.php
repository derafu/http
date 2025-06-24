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

use Derafu\Http\Contract\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Converts PSR-7 server requests into Derafu request objects.
 *
 * This middleware is responsible for:
 *
 *   - Converting PSR-7 ServerRequestInterface into Derafu's custom Request.
 *   - Adding the request context to the request.
 *   - Pass the custom request as the request argument for downstream middlewares.
 *
 * This should be one of the first middlewares in the stack as other
 * middlewares might depend on having access to the Derafu request object.
 */
class RequestFactoryMiddleware implements MiddlewareInterface
{
    /**
     * The attribute name used to store the request context.
     */
    public const CONTEXT_ATTRIBUTE = 'derafu.context';

    /**
     * Creates a new request factory middleware.
     */
    public function __construct(
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ParameterBagInterface $parameterBag
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
        // Add the request context to the request. This needs to be done before
        // creating the custom request.
        $context = $this->parameterBag->get('kernel.context');
        if (!empty($context)) {
            $request = $request->withAttribute(
                self::CONTEXT_ATTRIBUTE,
                $context
            );
        }

        // Create the custom request.
        $customRequest = $this->requestFactory->createFromPsrRequest($request);

        // Return the custom request.
        return $handler->handle($customRequest);
    }
}
