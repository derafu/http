<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Fixture\Controller;

use Derafu\Routing\Attribute\Route;

/**
 * A class with a route that is not a controller: its routes are not read.
 */
final class NotAController
{
    #[Route(path: '/not-read', name: 'not_read')]
    public function index(): string
    {
        return 'no';
    }
}
