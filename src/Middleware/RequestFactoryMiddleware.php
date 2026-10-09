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
use Derafu\Http\Service\RequestHolder;
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
 *   - Give it to the `RequestHolder` while the request is handled (and take it
 *     back at the end, also with an exception), so what is outside the pipeline
 *     (the `app` variable of the templates) knows the request.
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
        private readonly ParameterBagInterface $parameterBag,
        private readonly RequestHolder $holder
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

        // The request is the same object for the whole pipeline: what the next
        // middlewares add to it (route, session, user) is seen through the holder.
        // The error pages are rendered inside the pipeline, so they see it too.
        $this->holder->set($customRequest);

        try {
            return $handler->handle($customRequest);
        } finally {
            $this->holder->set(null);
        }
    }
}
