<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Gives the templates the variable `app` (see `AppVariable`), the same one in all
 * of them, with no need for a controller to pass it.
 */
final class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly AppVariable $app)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getGlobals(): array
    {
        return ['app' => $this->app];
    }
}
