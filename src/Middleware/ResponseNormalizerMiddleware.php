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
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Response;
use JsonException;
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
        // Get response from previous middleware.
        $response = $handler->handle($request);

        // Get handler response if stored as attribute.
        $handlerResponse = $request->getAttribute(
            DispatcherMiddleware::RESPONSE_ATTRIBUTE,
            $response
        );

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
     *   2. Determines appropriate Content-Type if not specified.
     *   3. Formats response data according to Content-Type.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @param mixed $response The response data to normalize.
     * @return ResponseInterface
     */
    protected function normalizeResponse(
        RequestInterface $request,
        mixed $response
    ): ResponseInterface {
        // If it is already an answer, just check/add Content-Type.
        if ($response instanceof ResponseInterface) {
            if (!$response->hasHeader('Content-Type')) {
                return $response->withContentType(
                    $request->getPreferredContentType()
                );
            }
            return $response;
        }

        // For other types, determine format and create response.
        $format = $request->getPreferredContentType();
        $newResponse = new Response();

        if ($format === ContentType::JSON) {
            try {
                return $newResponse->asJson($response);
            } catch (JsonException $e) {
                return $newResponse->asText($response, ContentType::PLAIN);
            }
        }

        return $newResponse->asText($response, $format);
    }
}
