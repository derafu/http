<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Fixture;

use Derafu\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A handler that answers an empty response and counts how many requests reached
 * it.
 */
final class CountingHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->calls++;

        return new Response();
    }
}
