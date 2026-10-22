<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Service;

use Derafu\Http\Request;
use Derafu\Http\Service\RequestStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The request at the top of the stack is the one that is being dispatched.
 */
#[CoversClass(RequestStack::class)]
#[UsesClass(Request::class)]
final class RequestStackTest extends TestCase
{
    public function testAnEmptyStackHasNoRequest(): void
    {
        $stack = new RequestStack();

        $this->assertNull($stack->getCurrentRequest());
        $this->assertNull($stack->pop());
    }

    public function testTheCurrentRequestIsTheLastOneThatWasPushed(): void
    {
        $stack = new RequestStack();
        $first = new Request('GET', 'http://localhost/first');
        $second = new Request('GET', 'http://localhost/second');

        $stack->push($first);
        $this->assertSame($first, $stack->getCurrentRequest());

        $stack->push($second);
        $this->assertSame($second, $stack->getCurrentRequest());
    }

    public function testPoppingGivesBackTheCurrentRequestAndTheOneBelow(): void
    {
        $stack = new RequestStack();
        $first = new Request('GET', 'http://localhost/first');
        $second = new Request('GET', 'http://localhost/second');
        $stack->push($first);
        $stack->push($second);

        $this->assertSame($second, $stack->pop());
        $this->assertSame($first, $stack->getCurrentRequest());
        $this->assertSame($first, $stack->pop());
        $this->assertNull($stack->getCurrentRequest());
    }
}
