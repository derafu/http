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

use Derafu\Http\Contract\RequestInterface;

/**
 * A controller whose action gets a parameter of the route and the request.
 */
final class ActionController
{
    public function show(string $id, RequestInterface $request): string
    {
        return sprintf('post %s at %s', $id, $request->getUri()->getPath());
    }
}
