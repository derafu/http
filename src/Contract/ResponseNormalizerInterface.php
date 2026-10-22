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

use Derafu\Http\Enum\ContentType;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * Turns what a handler returns (a controller, the page of an error) into a
 * PSR-7 response, in the format that the request prefers.
 */
interface ResponseNormalizerInterface
{
    /**
     * Normalizes the value that a handler returned.
     *
     * @param RequestInterface $request The request, which says the format.
     * @param mixed $result What the handler returned.
     * @param ContentType|null $format The format of the response, for what is
     * always of a format whatever the request prefers (the page of an error is
     * HTML); `null` is the format that the request prefers.
     * @return PsrResponseInterface
     */
    public function normalize(
        RequestInterface $request,
        mixed $result,
        ?ContentType $format = null
    ): PsrResponseInterface;
}
