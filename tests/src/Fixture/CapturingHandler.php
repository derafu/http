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
 * A handler that keeps the request that reached it and answers an empty
 * response.
 */
final class CapturingHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response();
    }
}
