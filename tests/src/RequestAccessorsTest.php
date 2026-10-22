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

use Derafu\Http\Enum\ContentType;
use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\Route;
use Derafu\Routing\ValueObject\RouteMatch;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\UserInterface;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessages;
use Mezzio\Session\Session;
use Mezzio\Session\SessionMiddleware;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * What a request says about its input (query, form, JSON, files), the format
 * that its client prefers and what the middlewares leave in it.
 */
#[CoversClass(Request::class)]
#[UsesClass(ContentType::class)]
final class RequestAccessorsTest extends TestCase
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    private function request(string $path = '/', array $headers = [], ?string $body = null): Request
    {
        return new Request('POST', 'http://localhost' . $path, $headers, $body);
    }

    public function testTheQueryIsReadByKeyWithADefault(): void
    {
        $request = $this->request()->withQueryParams(['id' => '7']);

        $this->assertSame('7', $request->query('id'));
        $this->assertSame(0, $request->query('page', 0));
        $this->assertNull($request->query('missing'));
    }

    public function testTheFormIsReadByKeyWithADefault(): void
    {
        $request = $this->request()->withParsedBody(['name' => 'Ana']);

        $this->assertSame('Ana', $request->post('name'));
        $this->assertSame('x', $request->post('missing', 'x'));
    }

    public function testWithoutAFormThePostIsTheDefault(): void
    {
        $this->assertSame('x', $this->request()->post('name', 'x'));
        $this->assertSame('x', $this->request()->withParsedBody(null)->post('name', 'x'));
    }

    public function testAHeaderIsItsFirstValueOrTheDefault(): void
    {
        $request = $this->request('/', ['X-Token' => ['abc', 'def']]);

        $this->assertSame('abc', $request->header('x-token'));
        $this->assertSame('none', $request->header('X-Missing', 'none'));
        $this->assertSame('', $request->header('X-Missing'));
    }

    public function testJsonIsToldByTheContentType(): void
    {
        $this->assertTrue($this->request('/', ['Content-Type' => 'application/json; charset=utf-8'])->isJson());
        $this->assertFalse($this->request('/', ['Content-Type' => 'text/plain'])->isJson());
        $this->assertFalse($this->request()->isJson());
    }

    public function testTheJsonBodyIsDecoded(): void
    {
        $request = $this->request('/', ['Content-Type' => 'application/json'], '{"name":"Ana","tags":["a","b"]}');

        $this->assertSame(['name' => 'Ana', 'tags' => ['a', 'b']], $request->json());
    }

    public function testTheJsonIsNullWhenItIsNotJsonOrIsNotValid(): void
    {
        $this->assertNull($this->request('/', [], '{"a":1}')->json());
        $this->assertNull($this->request('/', ['Content-Type' => 'application/json'], '{broken')->json());
        $this->assertNull($this->request('/', ['Content-Type' => 'application/json'], '"text"')->json());
        $this->assertNull($this->request('/', ['Content-Type' => 'application/json'])->json());
    }

    public function testAllHasTheQueryTheFormAndTheJsonWithTheLaterOnesWinning(): void
    {
        $request = $this->request('/', ['Content-Type' => 'application/json'], '{"c":"json","b":"json"}')
            ->withQueryParams(['a' => 'query', 'b' => 'query', 'c' => 'query'])
            ->withParsedBody(['b' => 'form', 'c' => 'form']);

        $this->assertSame(['a' => 'query', 'b' => 'json', 'c' => 'json'], $request->all());
    }

    public function testAllWithoutFormNorJsonIsTheQuery(): void
    {
        $request = $this->request()->withQueryParams(['a' => '1']);

        $this->assertSame(['a' => '1'], $request->all());
    }

    public function testInputAndOnlyReadFromAll(): void
    {
        $request = $this->request()
            ->withQueryParams(['a' => '1'])
            ->withParsedBody(['b' => '2', 'c' => '3']);

        $this->assertSame('2', $request->input('b'));
        $this->assertSame('d', $request->input('missing', 'd'));
        $this->assertSame(['a' => '1', 'c' => '3'], $request->only(['a', 'c', 'missing']));
    }

    public function testAUploadedFileIsOkWhenItHasNoError(): void
    {
        $ok = new UploadedFile('content', 7, UPLOAD_ERR_OK);
        $failed = new UploadedFile('', 0, UPLOAD_ERR_NO_FILE);
        $request = $this->request()->withUploadedFiles(['ok' => $ok, 'failed' => $failed]);

        $this->assertTrue($request->hasFile('ok'));
        $this->assertFalse($request->hasFile('failed'));
        $this->assertFalse($request->hasFile('missing'));
    }

    /**
     * @return array<string, array{string, array<string, string>, ContentType}>
     */
    public static function provideFormats(): array
    {
        return [
            'the extension wins over the header' => ['/data.json', ['Accept' => 'text/html'], ContentType::JSON],
            'a stylesheet' => ['/app.css', [], ContentType::CSS],
            'an api path' => ['/api/users', ['Accept' => 'text/html'], ContentType::JSON],
            'the api root' => ['/api', [], ContentType::JSON],
            'an ajax request' => ['/users', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'], ContentType::JSON],
            'json in the header' => ['/users', ['Accept' => 'application/json'], ContentType::JSON],
            'html in the header' => ['/users', ['Accept' => 'text/html'], ContentType::HTML],
            'plain in the header' => ['/users', ['Accept' => 'text/plain'], ContentType::PLAIN],
            'the first by q' => ['/users', ['Accept' => 'text/html;q=0.5, application/json'], ContentType::JSON],
            'a browser header' => ['/users', ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'], ContentType::HTML],
            'a header that is not supported' => ['/users', ['Accept' => 'application/xml'], ContentType::HTML],
            'any type' => ['/users', ['Accept' => '*/*'], ContentType::HTML],
            'without header' => ['/users', [], ContentType::HTML],
            'a path that only looks like the api' => ['/apiary', ['Accept' => 'text/plain'], ContentType::PLAIN],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideFormats')]
    public function testTheClientPrefersAFormat(string $path, array $headers, ContentType $expected): void
    {
        $request = $this->request($path, $headers);

        $this->assertSame($expected, $request->getPreferredContentType());
        $this->assertSame($expected->getSubType(), $request->getPreferredFormat());
    }

    public function testAnApiRequestIsOneOfThePathApi(): void
    {
        $this->assertTrue($this->request('/api')->isApiRequest());
        $this->assertTrue($this->request('/api/users')->isApiRequest());
        $this->assertFalse($this->request('/apiary')->isApiRequest());
        $this->assertFalse($this->request('/v1/api')->isApiRequest());
    }

    public function testAnXmlHttpRequestIsToldByItsHeader(): void
    {
        $this->assertTrue($this->request('/', ['X-Requested-With' => 'XMLHttpRequest'])->isXmlHttpRequest());
        $this->assertFalse($this->request('/', ['X-Requested-With' => 'fetch'])->isXmlHttpRequest());
        $this->assertFalse($this->request()->isXmlHttpRequest());
    }

    public function testWhatTheMiddlewaresLeaveInTheRequestIsGivenByItsMethods(): void
    {
        $route = new RouteMatch(new Route('home', '/', 'handler'));
        $session = new Session(['a' => 1]);
        $flash = FlashMessages::createFromSession($session);
        $user = new DefaultUser('ana', ['admin']);

        $request = $this->request()
            ->withAttribute(RouterMiddleware::ROUTE_ATTRIBUTE, $route)
            ->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, $session)
            ->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $flash)
            ->withAttribute(UserInterface::class, $user);

        $this->assertSame($route, $request->route());
        $this->assertSame($session, $request->session());
        $this->assertSame($flash, $request->flash());
        $this->assertSame($user, $request->user());
    }

    public function testTheTransportDataIsThatOfTheMessage(): void
    {
        $request = new Request('GET', 'http://localhost/?x=1', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']);
        $request = $request->withCookieParams(['sid' => 'abc']);

        $this->assertSame(['REMOTE_ADDR' => '10.0.0.1'], $request->getServerParams());
        $this->assertSame(['sid' => 'abc'], $request->getCookieParams());
        $this->assertSame('/?x=1', $request->getRequestTarget());
        $this->assertSame(['Host' => ['localhost']], $request->getHeaders());
        $this->assertSame(['localhost'], $request->getHeader('Host'));
        $this->assertSame('localhost', $request->getHeaderLine('host'));
    }
}
