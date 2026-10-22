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

use Closure;
use Derafu\Http\Request;
use Derafu\Translation\Contract\TranslatableInterface;
use LogicException;
use Mezzio\Authentication\UserInterface;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * What the middlewares put in the request (the route, the session, the flash
 * messages) is not there when they did not run: asking for it is a
 * translatable error that says which one is missing.
 */
#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /**
     * @return array<string, array{Closure, string}>
     */
    public static function missingProvider(): array
    {
        return [
            'route' => [
                fn (Request $request) => $request->route(),
                'Route match not found. Ensure RouterMiddleware is executed before using the $request->route() method.',
            ],
            'session' => [
                fn (Request $request) => $request->session(),
                'Session not found.',
            ],
            'flash messages' => [
                fn (Request $request) => $request->flash(),
                'Flash messages not found.',
            ],
        ];
    }

    #[DataProvider('missingProvider')]
    public function testSomethingThatIsMissingIsATranslatableError(Closure $ask, string $message): void
    {
        $exception = null;
        try {
            $ask(new Request('GET', 'http://localhost/'));
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testAJsonListIsReturnedWithIntegerKeys(): void
    {
        $request = new Request('POST', 'http://localhost/', ['Content-Type' => 'application/json'], '[1,2]');

        $this->assertSame([1, 2], $request->json());
    }

    public function testAFileIsTheOneOfTheField(): void
    {
        $file = new UploadedFile('content', 7, UPLOAD_ERR_OK);
        $request = (new Request('POST', 'http://localhost/'))->withUploadedFiles(['avatar' => $file]);

        $this->assertSame($file, $request->file('avatar'));
        $this->assertNull($request->file('other'));
    }

    public function testAGroupOfFilesIsNotASingleFile(): void
    {
        $group = [new UploadedFile('a', 1, UPLOAD_ERR_OK), new UploadedFile('b', 1, UPLOAD_ERR_OK)];
        $request = (new Request('POST', 'http://localhost/'))->withUploadedFiles(['photos' => $group]);

        $this->assertNull($request->file('photos'));
        $this->assertFalse($request->hasFile('photos'));
        $this->assertSame(['photos' => $group], $request->files());
    }

    public function testTheUserIsTheOneOfTheRequestOrNull(): void
    {
        $user = $this->createStub(UserInterface::class);
        $request = new Request('GET', 'http://localhost/');

        $this->assertNull($request->user());
        $this->assertSame($user, $request->withAttribute(UserInterface::class, $user)->user());
    }

    public function testAUserThatIsNotAUserInterfaceIsATranslatableError(): void
    {
        $request = (new Request('GET', 'http://localhost/'))->withAttribute(UserInterface::class, 'ana');

        $exception = null;
        try {
            $request->user();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(
            'The user of the request must be an instance of Mezzio\Authentication\UserInterface, string given.',
            $exception->getMessage()
        );
    }

    public function testEachWithGivesAnotherRequestAndTheOriginalDoesNotChange(): void
    {
        $original = new Request('GET', 'http://localhost/');

        $changed = [
            $original->withAttribute('k', 'v'),
            $original->withHeader('X-A', 'a'),
            $original->withAddedHeader('X-A', 'a'),
            $original->withQueryParams(['q' => '1']),
            $original->withParsedBody(['b' => '1']),
            $original->withCookieParams(['c' => '1']),
            $original->withUploadedFiles([]),
            $original->withMethod('POST'),
            $original->withUri(new Uri('http://localhost/other')),
            $original->withRequestTarget('/other'),
            $original->withProtocolVersion('2.0'),
            $original->withBody(Stream::create('body')),
        ];

        foreach ($changed as $request) {
            $this->assertNotSame($original, $request);
        }
        $this->assertSame([], $original->getAttributes());
        $this->assertFalse($original->hasHeader('X-A'));
        $this->assertSame([], $original->getQueryParams());
        $this->assertNull($original->getParsedBody());
        $this->assertSame([], $original->getCookieParams());
        $this->assertSame('GET', $original->getMethod());
        $this->assertSame('/', $original->getUri()->getPath());
        $this->assertSame('1.1', $original->getProtocolVersion());
        $this->assertSame('', (string) $original->getBody());
    }

    public function testWithoutHeaderAndWithoutAttributeRemoveThemOnlyInTheCopy(): void
    {
        $original = (new Request('GET', 'http://localhost/', ['X-A' => 'a']))->withAttribute('k', 'v');

        $copy = $original->withoutHeader('X-A')->withoutAttribute('k');

        $this->assertFalse($copy->hasHeader('X-A'));
        $this->assertNull($copy->getAttribute('k'));
        $this->assertTrue($original->hasHeader('X-A'));
        $this->assertSame('v', $original->getAttribute('k'));
    }
}
