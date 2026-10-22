<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Factory;

use Derafu\Http\Contract\RequestFactoryInterface;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Request;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;

/**
 * Factory for create the internal Request (PSR extended request).
 */
class RequestFactory implements RequestFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createFromPsrRequest(
        PsrRequestInterface $request
    ): RequestInterface {
        // The name of a header that is only digits is an int key of the array.
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[(string) $name] = array_values($values);
        }

        $newRequest = new Request(
            $request->getMethod(),
            $request->getUri(),
            $headers,
            $request->getBody(),
            $request->getProtocolVersion(),
            $request->getServerParams()
        );

        $newRequest = $newRequest
            ->withParsedBody($request->getParsedBody())
            ->withQueryParams($request->getQueryParams())
            ->withUploadedFiles($request->getUploadedFiles())
            ->withCookieParams($request->getCookieParams())
        ;

        // Copy attributes one by one.
        foreach ($request->getAttributes() as $attribute => $value) {
            $newRequest = $newRequest->withAttribute($attribute, $value);
        }

        return $newRequest;
    }
}
