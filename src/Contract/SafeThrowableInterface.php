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

use JsonSerializable;
use Stringable;

interface SafeThrowableInterface extends Stringable, JsonSerializable
{
    /**
     * Gets the throwable class name.
     *
     * @return string
     */
    public function getClass(): string;

    /**
     * Gets the original code for this error.
     *
     * @return int
     */
    public function getCode(): int;

    /**
     * Gets the error message.
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Gets the file where the error occurred.
     *
     * @return string
     */
    public function getFile(): string;

    /**
     * Gets the line where the error occurred.
     *
     * @return integer
     */
    public function getLine(): int;

    /**
     * Gets the stack trace.
     *
     * @return array<int,mixed>
     */
    public function getTrace(): array;

    /**
     * Gets the stack trace as a string.
     *
     * @return string
     */
    public function getTraceAsString(): string;

    /**
     * Gets the previous error if any.
     *
     * @return SafeThrowableInterface|null
     */
    public function getPrevious(): ?SafeThrowableInterface;
}
