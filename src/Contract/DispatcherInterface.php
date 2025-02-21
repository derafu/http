<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Contract;

use Derafu\Http\Exception\DispatcherException;
use Derafu\Routing\Contract\RouteMatchInterface;

/**
 * Interface for HTTP dispatchers that handle route matches and requests.
 */
interface DispatcherInterface
{
    /**
     * Dispatches a route match with an HTTP request.
     *
     * @param RouteMatchInterface $match The matched route.
     * @param RequestInterface $request The HTTP request.
     * @param array $context Additional context associated with the HTTP request.
     * @return mixed The response.
     * @throws DispatcherException If the handler cannot be dispatched.
     */
    public function dispatch(
        RouteMatchInterface $match,
        RequestInterface $request,
        array $context = []
    ): mixed;
}
