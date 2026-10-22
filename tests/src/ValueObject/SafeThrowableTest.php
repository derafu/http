<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\ValueObject;

use Derafu\Http\ValueObject\SafeThrowable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A throwable that is safe to show: it has what a throwable has, as plain data,
 * and its trace does not carry the arguments unless they are asked for.
 */
#[CoversClass(SafeThrowable::class)]
final class SafeThrowableTest extends TestCase
{
    private function throwable(?SafeThrowable $previous = null): SafeThrowable
    {
        return new SafeThrowable(
            'RuntimeException',
            12,
            'Something broke.',
            'project_dir:src/App.php',
            34,
            [
                ['file' => 'project_dir:src/App.php', 'line' => 34, 'function' => 'run', 'class' => 'App', 'type' => '->', 'args' => ['secret', 5]],
                ['function' => 'main', 'args' => []],
                ['function' => '{closure}'],
            ],
            $previous
        );
    }

    public function testItHasTheDataOfTheThrowable(): void
    {
        $previous = new SafeThrowable('LogicException', 0, 'Cause.', 'project_dir:src/B.php', 1, [], null);
        $throwable = $this->throwable($previous);

        $this->assertSame('RuntimeException', $throwable->getClass());
        $this->assertSame(12, $throwable->getCode());
        $this->assertSame('Something broke.', $throwable->getMessage());
        $this->assertSame('project_dir:src/App.php', $throwable->getFile());
        $this->assertSame(34, $throwable->getLine());
        $this->assertSame($previous, $throwable->getPrevious());
    }

    public function testTheTraceHasNoArgumentsByDefault(): void
    {
        $trace = $this->throwable()->getTrace();

        $this->assertSame([], $trace[0]['args'] ?? null);
        $this->assertSame('run', $trace[0]['function'] ?? null);
        $this->assertArrayNotHasKey('args', $trace[2]);
    }

    public function testTheTraceHasTheArgumentsWhenTheyAreAskedFor(): void
    {
        $trace = $this->throwable()->getTrace(true);

        $this->assertSame(['secret', 5], $trace[0]['args'] ?? null);
    }

    public function testTheTraceAsStringHasOneLineForEachFrame(): void
    {
        $this->assertSame(
            "#0 project_dir:src/App.php(34): App->run()\n#1 [internal](?): main()\n#2 [internal](?): {closure}()",
            $this->throwable()->getTraceAsString()
        );
    }

    public function testTheTraceAsStringHasTheTypesOfTheArgumentsNotTheirValues(): void
    {
        $this->assertStringContainsString(
            'App->run(string, integer)',
            $this->throwable()->getTraceAsString(true)
        );
        $this->assertStringNotContainsString('secret', $this->throwable()->getTraceAsString(true));
    }

    public function testTheTextHasTheDataAndTheCause(): void
    {
        $previous = new SafeThrowable('LogicException', 0, 'Cause.', 'project_dir:src/B.php', 1, [], null);

        $text = (string) $this->throwable($previous);

        $this->assertStringContainsString("Class: RuntimeException\nMessage: Something broke.\nCode: 12\n", $text);
        $this->assertStringContainsString("File: project_dir:src/App.php (34)\n", $text);
        $this->assertStringContainsString("Stack trace:\n#0 project_dir:src/App.php(34)", $text);
        $this->assertStringContainsString("\nCaused by:\nClass: LogicException\nMessage: Cause.", $text);
    }

    public function testTheTextWithoutCauseHasNoCausedBy(): void
    {
        $this->assertStringNotContainsString('Caused by', (string) $this->throwable());
    }

    public function testItIsSerializedToJsonWithoutTheArguments(): void
    {
        $previous = new SafeThrowable('LogicException', 0, 'Cause.', 'project_dir:src/B.php', 1, [], null);

        $data = json_decode((string) json_encode($this->throwable($previous)), true);

        $this->assertIsArray($data);
        $this->assertSame('RuntimeException', $data['class']);
        $this->assertSame(12, $data['code']);
        $this->assertSame('Something broke.', $data['message']);
        $this->assertSame(34, $data['line']);
        $this->assertIsArray($data['trace']);
        $this->assertIsArray($data['trace'][0]);
        $this->assertSame([], $data['trace'][0]['args']);
        $this->assertIsArray($data['previous']);
        $this->assertSame('LogicException', $data['previous']['class']);
        $this->assertNull($data['previous']['previous']);
    }
}
