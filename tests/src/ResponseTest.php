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
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Response;
use JsonException;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * A response is a PSR-7 message: it does not change, each `with*` gives another
 * one. On top of that it knows how to be text, HTML, JSON and a redirect.
 */
#[CoversClass(Response::class)]
#[UsesClass(ContentType::class)]
#[UsesClass(HttpStatus::class)]
final class ResponseTest extends TestCase
{
    public function testItHasTheValuesItWasCreatedWith(): void
    {
        $response = new Response(201, ['X-A' => 'a'], 'body', '1.0', 'Made');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Made', $response->getReasonPhrase());
        $this->assertSame('1.0', $response->getProtocolVersion());
        $this->assertSame(['X-A' => ['a']], $response->getHeaders());
        $this->assertSame('body', (string) $response->getBody());
    }

    public function testTheDefaultIsAnEmptyOk(): void
    {
        $response = new Response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getReasonPhrase());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testEachWithGivesAnotherResponseAndTheOriginalDoesNotChange(): void
    {
        $original = new Response();

        $changed = [
            $original->withStatus(404),
            $original->withProtocolVersion('2.0'),
            $original->withHeader('X-A', 'a'),
            $original->withAddedHeader('X-A', 'a'),
            $original->withBody(Stream::create('body')),
            $original->withHttpStatus(HttpStatus::GONE),
            $original->withContentType(ContentType::HTML),
            $original->asText('text'),
            $original->asHtml('<p>html</p>'),
            $original->asJson(['a' => 1]),
            $original->redirect('/home'),
        ];

        foreach ($changed as $response) {
            $this->assertNotSame($original, $response);
        }
        $this->assertSame(200, $original->getStatusCode());
        $this->assertSame('1.1', $original->getProtocolVersion());
        $this->assertSame([], $original->getHeaders());
        $this->assertSame('', (string) $original->getBody());
    }

    public function testWithoutHeaderRemovesItOnlyInTheCopy(): void
    {
        $original = new Response(200, ['X-A' => 'a']);

        $copy = $original->withoutHeader('X-A');

        $this->assertFalse($copy->hasHeader('X-A'));
        $this->assertTrue($original->hasHeader('X-A'));
    }

    public function testTheHeadersAreReadByNameWithoutCaseAndAsALine(): void
    {
        $response = (new Response())->withHeader('X-A', 'a')->withAddedHeader('x-a', 'b');

        $this->assertTrue($response->hasHeader('X-A'));
        $this->assertSame(['a', 'b'], $response->getHeader('x-a'));
        $this->assertSame('a, b', $response->getHeaderLine('X-A'));
    }

    public function testTheStatusHasItsReasonPhrase(): void
    {
        $response = (new Response())->withHttpStatus(HttpStatus::NOT_FOUND);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(HttpStatus::NOT_FOUND->getReasonPhrase(), $response->getReasonPhrase());
    }

    public function testTheContentTypeOfATextHasTheCharset(): void
    {
        $response = (new Response())->withContentType(ContentType::HTML);

        $this->assertSame(ContentType::HTML->withCharset(), $response->getHeaderLine('Content-Type'));
    }

    public function testTheContentTypeOfABinaryHasNoCharset(): void
    {
        $response = (new Response())->withContentType(ContentType::PDF);

        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
    }

    public function testATextIsSentAsPlainByDefault(): void
    {
        $response = (new Response())->asText('hello');

        $this->assertSame(ContentType::PLAIN->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame('hello', (string) $response->getBody());
    }

    public function testAnHtmlIsSentAsHtml(): void
    {
        $response = (new Response())->asHtml('<p>hi</p>');

        $this->assertSame(ContentType::HTML->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame('<p>hi</p>', (string) $response->getBody());
    }

    public function testAJsonKeepsTheSlashesAndTheUnicode(): void
    {
        $response = (new Response())->asJson(['url' => 'https://a.cl/ñ']);

        $this->assertSame(ContentType::JSON->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"url":"https://a.cl/ñ"}', (string) $response->getBody());
    }

    public function testAJsonThatCanNotBeEncodedIsAnException(): void
    {
        $this->expectException(JsonException::class);

        (new Response())->asJson("\xB1\x31");
    }

    public function testARedirectHasTheLocationAndAnEmptyBody(): void
    {
        $response = (new Response(200, [], 'before'))->redirect('/home');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/home', $response->getHeaderLine('Location'));
        $this->assertSame('', (string) $response->getBody());
    }

    public function testARedirectCanHaveAnotherStatus(): void
    {
        $response = (new Response())->redirect('/new', HttpStatus::MOVED_PERMANENTLY);

        $this->assertSame(301, $response->getStatusCode());
    }
}
