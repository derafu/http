<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Exception;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Trait\HttpExceptionTrait;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Throwable;

/**
 * Exception for a request that can not be attended because of what the client
 * sent (a field that is missing, a file that is not there).
 *
 * Mapped to 400 Bad Request instead of the generic 500: the failure is in the
 * request, not in the server.
 *
 * It is an invalid argument, so the code that does not know about HTTP (a
 * service that validates what it receives) can throw it and the callers that
 * already catch the invalid argument keep working. That is why it is not a
 * {@see HttpException}: it uses {@see HttpExceptionTrait} instead.
 */
class BadRequestException extends TranslatableInvalidArgumentException implements HttpExceptionInterface
{
    use HttpExceptionTrait;

    /**
     * Creates a new bad request exception.
     *
     * @param string|array<int|string, mixed>|TranslatableInterface $message The exception message:
     *   - string: Will be used as both message and translation key.
     *   - array: First element must be string (message), remaining elements are
     *     parameters.
     *   - TranslatableInterface: Will be used directly.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception
     * chaining.
     * @param array<string,string|list<string>> $headers Additional headers to
     * add to the HTTP response.
     * @param array<string, mixed> $context Additional context of the problem.
     */
    public function __construct(
        string|array|TranslatableInterface $message = 'Bad request.',
        int $code = 0,
        ?Throwable $previous = null,
        array $headers = [],
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->setHttpData($headers, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): HttpStatus
    {
        return HttpStatus::BAD_REQUEST;
    }
}
