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
use Derafu\Http\Contract\RequestStackInterface;

/**
 * {@inheritDoc}
 */
final class RequestStack implements RequestStackInterface
{
    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    /**
     * {@inheritDoc}
     */
    public function push(RequestInterface $request): void
    {
        $this->requests[] = $request;
    }

    /**
     * {@inheritDoc}
     */
    public function pop(): ?RequestInterface
    {
        return array_pop($this->requests);
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentRequest(): ?RequestInterface
    {
        return $this->requests === [] ? null : $this->requests[array_key_last($this->requests)];
    }
}
