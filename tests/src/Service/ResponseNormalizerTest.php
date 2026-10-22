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

use ArrayObject;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Exception\ResponseSerializationException;
use Derafu\Http\Response;
use Derafu\Http\Service\ResponseNormalizer;
use JsonSerializable;
use Nyholm\Psr7\Response as NyholmResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Stringable;

/**
 * What a handler returns is sent in the format that the request prefers; a
 * value that has no text (an array, an object) is sent as JSON in any format.
 */
#[CoversClass(ResponseNormalizer::class)]
#[UsesClass(ResponseSerializationException::class)]
#[UsesClass(ContentType::class)]
#[UsesClass(Response::class)]
final class ResponseNormalizerTest extends TestCase
{
    private function request(ContentType $format): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getPreferredContentType')->willReturn($format);

        return $request;
    }

    private function text(string $text): Stringable
    {
        return new class ($text) implements Stringable {
            public function __construct(private readonly string $text)
            {
            }

            public function __toString(): string
            {
                return $this->text;
            }
        };
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function provideValuesAsText(): array
    {
        return [
            'a string' => ['page', 'page'],
            'null' => [null, ''],
            'an int' => [42, '42'],
            'a float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'false' => [false, ''],
        ];
    }

    #[DataProvider('provideValuesAsText')]
    public function testAValueWithTextIsSentInTheFormatOfTheRequest(mixed $value, string $expected): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::HTML), $value);

        $this->assertSame(ContentType::HTML->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame($expected, (string) $response->getBody());
    }

    public function testAStringableIsSentAsTextInTheFormatOfTheRequest(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::MARKDOWN), $this->text('# Hi'));

        $this->assertSame(ContentType::MARKDOWN->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame('# Hi', (string) $response->getBody());
    }

    public function testAnArrayIsSentAsJsonEvenIfTheFormatIsNotJson(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::HTML), ['a' => 1]);

        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"a":1}', (string) $response->getBody());
    }

    public function testAnObjectWithoutTextIsSentAsJsonEvenIfTheFormatIsNotJson(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::PLAIN), new ArrayObject(['a' => 1]));

        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"a":1}', (string) $response->getBody());
    }

    public function testAnArrayThatCanNotBeEncodedIsATranslatableError(): void
    {
        $this->expectException(ResponseSerializationException::class);
        $this->expectExceptionMessage('response is of type array');

        (new ResponseNormalizer())->normalize($this->request(ContentType::HTML), ["\xB1\x31"]);
    }

    public function testAJsonRequestEncodesTheValue(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::JSON), ['a' => 1]);

        $this->assertSame('{"a":1}', (string) $response->getBody());
    }

    public function testAJsonRequestWithABinaryStringFallsBackToPlainText(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::JSON), "\xB1\x31");

        $this->assertSame(ContentType::PLAIN->withCharset(), $response->getHeaderLine('Content-Type'));
    }

    public function testAJsonRequestWithAValueThatCanNotBeEncodedIsATranslatableError(): void
    {
        $this->expectException(ResponseSerializationException::class);

        (new ResponseNormalizer())->normalize($this->request(ContentType::JSON), ["\xB1\x31"]);
    }

    public function testAResponseWithoutContentTypeGetsTheOneOfTheRequest(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::HTML), new Response());

        $this->assertSame(ContentType::HTML->withCharset(), $response->getHeaderLine('Content-Type'));
    }

    public function testAResponseWithContentTypeIsNotChanged(): void
    {
        $original = (new Response())->asText('x', ContentType::PLAIN);

        $this->assertSame($original, (new ResponseNormalizer())->normalize($this->request(ContentType::HTML), $original));
    }

    public function testAPsrResponseIsNotChanged(): void
    {
        $original = new NyholmResponse(200, [], 'x');

        $this->assertSame($original, (new ResponseNormalizer())->normalize($this->request(ContentType::HTML), $original));
    }

    public function testAStringableIsSentAsItsTextEncodedInJsonWhenTheFormatIsJson(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::JSON), $this->text('hi'));

        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('"hi"', (string) $response->getBody());
    }

    public function testAStringableThatCanNotBeEncodedInJsonFallsBackToPlainText(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::JSON), $this->text("\xB1\x31"));

        $this->assertSame(ContentType::PLAIN->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame("\xB1\x31", (string) $response->getBody());
    }

    /**
     * @return array<string, array{ContentType}>
     */
    public static function provideFormats(): array
    {
        return [
            'json' => [ContentType::JSON],
            'html' => [ContentType::HTML],
        ];
    }

    #[DataProvider('provideFormats')]
    public function testJsonSerializableWinsOverStringable(ContentType $format): void
    {
        $value = new class () implements JsonSerializable, Stringable {
            public function jsonSerialize(): mixed
            {
                return ['a' => 1];
            }

            public function __toString(): string
            {
                return 'text';
            }
        };

        $response = (new ResponseNormalizer())->normalize($this->request($format), $value);

        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"a":1}', (string) $response->getBody());
    }

    public function testTheFormatThatIsGivenIsUsedInsteadOfTheOneOfTheRequest(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::PNG), '<p>hi</p>', ContentType::HTML);

        $this->assertSame(ContentType::HTML->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame('<p>hi</p>', (string) $response->getBody());
    }

    public function testTheFormatThatIsGivenIsTheOneOfAResponseWithoutContentType(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::PNG), new Response(), ContentType::HTML);

        $this->assertSame(ContentType::HTML->withCharset(), $response->getHeaderLine('Content-Type'));
    }

    public function testAnArrayIsJsonWhateverFormatIsGiven(): void
    {
        $response = (new ResponseNormalizer())->normalize($this->request(ContentType::PNG), ['a' => 1], ContentType::HTML);

        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
    }
}
