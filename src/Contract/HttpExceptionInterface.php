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
use Derafu\Translation\Contract\HeadersAwareInterface;
use Derafu\Translation\Contract\TranslatableInterface;

/**
 * Interface for HTTP Translatables Exceptions.
 *
 * The headers (`getHeaders()`, `setHeaders()`) are `HeadersAwareInterface`, of
 * `derafu/translation`: an exception of a package that does not depend on this
 * one (`derafu/auth`, for example) can carry them too, and `ProblemFactory`
 * reads them from any exception that has them, not only from this interface.
 */
interface HttpExceptionInterface extends TranslatableInterface, HeadersAwareInterface
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
     * @return array<string, mixed>
     */
    public function getContext(): array;
}
