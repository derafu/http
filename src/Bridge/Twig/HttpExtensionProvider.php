<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Bridge\Twig;

use Derafu\Http\Twig\AppExtension;
use Derafu\Http\Twig\AppVariable;
use Derafu\Twig\Contract\ExtensionProviderInterface;

/**
 * Contributes HTTP's app variable to the shared Twig environment.
 *
 * The variable reads the same request stack used by the HTTP dispatcher.
 * This provider neither constructs an environment nor owns request state.
 */
final class HttpExtensionProvider implements ExtensionProviderInterface
{
    /**
     * @param AppVariable $app Shared view of the dispatcher's request stack.
     */
    public function __construct(private readonly AppVariable $app)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensions(array $options = []): iterable
    {
        yield new AppExtension($this->app);
    }
}
