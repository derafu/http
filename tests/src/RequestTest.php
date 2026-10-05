<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp;

use Closure;
use Derafu\Http\Request;
use Derafu\Translation\Contract\TranslatableInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * What the middlewares put in the request (the route, the session, the flash
 * messages) is not there when they did not run: asking for it is a
 * translatable error that says which one is missing.
 */
#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /**
     * @return array<string, array{Closure, string}>
     */
    public static function missingProvider(): array
    {
        return [
            'route' => [
                fn (Request $request) => $request->route(),
                'Route match not found. Ensure RouterMiddleware is executed before using the $request->route() method.',
            ],
            'session' => [
                fn (Request $request) => $request->session(),
                'Session not found.',
            ],
            'flash messages' => [
                fn (Request $request) => $request->flash(),
                'Flash messages not found.',
            ],
        ];
    }

    #[DataProvider('missingProvider')]
    public function testSomethingThatIsMissingIsATranslatableError(Closure $ask, string $message): void
    {
        $exception = null;
        try {
            $ask(new Request('GET', 'http://localhost/'));
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }
}
