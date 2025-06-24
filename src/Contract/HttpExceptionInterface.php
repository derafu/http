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

use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Contract\TranslatableInterface;

/**
 * Interface for HTTP Translatables Exceptions.
 */
interface HttpExceptionInterface extends TranslatableInterface
{
    /**
     * A URI reference [RFC3986] that identifies the problem type.
     *
     * @return string
     */
    public function getUriReference(): string;

    /**
     * A short, human-readable summary of the problem type.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * The HTTP status related to the exception.
     *
     * @return HttpStatus
     */
    public function getStatus(): HttpStatus;

    /**
     * Additional context of the exception.
     *
     * @return array
     */
    public function getContext(): array;
}
