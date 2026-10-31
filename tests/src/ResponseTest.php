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
use Derafu\TestsHttp\Fixture\GeneratedStream;
use Derafu\TestsHttp\Fixture\UnsizedStream;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use JsonException;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFileInfo;

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

    private function tmpFile(string $content, string $name = 'file.txt'): string
    {
        $directory = sys_get_temp_dir() . '/download-test-' . uniqid();
        mkdir($directory);
        $path = $directory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    public function testAStringIsTheContentOfTheDownload(): void
    {
        $response = (new Response())->asDownload('a;b', 'data.csv', ContentType::CSV);

        $this->assertSame('a;b', (string) $response->getBody());
        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('attachment; filename="data.csv"', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('3', $response->getHeaderLine('Content-Length'));
        $this->assertSame('bytes', $response->getHeaderLine('Accept-Ranges'));
    }

    public function testAStringIsNeverAPath(): void
    {
        $path = $this->tmpFile('SECRET');

        $response = (new Response())->asDownload($path);

        $this->assertSame($path, (string) $response->getBody());

        unlink($path);
        rmdir(dirname($path));
    }

    public function testTheDefaultTypeIsBinaryAndThereIsNoNameWithoutOne(): void
    {
        $response = (new Response())->asDownload('x');

        $this->assertSame('application/octet-stream', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($response->hasHeader('Content-Disposition'));
    }

    public function testAStreamIsSentAsItIs(): void
    {
        $stream = Stream::create('stream');

        $response = (new Response())->asDownload($stream, 's.txt', ContentType::PLAIN);

        $this->assertSame($stream, $response->getBody());
        $this->assertSame('6', $response->getHeaderLine('Content-Length'));
    }

    public function testAResourceIsSentAsAStream(): void
    {
        $handle = fopen('php://temp', 'w+b');
        $this->assertNotFalse($handle);
        fwrite($handle, 'resource');
        rewind($handle);

        $response = (new Response())->asDownload($handle, 'r.txt');

        $this->assertSame('8', $response->getHeaderLine('Content-Length'));
        $this->assertSame('resource', (string) $response->getBody());
    }

    public function testAFileIsOpenedAndTakesItsNameFromThePath(): void
    {
        $path = $this->tmpFile('from a file', 'report.txt');

        $response = (new Response())->asDownload(new SplFileInfo($path), type: ContentType::PLAIN);

        $this->assertSame('attachment; filename="report.txt"', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('11', $response->getHeaderLine('Content-Length'));
        $this->assertSame('bytes', $response->getHeaderLine('Accept-Ranges'));
        $this->assertSame('from a file', (string) $response->getBody());
        $this->assertFileExists($path);

        unlink($path);
        rmdir(dirname($path));
    }

    public function testTheNameGivenIsTheOneThatIsUsedForAFile(): void
    {
        $path = $this->tmpFile('x', 'internal-1234.tmp');

        $response = (new Response())->asDownload(new SplFileInfo($path), 'invoice.xml');

        $this->assertSame('attachment; filename="invoice.xml"', $response->getHeaderLine('Content-Disposition'));

        unlink($path);
        rmdir(dirname($path));
    }

    public function testATemporaryFileIsDeletedAndStillSent(): void
    {
        $path = $this->tmpFile('temporary');

        $response = (new Response())->asDownload(new SplFileInfo($path), deleteAfter: true);

        $this->assertFileDoesNotExist($path);
        $this->assertSame('temporary', (string) $response->getBody());

        rmdir(dirname($path));
    }

    public function testOnlyAFileCanBeDeleted(): void
    {
        try {
            (new Response())->asDownload('content', deleteAfter: true);
            $this->fail('A string was deleted.');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(TranslatableInterface::class, $e);
            $this->assertStringContainsString('string', $e->getMessage());
        }
    }

    public function testAFileThatDoesNotExistIsAnError(): void
    {
        try {
            (new Response())->asDownload(new SplFileInfo('/does/not/exist.txt'));
            $this->fail('A file that does not exist was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(TranslatableInterface::class, $e);
            $this->assertStringContainsString('/does/not/exist.txt', $e->getMessage());
        }
    }

    public function testADirectoryIsNotAFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Response())->asDownload(new SplFileInfo(sys_get_temp_dir()));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidSources(): array
    {
        return [
            'null' => [null],
            'an integer' => [10],
            'an array' => [['a']],
            'an object' => [new \stdClass()],
        ];
    }

    #[DataProvider('invalidSources')]
    public function testASourceOfAnotherTypeIsAnError(mixed $source): void
    {
        // The type of the source is a rule of the method, so it is called without
        // the check of its declared type to see that it is refused.
        $asDownload = new ReflectionMethod(Response::class, 'asDownload');

        try {
            $asDownload->invoke(new Response(), $source);
            $this->fail('A source of another type was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(TranslatableInterface::class, $e);
            $this->assertStringContainsString(get_debug_type($source), $e->getMessage());
        }
    }

    public function testAClosedResourceIsAnError(): void
    {
        $handle = fopen('php://temp', 'r');
        $this->assertNotFalse($handle);
        fclose($handle);

        $this->expectException(InvalidArgumentException::class);

        (new Response())->asDownload($handle);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'with accents' => ['Liquidación de sueldo.pdf', 'attachment; filename="Liquidaci_n de sueldo.pdf"; filename*=UTF-8\'\'Liquidaci%C3%B3n%20de%20sueldo.pdf'],
            'with quotes' => ['a"b.txt', 'attachment; filename="a_b.txt"; filename*=UTF-8\'\'a%22b.txt'],
            'with a line break' => ["a\r\nSet-Cookie: x=1.txt", 'attachment; filename="a_Set-Cookie: x=1.txt"'],
            'with a path' => ['../../etc/passwd', 'attachment; filename=".._.._etc_passwd"'],
            'with backslashes' => ['a\\b.txt', 'attachment; filename="a_b.txt"'],
            'with a null byte' => ["a\0b.txt", 'attachment; filename="a_b.txt"'],
            'only separators' => ['///', 'attachment; filename="_"'],
            'empty' => ['', 'attachment; filename="download"'],
            'in latin 1' => ["Informaci\xF3n.txt", 'attachment; filename="Informaci_n.txt"; filename*=UTF-8\'\'Informaci%C3%B3n.txt'],
        ];
    }

    #[DataProvider('names')]
    public function testTheNameOfTheFileCannotBreakTheHeader(string $name, string $expected): void
    {
        $response = (new Response())->asDownload('x', $name);

        $this->assertSame($expected, $response->getHeaderLine('Content-Disposition'));
        $this->assertCount(1, $response->getHeader('Content-Disposition'));
        $this->assertFalse($response->hasHeader('Set-Cookie'));
    }

    public function testADownloadLargerThanTheMemoryIsNotRead(): void
    {
        $response = (new Response())->asDownload(new GeneratedStream(8 * 1024 * 1024 * 1024), 'big.bin');

        $this->assertSame('8589934592', $response->getHeaderLine('Content-Length'));
        $this->assertSame('bytes', $response->getHeaderLine('Accept-Ranges'));
    }

    public function testWithoutKnownSizeThereIsNoLengthNorRanges(): void
    {
        $stream = new UnsizedStream('12345');

        $response = (new Response(200, ['Content-Length' => '5', 'Accept-Ranges' => 'bytes']))->asDownload($stream);

        $this->assertFalse($response->hasHeader('Content-Length'));
        $this->assertFalse($response->hasHeader('Accept-Ranges'));
    }

    public function testAStreamThatCannotBeMovedHasNoRanges(): void
    {
        $handle = fopen('php://output', 'w');
        $this->assertNotFalse($handle);

        $response = (new Response())->asDownload($handle);

        $this->assertFalse($response->hasHeader('Accept-Ranges'));
    }

    public function testAFileThatCannotBeDeletedIsAnError(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('The superuser can delete a file in any directory.');
        }

        $path = $this->tmpFile('locked');
        chmod(dirname($path), 0555);

        // The failed deletion is a warning of PHP: the error is the one of the method.
        set_error_handler(static fn (): bool => true);

        try {
            (new Response())->asDownload(new SplFileInfo($path), deleteAfter: true);
            $this->fail('A file that cannot be deleted was sent.');
        } catch (\Derafu\Translation\Exception\Core\TranslatableRuntimeException $e) {
            $this->assertInstanceOf(TranslatableInterface::class, $e);
            $this->assertStringContainsString($path, $e->getMessage());
        } finally {
            restore_error_handler();
            chmod(dirname($path), 0755);
            unlink($path);
            rmdir(dirname($path));
        }
    }
}
