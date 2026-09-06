<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Service;

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Exception\DispatcherException;
use Derafu\Http\Service\Dispatcher;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouteMatchInterface;
use Invoker\InvokerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * `DispatcherException` is translatable (`TranslatableLogicException`), so
 * these lock in that its message is built via the `[message, ...params]`
 * form — not `sprintf()`, which would bake a fixed, non-translatable string
 * into the exception before it ever reaches the translator.
 */
#[CoversClass(Dispatcher::class)]
class DispatcherTest extends TestCase
{
    private function dispatch(mixed $handler): mixed
    {
        $dispatcher = new Dispatcher(
            $this->createStub(InvokerInterface::class),
            $this->createStub(RendererInterface::class),
        );

        $match = $this->createStub(RouteMatchInterface::class);
        $match->method('getHandler')->willReturn($handler);
        $match->method('getParameters')->willReturn([]);

        $request = $this->createStub(RequestInterface::class);

        return $dispatcher->dispatch($match, $request);
    }

    public function testThrowsWithTheInvalidHandlerStringInTheMessageForAnUnrecognizedStringHandler(): void
    {
        $this->expectException(DispatcherException::class);
        $this->expectExceptionMessageMatches('/not-a-valid-handler/');

        $this->dispatch('not-a-valid-handler');
    }

    public function testThrowsWithTheHandlerTypeInTheMessageForAnUnsupportedHandlerType(): void
    {
        $this->expectException(DispatcherException::class);
        $this->expectExceptionMessageMatches('/array/');

        $this->dispatch(['not', 'a', 'valid', 'handler']);
    }
}
