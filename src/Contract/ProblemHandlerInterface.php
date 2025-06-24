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

/**
 * Interface for handling application errors and generating error responses.
 *
 * Implementations should:
 *
 *   - Generate appropriate error responses based on the error type.
 *   - Handle different environments (dev/prod).
 *   - Provide meaningful error information while maintaining security.
 *   - Support custom error pages.
 */
interface ProblemHandlerInterface
{
    /**
     * Handles an error and generates an appropriate response.
     *
     * @param ProblemDetailInterface $error The error with the full context.
     * @return ResponseInterface The error response.
     */
    public function handle(ProblemDetailInterface $error): ResponseInterface;
}
