<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Service;

use Derafu\Http\Contract\DispatcherInterface;
use Derafu\Http\Contract\ProblemFactoryInterface;
use Derafu\Http\Contract\ProblemHandlerInterface;
use Derafu\Http\Contract\RequestFactoryInterface;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Response;
use Derafu\Routing\Contract\RouterInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * HTTP request handler that handles incoming requests.
 *
 * This class serves as the main entry point for handling HTTP requests. It:
 *
 *   - Routes requests to appropriate handlers.
 *   - Dispatches the request to the matched handler.
 *   - Handles errors and exceptions.
 *   - Normalizes responses to ensure PSR-7 compliance.
 *
 * The request handler follows these steps:
 *
 *   1. Matches the request URI to a route.
 *   2. Dispatches the request to the matched handler.
 *   3. Handles any errors that occur during processing.
 *   4. Normalizes the response to ensure it's PSR-7 compliant.
 */
class RequestHandler implements RequestHandlerInterface
{
    /**
     * Creates a new RequestHandler instance.
     *
     * @param RequestFactoryInterface $requestFactory
     * @param RouterInterface $router
     * @param DispatcherInterface $dispatcher
     * @param ProblemFactoryInterface $errorFactory
     * @param ProblemHandlerInterface $errorHandler
     */
    public function __construct(
        private readonly RequestFactoryInterface $requestFactory,
        private readonly RouterInterface $router,
        private readonly DispatcherInterface $dispatcher,
        private readonly ProblemFactoryInterface $errorFactory,
        private readonly ProblemHandlerInterface $errorHandler
    ) {
    }

    /**
     * Handles an incoming HTTP request.
     *
     * This method implements the main request handling flow:
     *
     *   1. Normalizes the request.
     *   2. Attempts to match the request path to a route.
     *   3. Dispatches the request to the matched handler.
     *   4. Handles any errors that occur during processing.
     *   5. Normalizes the response.
     *
     * @param PsrRequestInterface $request The HTTP request.
     * @return ResponseInterface A PSR-7 compliant response.
     */
    public function handle(PsrRequestInterface $request): ResponseInterface
    {
        $request = $this->requestFactory->createFromPsrRequest($request);

        try {
            $route = $this->router->match($request->getUri()->getPath());
            $response = $this->dispatcher->dispatch($route, $request);
        } catch (Throwable $e) {
            $error = $this->errorFactory->create($e, $request);
            $response = $this->errorHandler->handle($error);
        }

        return $this->normalizeResponse($request, $response);
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
            return $newResponse->asJson($response);
        }

        return $newResponse->asText($response, $format);
    }
}
