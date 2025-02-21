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

use Throwable;

/**
 * Interface for factory of HTTP errors.
 */
interface ProblemFactoryInterface
{
    /**
     * Create a new error (as a ProblemDetail) from a throwable instance and
     * the related HTTP request.
     *
     * @param Throwable $throwable
     * @param RequestInterface $request
     * @return ProblemDetailInterface
     */
    public function create(
        Throwable $throwable,
        RequestInterface $request
    ): ProblemDetailInterface;
}
