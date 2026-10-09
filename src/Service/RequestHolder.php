<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Service;

use Derafu\Http\Contract\RequestInterface;

/**
 * Holds the request that the application is handling, for what is outside the
 * pipeline and needs to know about it: the `app` variable of the templates.
 *
 * It is the request of the pipeline (`RequestFactoryMiddleware` gives it and
 * takes it back when the request ends, also when it ends with an exception). The
 * request is the same object for the whole pipeline, so what the middlewares add
 * to it (the route, the session, the user) is there when it is read, and the
 * error pages, that are rendered inside the pipeline, see it too.
 */
final class RequestHolder
{
    private ?RequestInterface $request = null;

    /**
     * Gives the request that is being handled, or null when it ends.
     */
    public function set(?RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * The request that is being handled, or null if none is.
     */
    public function get(): ?RequestInterface
    {
        return $this->request;
    }
}
