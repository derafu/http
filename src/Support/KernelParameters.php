<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Support;

use Derafu\Array\ArrayReader;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * The parameters of the kernel that the services read, with the type that they
 * must have: a parameter of another type is a configuration that is wrong, and
 * it is not used.
 */
final class KernelParameters
{
    /**
     * A parameter that is a string.
     *
     * @throws InvalidArgumentException If the parameter is not a string.
     */
    public static function string(ParameterBagInterface $params, string $name): string
    {
        return self::reader($params, $name)->requireString($name);
    }

    /**
     * A parameter that is true or false.
     *
     * @throws InvalidArgumentException If the parameter is not a boolean.
     */
    public static function bool(ParameterBagInterface $params, string $name): bool
    {
        return self::reader($params, $name)->requireBool($name);
    }

    /**
     * A parameter that is an array.
     *
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException If the parameter is not an array.
     */
    public static function array(ParameterBagInterface $params, string $name): array
    {
        return self::reader($params, $name)->requireArray($name);
    }

    /**
     * The reader of a parameter, whose value is the one that the bag resolves.
     */
    private static function reader(ParameterBagInterface $params, string $name): ArrayReader
    {
        return ArrayReader::fromArray([$name => $params->get($name)]);
    }
}
