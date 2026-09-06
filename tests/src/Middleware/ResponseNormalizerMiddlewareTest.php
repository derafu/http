<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Middleware;

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Exception\ResponseSerializationException;
use Derafu\Http\Middleware\ResponseNormalizerMiddleware;
use Derafu\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use ReflectionMethod;
use Stringable;

/**
 * `json_encode()` rejects invalid UTF-8 (the exact shape of raw binary
 * content, e.g. a PDF, returned as a plain string) and `Response::asJson()`
 * uses `JSON_THROW_ON_ERROR`, so that failure always surfaces as a
 * `JsonException` for `normalizeResponse()` to handle.
 */
#[CoversClass(ResponseNormalizerMiddleware::class)]
#[UsesClass(ResponseSerializationException::class), UsesClass(ContentType::class), UsesClass(Response::class)]
class ResponseNormalizerMiddlewareTest extends TestCase
{
    private const INVALID_UTF8 = "\xB1\x31";

    private function normalize(mixed $response): PsrResponseInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getPreferredContentType')->willReturn(ContentType::JSON);

        $middleware = new ResponseNormalizerMiddleware();
        $method = new ReflectionMethod($middleware, 'normalizeResponse');

        return $method->invoke($middleware, $request, $response);
    }

    public function testFallsBackToPlainTextWhenTheResponseIsAStringAndJsonEncodingFails(): void
    {
        $response = $this->normalize(self::INVALID_UTF8);

        $this->assertSame(
            ContentType::PLAIN->withCharset(),
            $response->getHeaderLine('Content-Type'),
        );
        $this->assertSame(self::INVALID_UTF8, (string) $response->getBody());
    }

    /**
     * `asText()` takes a `string` parameter, so a `Stringable` response
     * (not itself a `string`) needs an explicit cast — this locks in that
     * the fallback still works for it, not just for a plain `string`.
     *
     * `json_encode()` never calls `__toString()` on an arbitrary object (only
     * on `JsonSerializable` ones) — it serializes its public properties
     * instead. So this object's invalid-UTF-8 property, not its string cast,
     * is what makes `json_encode()` fail here; `__toString()` returns the
     * same value to keep the scenario coherent.
     */
    public function testFallsBackToPlainTextWhenTheResponseIsAStringableObjectAndJsonEncodingFails(): void
    {
        $stringable = new class (self::INVALID_UTF8) implements Stringable {
            public function __construct(public readonly string $value)
            {
            }

            public function __toString(): string
            {
                return $this->value;
            }
        };

        $response = $this->normalize($stringable);

        $this->assertSame(self::INVALID_UTF8, (string) $response->getBody());
    }

    /**
     * The real-world case that motivated this: a Worker returns raw binary
     * content (e.g. a PDF) as a plain `string`, which the API wraps in a
     * `{"meta": ..., "data": <raw bytes>}` envelope before it ever reaches
     * this middleware. That envelope is an `array`, not a `string`, so no
     * plain-text fallback is possible — this must fail with a clear,
     * dedicated exception instead of a confusing `TypeError` from `asText()`
     * receiving something other than a `string`.
     */
    public function testThrowsResponseSerializationExceptionWhenTheResponseIsNotAStringAndJsonEncodingFails(): void
    {
        $response = ['meta' => [], 'data' => self::INVALID_UTF8];

        $this->expectException(ResponseSerializationException::class);
        $this->expectExceptionMessageMatches('/array/');

        $this->normalize($response);
    }

    public function testResponseSerializationExceptionKeepsTheOriginalJsonExceptionAsPrevious(): void
    {
        try {
            $this->normalize(['data' => self::INVALID_UTF8]);
            $this->fail('Expected ResponseSerializationException to be thrown.');
        } catch (ResponseSerializationException $e) {
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }
}
