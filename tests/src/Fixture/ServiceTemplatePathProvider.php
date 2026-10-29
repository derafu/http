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

use Derafu\Twig\Contract\TemplatePathProviderInterface;

/**
 * Contributes only the test application's templates to the real shared renderer.
 */
final class ServiceTemplatePathProvider implements TemplatePathProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function getPaths(): array
    {
        return [dirname(__DIR__, 2) . '/fixtures/services'];
    }
}
