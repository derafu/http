<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Controller;

use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Example "normal" controller (no API controller).
 */
class DocsController
{
    public function __construct(private readonly RendererInterface $renderer)
    {
    }

    // You can inject the $renderer only if the service is public.
    // public function index(RendererInterface $renderer, Request $request)
    public function index(Request $request)
    {
        $readme = (string) $request->query('readme', '');

        $file = match($readme) {
            'markdown' => __DIR__ . '/../../vendor/derafu/markdown/README.md',
            'routing' => __DIR__ . '/../../vendor/derafu/routing/README.md',
            default => __DIR__ . '/../../README.md',
        };

        return $this->renderer->render($file);
    }
}
