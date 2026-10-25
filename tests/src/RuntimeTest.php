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

use Derafu\Http\Runtime;
use Derafu\TestsHttp\Fixture\PhpInputStream;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Throwable;

/**
 * The runtime builds the request from the superglobals and the files that PHP
 * uploads, whose layout is a column for each property instead of a list of
 * files.
 */
#[CoversClass(Runtime::class)]
final class RuntimeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $_POST = $_FILES = [];
    }

    /**
     * @param array<int|string, mixed> $files
     * @return array<int|string, mixed>
     */
    private function normalize(array $files): array
    {
        $method = new ReflectionMethod(Runtime::class, 'normalizeFiles');

        $normalized = $method->invoke(null, $files, new Psr17Factory());
        $this->assertIsArray($normalized);

        return $normalized;
    }

    private function tmpFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'derafu-http-');
        $this->assertIsString($path);
        file_put_contents($path, $content);

        return $path;
    }

    public function testASingleFileIsAnUploadedFile(): void
    {
        $tmp = $this->tmpFile('hello');

        $files = $this->normalize(['avatar' => [
            'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 5,
        ]]);

        $file = $files['avatar'];
        $this->assertInstanceOf(UploadedFileInterface::class, $file);
        $this->assertSame('a.txt', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(5, $file->getSize());
        $this->assertSame('hello', (string) $file->getStream());

        unlink($tmp);
    }

    public function testThePublicRuntimePreservesSparseUploadKeysAndLiteralFieldNames(): void
    {
        $tmp = $this->tmpFile('document');
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/upload'];
        $_FILES = ['attachments' => [
            'name' => [7 => ['front.page' => 'document.txt']],
            'type' => [7 => ['front.page' => 'text/plain']],
            'tmp_name' => [7 => ['front.page' => $tmp]],
            'error' => [7 => ['front.page' => UPLOAD_ERR_OK]],
            'size' => [7 => ['front.page' => 8]],
        ]];
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(204);
            }
        };

        ob_start();
        try {
            $this->assertSame(0, Runtime::run($handler));
            $request = $handler->request;
            $this->assertInstanceOf(ServerRequestInterface::class, $request);
            $files = $request->getUploadedFiles();
            $this->assertIsArray($files['attachments']);
            $this->assertSame([7], array_keys($files['attachments']));
            $this->assertIsArray($files['attachments'][7]);
            $this->assertSame(['front.page'], array_keys($files['attachments'][7]));
            $file = $files['attachments'][7]['front.page'];
            $this->assertInstanceOf(UploadedFileInterface::class, $file);
            $this->assertSame('document.txt', $file->getClientFilename());
            $this->assertSame('text/plain', $file->getClientMediaType());
            $this->assertSame(8, $file->getSize());
            $this->assertSame('document', (string) $file->getStream());
        } finally {
            ob_end_clean();
            unlink($tmp);
        }
    }

    public function testAFieldWithoutFileIsLeftOut(): void
    {
        $files = $this->normalize(['avatar' => [
            'name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0,
        ]]);

        $this->assertSame([], $files);
    }

    public function testAFieldWithSeveralFilesIsAGroup(): void
    {
        $a = $this->tmpFile('a');
        $b = $this->tmpFile('b');

        $files = $this->normalize(['photos' => [
            'name' => ['a.txt', 'b.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => [$a, $b],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [1, 1],
        ]]);

        $this->assertIsArray($files['photos']);
        $this->assertCount(2, $files['photos']);
        $second = $files['photos'][1];
        $this->assertInstanceOf(UploadedFileInterface::class, $second);
        $this->assertSame('b', (string) $second->getStream());

        unlink($a);
        unlink($b);
    }

    public function testNestedFieldsKeepTheirNames(): void
    {
        $tmp = $this->tmpFile('x');

        // The field is cafs[0][caf].
        $files = $this->normalize(['cafs' => [
            'name' => [0 => ['caf' => 'c.xml']],
            'type' => [0 => ['caf' => 'text/xml']],
            'tmp_name' => [0 => ['caf' => $tmp]],
            'error' => [0 => ['caf' => UPLOAD_ERR_OK]],
            'size' => [0 => ['caf' => 1]],
        ]]);

        $this->assertIsArray($files['cafs']);
        $this->assertIsArray($files['cafs'][0]);
        $this->assertInstanceOf(UploadedFileInterface::class, $files['cafs'][0]['caf']);
        $this->assertSame('c.xml', $files['cafs'][0]['caf']->getClientFilename());

        unlink($tmp);
    }

    public function testAnUploadedFileAndAGroupWithoutTmpNameAreKept(): void
    {
        $uploaded = (new Psr17Factory())->createUploadedFile((new Psr17Factory())->createStream('x'));

        $files = $this->normalize(['one' => $uploaded, 'group' => ['inner' => $uploaded], 'text' => 'ignored']);

        $this->assertSame($uploaded, $files['one']);
        $this->assertSame(['inner' => $uploaded], $files['group']);
        $this->assertArrayNotHasKey('text', $files);
    }

    public function testAFileWithAnInvalidLayoutIsATranslatableError(): void
    {
        $exception = null;
        try {
            $this->normalize(['avatar' => [
                'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => 5, 'error' => UPLOAD_ERR_OK, 'size' => 1,
            ]]);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringContainsString('avatar.tmp_name', $exception->getMessage());
    }

    public function testTheContextHasTheDefaultsWithoutAWebServer(): void
    {
        $_SERVER = [];

        $context = Runtime::getApplicationContext();

        $this->assertSame(80, $context['URL_PORT']);
        $this->assertSame('http', $context['URL_SCHEME']);
        $this->assertSame('GET', $context['URL_METHOD']);
        $this->assertSame('/', $context['URL_URI']);
    }

    public function testThePort443IsHttps(): void
    {
        $_SERVER = ['SERVER_PORT' => '443'];

        $this->assertSame('https', Runtime::getApplicationContext()['URL_SCHEME']);
    }

    public function testTheRequestIsBuiltFromTheSuperglobals(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users?x=1',
            'HTTP_ACCEPT_LANGUAGE' => 'es-CL',
            'HTTP_X_EMPTY' => null,
        ];
        $_GET = ['x' => '1'];
        $_POST = ['name' => 'Ana'];

        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, [], 'ok');
            }
        };

        ob_start();
        $status = Runtime::run($handler);
        $output = ob_get_clean();

        $this->assertSame(0, $status);
        $this->assertSame('ok', $output);
        $request = $handler->request;
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/users?x=1', (string) $request->getUri());
        $this->assertSame('es-CL', $request->getHeaderLine('accept-language'));
        $this->assertFalse($request->hasHeader('x-empty'));
        $this->assertSame(['x' => '1'], $request->getQueryParams());
        $this->assertSame(['name' => 'Ana'], $request->getParsedBody());
    }

    public function testTheMethodWithAnInvalidTypeIsATranslatableError(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 5];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/REQUEST_METHOD/');

        Runtime::run($this->createStub(RequestHandlerInterface::class));
    }

    public function testTheResponseIsSentWithItsHeadersAndItsStatus(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(500, ['X-A' => ['1', '2'], 'X-B' => 'b'], 'failed');
            }
        };

        ob_start();
        $status = Runtime::run($handler);
        $output = ob_get_clean();

        // The status of the execution is an error one, and the body is sent.
        $this->assertSame(1, $status);
        $this->assertSame('failed', $output);
        $this->assertSame(500, http_response_code());
        http_response_code(200);
    }

    public function testTheBodyOfTheRequestIsTheOneThatIsReadFromTheInput(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users'];

        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        PhpInputStream::register('{"name":"Ana"}');
        try {
            ob_start();
            Runtime::run($handler);
            ob_end_clean();
        } finally {
            PhpInputStream::restore();
        }

        $this->assertNotNull($handler->request);
        $this->assertSame('{"name":"Ana"}', (string) $handler->request->getBody());
    }

    public function testWithoutBodyTheRequestHasAnEmptyOne(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];

        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        PhpInputStream::register('');
        try {
            ob_start();
            Runtime::run($handler);
            ob_end_clean();
        } finally {
            PhpInputStream::restore();
        }

        $this->assertNotNull($handler->request);
        $this->assertSame('', (string) $handler->request->getBody());
    }
}
