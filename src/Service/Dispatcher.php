<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Service;

use Closure;
use Derafu\Http\Contract\DispatcherInterface;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Exception\DispatcherException;
use Derafu\Http\Response;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouteMatchInterface;
use Invoker\InvokerInterface;

/**
 * Dispatches HTTP requests to appropriate handlers.
 *
 * This dispatcher supports:
 *
 *   - Controller classes with action methods.
 *   - Closure handlers.
 *   - Template/view files.
 *   - Automatic dependency injection.
 *   - Route parameters resolution.
 *   - HTTP request injection.
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * Creates a new HTTP dispatcher.
     *
     * @param InvokerInterface $invoker The PHP-DI invoker.
     * @param RendererInterface $renderer The renderer service.
     */
    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(
        RouteMatchInterface $match,
        RequestInterface $request,
        array $context = []
    ): mixed {
        return $this->callHandler($match->getHandler(), [
            // Core objects.
            RequestInterface::class => $request,
            RouteMatchInterface::class => $match,

            // Named parameters from route.
            ...$match->getParameters(),

            // Request and route for direct access.
            'request' => $request,
            'route' => $match,

            // Original parameters array if needed.
            'params' => $match->getParameters(),

            // Context array if needed.
            'context' => $context,
        ]);
    }

    /**
     * Calls the appropriate handler based on its type.
     *
     * @param mixed $handler The route handler.
     * @param array<string,mixed> $parameters The execution parameters.
     * @return mixed The handler's response.
     * @throws DispatcherException If the handler type is not supported.
     */
    private function callHandler(mixed $handler, array $parameters): mixed
    {
        if (is_string($handler)) {
            // Handle template files.
            if (file_exists($handler)) {
                return $this->renderer->render($handler, $parameters);
            }

            // Handle controller::action strings.
            if (str_contains($handler, '::')) {
                return $this->invoker->call($handler, $parameters);
            }

            // Handle redirect strings.
            if (str_starts_with($handler, 'redirect:')) {
                return new Response(
                    status: 302,
                    headers: [
                        'Location' => substr($handler, 9),
                    ],
                );
            }

            // Error in the string handler.
            throw new DispatcherException(sprintf(
                'Handler of type string %s is invalid.',
                $handler
            ));
        }

        // Handle closures.
        if ($handler instanceof Closure) {
            return $this->invoker->call($handler, $parameters);
        }

        // Handler type not supported.
        throw new DispatcherException(sprintf(
            'Unsupported handler type: %s.',
            get_debug_type($handler)
        ));
    }
}
