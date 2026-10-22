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
 * The requests that are being dispatched, for what is outside the pipeline and
 * needs to know the request that is being handled (the `app` variable of the
 * templates).
 *
 * The dispatcher puts the request in the stack before it calls a handler and
 * takes it out when the handler ends, so the last one is the request of the
 * controller or of the error page that is being rendered, also when an error page
 * is rendered inside another dispatch.
 */
interface RequestStackInterface
{
    /**
     * Puts a request at the top of the stack.
     */
    public function push(RequestInterface $request): void;

    /**
     * Takes the request at the top out of the stack and returns it, or returns
     * `null` if the stack is empty.
     */
    public function pop(): ?RequestInterface;

    /**
     * The request at the top of the stack, or `null` if the stack is empty.
     */
    public function getCurrentRequest(): ?RequestInterface;
}
