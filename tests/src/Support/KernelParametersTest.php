<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Support;

use Derafu\Http\Support\KernelParameters;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Throwable;

/**
 * The parameters of the kernel are read with the type that they must have.
 */
#[CoversClass(KernelParameters::class)]
final class KernelParametersTest extends TestCase
{
    public function testParameterNamesRemainLiteralBagKeys(): void
    {
        $params = new ParameterBag([
            'kernel.environment' => 'prod',
            'kernel' => ['environment' => 'dev'],
        ]);

        $this->assertSame('prod', KernelParameters::string($params, 'kernel.environment'));
    }

    public function testMissingParameterKeepsTheBagsExceptionInsteadOfReadingANestedValue(): void
    {
        $params = new ParameterBag(['kernel' => ['environment' => 'dev']]);
        $this->expectException(ParameterNotFoundException::class);

        KernelParameters::string($params, 'kernel.environment');
    }

    public function testArrayParametersKeepSparseKeysAndObjectValues(): void
    {
        $object = new stdClass();
        $values = [7 => 'first', 'nested' => [3 => $object], 2 => null];
        $params = new ParameterBag(['application.values' => $values]);

        $this->assertSame($values, KernelParameters::array($params, 'application.values'));
        $this->assertSame($object, KernelParameters::array($params, 'application.values')['nested'][3]);
    }

    public function testAStringParameterIsGiven(): void
    {
        $params = new ParameterBag(['kernel.environment' => 'prod']);

        $this->assertSame('prod', KernelParameters::string($params, 'kernel.environment'));
    }

    public function testABooleanParameterIsGiven(): void
    {
        $params = new ParameterBag(['kernel.debug' => false]);

        $this->assertFalse(KernelParameters::bool($params, 'kernel.debug'));
    }

    public function testAParameterThatIsNotAStringIsATranslatableError(): void
    {
        $params = new ParameterBag(['kernel.project_dir' => ['/app']]);

        $exception = null;
        try {
            KernelParameters::string($params, 'kernel.project_dir');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('The key "kernel.project_dir" must be of type string, array given.', $exception->getMessage());
    }

    public function testAParameterThatIsNotABooleanIsATranslatableError(): void
    {
        $params = new ParameterBag(['kernel.debug' => '1']);

        $exception = null;
        try {
            KernelParameters::bool($params, 'kernel.debug');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('The key "kernel.debug" must be of type bool, string given.', $exception->getMessage());
    }

    public function testAnArrayParameterIsGiven(): void
    {
        $params = new ParameterBag(['routes' => ['home' => ['path' => '/']]]);

        $this->assertSame(['home' => ['path' => '/']], KernelParameters::array($params, 'routes'));
    }

    public function testAParameterThatIsNotAnArrayIsATranslatableError(): void
    {
        $params = new ParameterBag(['routes' => 7]);

        $exception = null;
        try {
            KernelParameters::array($params, 'routes');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('The key "routes" must be of type array, int given.', $exception->getMessage());
    }
}
