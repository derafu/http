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

use Derafu\Http\Contract\SafeThrowableFactoryInterface;
use Derafu\Http\Contract\SafeThrowableInterface;
use Derafu\Http\ValueObject\SafeThrowable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

/**
 * Factory for creating safe instances representing a throwable instance.
 */
class SafeThrowableFactory implements SafeThrowableFactoryInterface
{
    /**
     * Creates a new SafeThrowable factory.
     *
     * @param ParameterBagInterface $params For accessing environment settings.
     */
    public function __construct(
        private readonly ParameterBagInterface $params
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(Throwable $throwable): SafeThrowableInterface
    {
        return new SafeThrowable(
            class: get_class($throwable),
            code: $throwable->getCode(),
            message: $throwable->getMessage(),
            file: $this->obfuscatePath($throwable->getFile()),
            line: $throwable->getLine(),
            trace: array_map(function ($frame) {
                if (isset($frame['file'])) {
                    $frame['file'] = $this->obfuscatePath($frame['file']);
                }
                return $frame;
            }, $throwable->getTrace()),
            previous: $throwable->getPrevious()
                ? $this->create($throwable->getPrevious())
                : null
        );
    }

    /**
     * Obfuscates the project directory into a file path.
     *
     * @param string $path
     * @return string
     */
    private function obfuscatePath(string $path): string
    {
        $projectDir = $this->params->get('kernel.project_dir');

        return str_replace(
            $projectDir . '/',
            'project_dir:',
            $path
        );
    }
}
