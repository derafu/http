<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Middleware;

use Derafu\Http\Enum\ContentType;
use Derafu\Http\Middleware\RangeMiddleware;
use Derafu\Http\Response;
use Derafu\Http\Support\RangeStream;
use Derafu\TestsHttp\Fixture\GeneratedStream;
use Derafu\TestsHttp\Fixture\UnsizedStream;
use Nyholm\Psr7\Response as PsrResponse;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RangeMiddleware::class)]
#[UsesClass(RangeStream::class)]
#[UsesClass(Response::class)]
#[UsesClass(ContentType::class)]
final class RangeMiddlewareTest extends TestCase
{
    private const CONTENT = '0123456789';

    /**
     * @param array<string, string> $headers
     */
    private function process(
        array $headers,
        ?PsrResponseInterface $response = null,
        string $method = 'GET'
    ): PsrResponseInterface {
        $response ??= (new Response())
            ->asDownload(self::CONTENT, 'a.txt', ContentType::PLAIN)
            ->withHeader('ETag', '"v1"')
            ->withHeader('Last-Modified', 'Sat, 01 Jan 2028 00:00:00 GMT')
        ;

        $handler = new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly PsrResponseInterface $response)
            {
            }

            public function handle(PsrRequestInterface $request): PsrResponseInterface
            {
                return $this->response;
            }
        };

        return (new RangeMiddleware())->process(
            new ServerRequest($method, 'http://localhost/a.txt', $headers),
            $handler
        );
    }

    private function assertWhole(PsrResponseInterface $response): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::CONTENT, (string) $response->getBody());
        $this->assertSame('10', $response->getHeaderLine('Content-Length'));
        $this->assertFalse($response->hasHeader('Content-Range'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function ranges(): array
    {
        return [
            'first and last' => ['bytes=2-5', '2345', 'bytes 2-5/10'],
            'only the first' => ['bytes=4-', '456789', 'bytes 4-9/10'],
            'the last ones' => ['bytes=-3', '789', 'bytes 7-9/10'],
            'a last one beyond the size' => ['bytes=7-500', '789', 'bytes 7-9/10'],
            'more last ones than the size' => ['bytes=-500', self::CONTENT, 'bytes 0-9/10'],
            'a single byte' => ['bytes=0-0', '0', 'bytes 0-0/10'],
            'the last byte' => ['bytes=9-9', '9', 'bytes 9-9/10'],
            'the unit in capitals' => ['BYTES=1-2', '12', 'bytes 1-2/10'],
        ];
    }

    #[DataProvider('ranges')]
    public function testItServesTheRange(string $range, string $body, string $contentRange): void
    {
        $response = $this->process(['Range' => $range]);

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('Partial Content', $response->getReasonPhrase());
        $this->assertSame($body, (string) $response->getBody());
        $this->assertSame($contentRange, $response->getHeaderLine('Content-Range'));
        $this->assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'));
        $this->assertSame('bytes', $response->getHeaderLine('Accept-Ranges'));
        $this->assertSame('attachment; filename="a.txt"', $response->getHeaderLine('Content-Disposition'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsatisfiable(): array
    {
        return [
            'starts at the end' => ['bytes=10-'],
            'starts beyond the end' => ['bytes=50-60'],
            'no last bytes' => ['bytes=-0'],
        ];
    }

    #[DataProvider('unsatisfiable')]
    public function testARangeThatIsOutsideOfTheFileIsRefused(string $range): void
    {
        $response = $this->process(['Range' => $range]);

        $this->assertSame(416, $response->getStatusCode());
        $this->assertSame('bytes */10', $response->getHeaderLine('Content-Range'));
        $this->assertSame('0', $response->getHeaderLine('Content-Length'));
        $this->assertSame('', (string) $response->getBody());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ignored(): array
    {
        return [
            'several ranges' => ['bytes=0-1,4-5'],
            'another unit' => ['items=0-1'],
            'without positions' => ['bytes=-'],
            'ends before it starts' => ['bytes=5-2'],
            'not a range' => ['hello'],
            'a position too large for an integer' => ['bytes=99999999999999999999-'],
            'a negative start' => ['bytes=--5'],
        ];
    }

    #[DataProvider('ignored')]
    public function testARangeThatIsNotValidOrIsNotOneGivesTheWholeFile(string $range): void
    {
        $this->assertWhole($this->process(['Range' => $range]));
    }

    public function testWithoutRangeTheResponseIsNotChanged(): void
    {
        $this->assertWhole($this->process([]));
    }

    public function testOnlyGetIsServedByRanges(): void
    {
        $this->assertWhole($this->process(['Range' => 'bytes=2-3'], null, 'HEAD'));
        $this->assertWhole($this->process(['Range' => 'bytes=2-3'], null, 'POST'));
    }

    public function testTheRangeIsServedIfTheEntityTagIsTheSame(): void
    {
        $response = $this->process(['Range' => 'bytes=2-3', 'If-Range' => '"v1"']);

        $this->assertSame(206, $response->getStatusCode());
    }

    public function testTheWholeFileIsServedIfTheEntityTagChanged(): void
    {
        $this->assertWhole($this->process(['Range' => 'bytes=2-3', 'If-Range' => '"v0"']));
    }

    public function testAWeakEntityTagDoesNotMatchAnIfRange(): void
    {
        $response = (new Response())
            ->asDownload(self::CONTENT)
            ->withHeader('ETag', 'W/"v1"')
        ;

        $this->assertWhole($this->process(['Range' => 'bytes=2-3', 'If-Range' => 'W/"v1"'], $response));
    }

    public function testTheRangeIsServedIfTheDateIsTheLastModification(): void
    {
        $response = $this->process(['Range' => 'bytes=2-3', 'If-Range' => 'Sat, 01 Jan 2028 00:00:00 GMT']);

        $this->assertSame(206, $response->getStatusCode());
    }

    public function testTheWholeFileIsServedIfTheDateIsNotTheLastModification(): void
    {
        $this->assertWhole($this->process(['Range' => 'bytes=2-3', 'If-Range' => 'Fri, 31 Dec 2027 00:00:00 GMT']));
        $this->assertWhole($this->process(['Range' => 'bytes=2-3', 'If-Range' => 'not a date']));
    }

    public function testAResponseThatIsNotAnOkIsNotChanged(): void
    {
        $response = (new Response())->asDownload(self::CONTENT)->withStatus(404);

        $this->assertSame(404, $this->process(['Range' => 'bytes=2-3'], $response)->getStatusCode());
    }

    public function testAResponseThatDoesNotOfferRangesIsNotChanged(): void
    {
        $response = new PsrResponse(200, ['Content-Length' => '10'], self::CONTENT);

        $this->assertWhole($this->process(['Range' => 'bytes=2-3'], $response));
    }

    public function testABodyWithoutKnownSizeIsNotChanged(): void
    {
        $stream = new UnsizedStream(self::CONTENT);
        $response = (new PsrResponse(200, ['Accept-Ranges' => 'bytes']))->withBody($stream);

        $this->assertSame(200, $this->process(['Range' => 'bytes=2-3'], $response)->getStatusCode());
    }

    public function testAFileLargerThanTheMemoryIsServedByRangeWithoutReadingIt(): void
    {
        // 5 GB: reading it whole, or building a string of it, would not fit.
        $size = 5 * 1024 * 1024 * 1024;
        $response = (new Response())->asDownload(new GeneratedStream($size, 'z'));

        $partial = $this->process(['Range' => 'bytes=' . ($size - 4) . '-'], $response);

        $this->assertSame(206, $partial->getStatusCode());
        $this->assertSame('bytes ' . ($size - 4) . '-' . ($size - 1) . '/' . $size, $partial->getHeaderLine('Content-Range'));
        $this->assertSame('zzzz', (string) $partial->getBody());
    }
}
