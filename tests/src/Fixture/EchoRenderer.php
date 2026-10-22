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
 * A renderer that says what it was asked to render: the template and the names
 * of the data that it got, as JSON.
 */
final class EchoRenderer implements RendererInterface
{
    public function render(string $template, array $data = [], array $options = []): string
    {
        return (string) json_encode(['template' => $template, 'data' => array_keys($data)]);
    }

    public function renderFromString(string $content, array $data = [], array $options = []): string
    {
        throw new LogicException('The renderer is only used with templates.');
    }

    public function getEngine(string $name): EngineInterface
    {
        throw new LogicException('The renderer has no engines.');
    }

    public function addEngine(string $name, EngineInterface $engine): static
    {
        throw new LogicException('The renderer has no engines.');
    }
}
