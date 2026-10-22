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

use Derafu\Http\Factory\RequestFactory;
use Derafu\Http\Request;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The request of Derafu has what the PSR-7 request has.
 */
#[CoversClass(RequestFactory::class)]
#[UsesClass(Request::class)]
final class RequestFactoryTest extends TestCase
{
    public function testTheRequestHasTheDataOfThePsrRequest(): void
    {
        $psr = (new ServerRequest('POST', 'http://localhost/users?x=1', ['Accept' => 'text/html'], 'body', '1.1', ['REMOTE_ADDR' => '10.0.0.1']))
            ->withParsedBody(['name' => 'Ana'])
            ->withQueryParams(['x' => '1'])
            ->withCookieParams(['sid' => 'abc'])
            ->withAttribute('user', 'ana');

        $request = (new RequestFactory())->createFromPsrRequest($psr);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('http://localhost/users?x=1', (string) $request->getUri());
        $this->assertSame('text/html', $request->getHeaderLine('Accept'));
        $this->assertSame('body', (string) $request->getBody());
        $this->assertSame(['REMOTE_ADDR' => '10.0.0.1'], $request->getServerParams());
        $this->assertSame(['name' => 'Ana'], $request->getParsedBody());
        $this->assertSame(['x' => '1'], $request->getQueryParams());
        $this->assertSame(['sid' => 'abc'], $request->getCookieParams());
        $this->assertSame('ana', $request->getAttribute('user'));
    }

    public function testAHeaderWithSeveralValuesKeepsThem(): void
    {
        $psr = new ServerRequest('GET', 'http://localhost/', ['Accept' => ['text/html', 'application/json']]);

        $request = (new RequestFactory())->createFromPsrRequest($psr);

        $this->assertSame(['text/html', 'application/json'], $request->getHeader('Accept'));
    }

    public function testAHeaderWhoseNameIsOnlyDigitsIsKept(): void
    {
        $psr = new ServerRequest('GET', 'http://localhost/', ['123' => 'x']);

        $request = (new RequestFactory())->createFromPsrRequest($psr);

        $this->assertSame(['x'], $request->getHeader('123'));
    }
}
