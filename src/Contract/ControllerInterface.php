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
 * Interface for HTTP controllers.
 *
 * It is a marker: `ControllerConfigurationCompilerPass` reads the `#[Route]`
 * attributes of the services that implement it, and makes them routes. A
 * controller needs nothing else (no base class): it asks the container for what
 * it uses, and a template has the `app` variable without the controller passing
 * it (see `Derafu\Http\Twig\AppVariable`).
 */
interface ControllerInterface
{
}
