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

use Derafu\Kernel\Contract\KernelInterface as MicroKernelInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Interface of a kernel for HTTP requests.
 */
interface KernelInterface extends MicroKernelInterface, RequestHandlerInterface
{
}
