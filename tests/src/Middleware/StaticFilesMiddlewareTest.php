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
use Derafu\Http\Middleware\StaticFilesMiddleware;
use Derafu\Http\Response;
use Nyholm\Psr7\Response as PsrResponse;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Static files must only be served from inside the configured directory.
 *
 * Layout created for every test (`$base` is resolved with `realpath()`):
 *
 *   $base/static/css/ok.css         The only legitimate files.
 *   $base/static/.hidden.css        A hidden file inside the directory.
 *   $base/static/link.css           A symlink pointing outside.
 *   $base/static-secret/secret.css  A sibling sharing the directory prefix.
 *   $base/outside.css               A file outside of the directory.
 */
#[CoversClass(StaticFilesMiddleware::class)]
#[UsesClass(ContentType::class), UsesClass(Response::class)]
class StaticFilesMiddlewareTest extends TestCase
{
    private string $base;

    private string $directory;

    protected function setUp(): void
    {
        $this->base = realpath(sys_get_temp_dir()) . '/static-test-' . uniqid();
        $this->directory = $this->base . '/static';

        mkdir($this->directory . '/css', 0777, true);
        mkdir($this->base . '/static-secret');

        file_put_contents($this->directory . '/css/ok.css', 'body{}');
        file_put_contents($this->directory . '/.hidden.css', 'HIDDEN');
        file_put_contents($this->base . '/static-secret/secret.css', 'SECRET-SIBLING');
        file_put_contents($this->base . '/outside.css', 'SECRET-OUTSIDE');
        symlink($this->base . '/outside.css', $this->directory . '/link.css');
    }

    protected function tearDown(): void
    {
        $this->remove($this->base);
    }

    /**
     * Removes a path recursively. Symlinks are removed, never followed.
     */
    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }

        rmdir($path);
    }

    /**
     * Handler that records it was reached, i.e. the file was NOT served.
     */
    private function next(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(PsrRequestInterface $request): PsrResponseInterface
            {
                return new PsrResponse(404, [], 'NEXT');
            }
        };
    }

    private function process(
        string $path,
        ?string $directory = null,
        string $method = 'GET',
        array $headers = []
    ): PsrResponseInterface {
        $request = new ServerRequest($method, 'http://localhost/', $headers);
        // Set the raw path as is, the way the runtime hands it over.
        $request = $request->withUri($request->getUri()->withPath($path));

        $middleware = new StaticFilesMiddleware($directory ?? $this->directory);

        return $middleware->process($request, $this->next());
    }

    private function assertServed(PsrResponseInterface $response, string $body): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($body, (string) $response->getBody());
    }

    private function assertNotServed(PsrResponseInterface $response): void
    {
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('NEXT', (string) $response->getBody());
    }

    public function testServesAFileInsideTheDirectory(): void
    {
        $response = $this->process('/css/ok.css');

        $this->assertServed($response, 'body{}');
        $this->assertSame(
            ContentType::CSS->withCharset(),
            $response->getHeaderLine('Content-Type')
        );
        $this->assertStringContainsString(
            'max-age=86400',
            $response->getHeaderLine('Cache-Control')
        );
    }

    public function testDelegatesNonStaticRequestsToTheNextHandler(): void
    {
        $this->assertNotServed($this->process('/about'));
    }

    public function testReturns304WhenIfNoneMatchMatchesTheEtag(): void
    {
        $etag = $this->process('/css/ok.css')->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);

        $response = $this->process('/css/ok.css', null, 'GET', ['If-None-Match' => $etag]);

        $this->assertSame(304, $response->getStatusCode());
    }

    /**
     * The directory is compared by prefix, so a sibling such as
     * `static-secret` must not pass for being inside `static`.
     */
    public function testDoesNotServeASiblingDirectorySharingThePrefix(): void
    {
        $this->assertNotServed($this->process('/../static-secret/secret.css'));
    }

    #[DataProvider('unsafePathsProvider')]
    public function testRejectsUnsafePaths(string $path): void
    {
        $this->assertNotServed($this->process($path));
    }

    public static function unsafePathsProvider(): array
    {
        return [
            'parent-directory' => ['/../outside.css'],
            'inside-but-with-dots' => ['/css/../css/ok.css'],
            'trailing-dots-segment' => ['/css/..'],
            'multiple-leading-slashes' => ['//../outside.css'],
            'hidden-file' => ['/.hidden.css'],
            'file-in-hidden-directory' => ['/.git/config.css'],
            'null-byte' => ["/css/ok.css\0.png"],
        ];
    }

    public function testDoesNotServeASymlinkPointingOutsideTheDirectory(): void
    {
        $this->assertNotServed($this->process('/link.css'));
    }

    /**
     * Names that merely contain dots are legitimate.
     */
    public function testServesFilesWhoseNamesContainDots(): void
    {
        file_put_contents($this->directory . '/css/app.min.v1..2.css', 'min');

        try {
            $this->assertServed($this->process('/css/app.min.v1..2.css'), 'min');
        } finally {
            unlink($this->directory . '/css/app.min.v1..2.css');
        }
    }

    /**
     * A directory configured through a symlink (e.g. a `current` release)
     * must keep working: it is resolved once on construction.
     */
    public function testServesFromADirectoryConfiguredThroughASymlink(): void
    {
        symlink($this->directory, $this->base . '/alias');

        $this->assertServed(
            $this->process('/css/ok.css', $this->base . '/alias'),
            'body{}'
        );
    }

    /**
     * Same sibling protection when the configured directory carries a
     * trailing slash or a `..` segment.
     */
    #[DataProvider('equivalentDirectoriesProvider')]
    public function testSiblingStaysProtectedWhateverTheDirectorySpelling(string $suffix): void
    {
        $directory = $this->directory . $suffix;

        $this->assertServed($this->process('/css/ok.css', $directory), 'body{}');
        $this->assertNotServed(
            $this->process('/../static-secret/secret.css', $directory)
        );
    }

    public static function equivalentDirectoriesProvider(): array
    {
        return [
            'trailing-slash' => ['/'],
            'dot-segment' => ['/css/..'],
        ];
    }

    public function testADirectoryThatDoesNotExistServesNothing(): void
    {
        $this->assertNotServed(
            $this->process('/css/ok.css', $this->base . '/missing')
        );
    }

    #[DataProvider('notAllowedMethodsProvider')]
    public function testOnlyGetAndHeadAreServed(string $method): void
    {
        $this->assertNotServed($this->process('/css/ok.css', null, $method));
    }

    public static function notAllowedMethodsProvider(): array
    {
        return [
            'post' => ['POST'],
            'put' => ['PUT'],
            'delete' => ['DELETE'],
        ];
    }

    public function testHeadIsServedWithoutBody(): void
    {
        $response = $this->process('/css/ok.css', null, 'HEAD');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame(
            ContentType::CSS->withCharset(),
            $response->getHeaderLine('Content-Type')
        );
    }

    private function createFile(string $relativePath, string $content): void
    {
        $file = $this->directory . '/' . $relativePath;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);
    }

    /**
     * `/.well-known/` (RFC 8615) is the one hidden directory with a standard
     * meaning: `security.txt`, `assetlinks.json`, etc.
     */
    #[DataProvider('wellKnownFilesProvider')]
    public function testServesFilesInsideTheWellKnownDirectory(string $path, string $body): void
    {
        $this->createFile($path, $body);

        $this->assertServed($this->process('/' . $path), $body);
    }

    public static function wellKnownFilesProvider(): array
    {
        return [
            'security-txt' => ['.well-known/security.txt', 'Contact: x'],
            'assetlinks' => ['.well-known/assetlinks.json', '[]'],
            'nested' => ['.well-known/pki-validation/file.txt', 'v'],
        ];
    }

    /**
     * Only the exact name, only as the first segment, and it must not
     * become a way around the other rules.
     */
    #[DataProvider('wellKnownAbuseProvider')]
    public function testWellKnownDoesNotOpenAnythingElse(string $path): void
    {
        $this->createFile('.well-known/ok.txt', 'ok');
        $this->createFile('.well-known/.hidden.txt', 'hidden');
        $this->createFile('css/.well-known/ok.txt', 'nested');
        file_put_contents($this->base . '/static/outside-of-well-known.txt', 'x');

        try {
            $this->assertNotServed($this->process($path));
        } finally {
            unlink($this->base . '/static/outside-of-well-known.txt');
        }
    }

    public static function wellKnownAbuseProvider(): array
    {
        return [
            'hidden-file-inside' => ['/.well-known/.hidden.txt'],
            'parent-directory-after' => ['/.well-known/../outside-of-well-known.txt'],
            'parent-directory-outside' => ['/.well-known/../../outside.css'],
            'not-the-first-segment' => ['/css/.well-known/ok.txt'],
            'similar-name' => ['/.well-known-x/ok.txt'],
            'different-case' => ['/.WELL-KNOWN/ok.txt'],
            'hidden-sibling' => ['/.well-known.txt'],
        ];
    }
}
