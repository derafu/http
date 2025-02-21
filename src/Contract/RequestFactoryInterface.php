<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Contract;

use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;

interface RequestFactoryInterface
{
    /**
     * Creates a new Request instance from a PSR-7 ServerRequest.
     *
     * This method allows converting any PSR-7 ServerRequest into our extended
     * Request implementation, maintaining all the original request data
     * including:
     *
     *   - URI and method.
     *   - Headers and attributes.
     *   - Query parameters and body.
     *   - Uploaded files.
     *
     * @param PsrRequestInterface $request The PSR-7 request to convert.
     * @return RequestInterface A new Request instance with all data from the
     * original request.
     */
    public function createFromPsrRequest(
        PsrRequestInterface $request
    ): RequestInterface;
}
