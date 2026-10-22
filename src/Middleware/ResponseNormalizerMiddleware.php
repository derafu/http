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

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Contract\ResponseNormalizerInterface;
use Derafu\Http\Service\ResponseNormalizer;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Normalizes responses to ensure PSR-7 compliance.
 *
 * This middleware is responsible for:
 *   - Ensuring all responses are PSR-7 compliant
 *   - Adding appropriate Content-Type headers if missing
 *   - Converting non-Response objects to Response instances
 *
 * This should be one of the last middlewares in the stack as it finalizes
 * the response before sending it back to the client.
 */
class ResponseNormalizerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseNormalizerInterface $normalizer = new ResponseNormalizer()
    ) {
    }

    /**
     * Processes an incoming server request.
     *
     * @param PsrRequestInterface $request The PSR-7 request
     * @param RequestHandlerInterface $handler The request handler
     * @return PsrResponseInterface The response
     */
    public function process(
        PsrRequestInterface $request,
        RequestHandlerInterface $handler
    ): PsrResponseInterface {
        // Remove X-Powered-By header.
        header_remove('X-Powered-By');

        // Use the response stored by the dispatcher. It is checked by the key
        // and not by its value because a handler can legitimately return null.
        //
        // The next handler is only asked for a response when none was stored.
        // Asking it anyway would make the request handler (when this is the
        // last middleware) throw on purpose and build a problem that is then
        // discarded, on every successful request.
        $attributes = $request->getAttributes();
        $handlerResponse = array_key_exists(
            DispatcherMiddleware::RESPONSE_ATTRIBUTE,
            $attributes
        )
            ? $attributes[DispatcherMiddleware::RESPONSE_ATTRIBUTE]
            : $handler->handle($request);

        // Normalize the response
        assert($request instanceof RequestInterface);
        return $this->normalizeResponse($request, $handlerResponse);
    }

    /**
     * Normalizes handler responses to ensure PSR-7 compliance.
     *
     * This method:
     *
     *   1. Returns ResponseInterface instances unchanged (unless missing
     *      Content-Type).
     *   2. Returns PsrResponseInterface instances unchanged.
     *   3. Determines appropriate Content-Type if not specified.
     *   4. Formats response data according to Content-Type.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @param mixed $response The response data to normalize.
     * @return PsrResponseInterface
     */
    protected function normalizeResponse(
        RequestInterface $request,
        mixed $response
    ): PsrResponseInterface {
        return $this->normalizer->normalize($request, $response);
    }
}
