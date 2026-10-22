<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Factory;

use Derafu\Http\Factory\SafeThrowableFactory;
use Derafu\Http\Support\KernelParameters;
use Derafu\Http\ValueObject\SafeThrowable;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * A throwable is safe to show when the path of the project is not in it, its
 * code is a number and its causes are safe too.
 */
#[CoversClass(SafeThrowableFactory::class)]
#[UsesClass(SafeThrowable::class)]
#[UsesClass(KernelParameters::class)]
final class SafeThrowableFactoryTest extends TestCase
{
    private function factory(): SafeThrowableFactory
    {
        return new SafeThrowableFactory(new ParameterBag(['kernel.project_dir' => dirname(__DIR__, 3)]));
    }

    public function testItHasTheClassTheMessageTheCodeAndTheLine(): void
    {
        $line = __LINE__ + 1;
        $exception = new RuntimeException('Something broke.', 7);

        $safe = $this->factory()->create($exception);

        $this->assertSame(RuntimeException::class, $safe->getClass());
        $this->assertSame('Something broke.', $safe->getMessage());
        $this->assertSame(7, $safe->getCode());
        $this->assertSame($line, $safe->getLine());
        $this->assertNull($safe->getPrevious());
    }

    public function testThePathOfTheProjectIsNotInTheFileNorInTheTrace(): void
    {
        $safe = $this->factory()->create(new RuntimeException('Something broke.'));

        $this->assertSame('project_dir:tests/src/Factory/SafeThrowableFactoryTest.php', $safe->getFile());
        $files = array_filter(array_column($safe->getTrace(), 'file'));
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertStringNotContainsString(dirname(__DIR__, 3), $file);
        }
        $this->assertStringContainsString('project_dir:', $safe->getTraceAsString());
    }

    public function testACodeThatIsAStringGoesToTheMessage(): void
    {
        $safe = $this->factory()->create(new PDOException('Connection refused.', 0));
        $this->assertSame(0, $safe->getCode());

        $exception = new PDOException('Access denied.');
        $exception->errorInfo = ['HY000'];
        // PDOException keeps the code of the driver as a string.
        $code = new \ReflectionProperty(\Exception::class, 'code');
        $code->setValue($exception, 'HY000');

        $safe = $this->factory()->create($exception);

        $this->assertSame(0, $safe->getCode());
        $this->assertSame('HY000 - Access denied.', $safe->getMessage());
    }

    public function testACodeThatIsANumericStringIsANumber(): void
    {
        $exception = new PDOException('Duplicate.');
        $code = new \ReflectionProperty(\Exception::class, 'code');
        $code->setValue($exception, '23000');

        $safe = $this->factory()->create($exception);

        $this->assertSame(23000, $safe->getCode());
        $this->assertSame('Duplicate.', $safe->getMessage());
    }

    public function testTheCausesAreSafeToo(): void
    {
        $exception = new RuntimeException('Outer.', 1, new LogicException('Middle.', 2, new InvalidArgumentException('Inner.', 3)));

        $safe = $this->factory()->create($exception);

        $middle = $safe->getPrevious();
        $this->assertInstanceOf(SafeThrowable::class, $middle);
        $this->assertSame('Middle.', $middle->getMessage());
        $this->assertSame(2, $middle->getCode());
        $inner = $middle->getPrevious();
        $this->assertInstanceOf(SafeThrowable::class, $inner);
        $this->assertSame(InvalidArgumentException::class, $inner->getClass());
        $this->assertSame('project_dir:tests/src/Factory/SafeThrowableFactoryTest.php', $inner->getFile());
        $this->assertNull($inner->getPrevious());
    }

    public function testAProjectDirThatIsNotATextIsATranslatableError(): void
    {
        $factory = new SafeThrowableFactory(new ParameterBag(['kernel.project_dir' => ['/app']]));

        $this->expectException(InvalidArgumentException::class);

        try {
            $factory->create(new RuntimeException('Something broke.'));
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(TranslatableInterface::class, $e);
            $this->assertStringContainsString('kernel.project_dir', $e->getMessage());

            throw $e;
        }
    }
}
