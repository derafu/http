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

use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Renderer\Contract\RendererInterface;
use LogicException;

/**
 * A renderer for the tests of what has nothing to render: if it is used, the
 * test is wrong.
 */
final class UnusedRenderer implements RendererInterface
{
    public function render(string $template, array $data = [], array $options = []): string
    {
        throw new LogicException('The renderer must not be used.');
    }

    public function renderFromString(string $content, array $data = [], array $options = []): string
    {
        throw new LogicException('The renderer must not be used.');
    }

    public function getEngine(string $name): EngineInterface
    {
        throw new LogicException('The renderer must not be used.');
    }

    public function addEngine(string $name, EngineInterface $engine): static
    {
        throw new LogicException('The renderer must not be used.');
    }
}
