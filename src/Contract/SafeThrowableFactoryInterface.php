<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Contract;

use Throwable;

interface SafeThrowableFactoryInterface
{
    /**
     * Creates a new SafeThrowable from a regular Throwable.
     *
     * @param Throwable $throwable
     * @return SafeThrowableInterface
     */
    public function create(Throwable $throwable): SafeThrowableInterface;
}
