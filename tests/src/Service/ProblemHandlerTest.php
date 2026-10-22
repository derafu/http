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

use Derafu\Http\Enum\ContentType;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Exception\ResponseSerializationException;
use Derafu\Http\Request;
use Derafu\Http\Response;
use Derafu\Http\Service\Dispatcher;
use Derafu\Http\Service\ProblemHandler;
use Derafu\Http\Service\RequestStack;
use Derafu\Http\Service\ResponseNormalizer;
use Derafu\Http\ValueObject\ProblemDetail;
use Derafu\Http\ValueObject\SafeThrowable;
use Derafu\TestsHttp\Fixture\EchoRenderer;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Invoker\Invoker;
use Nyholm\Psr7\Response as NyholmResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * The error pages that the handler is given are checked when it is created: a
 * configuration that is wrong is not ignored. What the handler does with the
 * pages is tested with the whole request in `HttpTest`.
 */
#[CoversClass(ProblemHandler::class)]
#[UsesClass(ResponseNormalizer::class)]
#[UsesClass(ResponseSerializationException::class)]
#[UsesClass(ContentType::class)]
#[UsesClass(Response::class)]
#[UsesClass(Request::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(HttpStatus::class)]
#[UsesClass(Dispatcher::class)]
#[UsesClass(RequestStack::class)]
#[UsesClass(SafeThrowable::class)]
class ProblemHandlerTest extends TestCase
{
    private function dispatcher(): Dispatcher
    {
        return new Dispatcher(new Invoker(), new EchoRenderer(), new RequestStack());
    }

    /**
     * Creates the handler through reflection, which does not check the types of
     * the pages: the ones that are not valid are what is tested.
     *
     * @param array<int|string, mixed> $pages
     */
    private function handler(array $pages): ProblemHandler
    {
        $handler = (new ReflectionClass(ProblemHandler::class))
            ->newInstance($this->dispatcher(), $pages);
        $this->assertInstanceOf(ProblemHandler::class, $handler);

        return $handler;
    }

    public function testAcceptsTheStatusesTheDefaultAndAClosure(): void
    {
        $handler = $this->handler([
            400 => '/templates/error400.html.twig',
            404 => '/templates/error404.html.twig',
            599 => '/templates/error599.html.twig',
            '500' => 'App\Controller\ErrorController::show',
            'default' => fn (): string => 'page',
        ]);

        $this->assertInstanceOf(ProblemHandler::class, $handler);
    }

    public function testAcceptsNoPages(): void
    {
        $this->assertInstanceOf(ProblemHandler::class, $this->handler([]));
    }

    /**
     * @return array<string, array{array<int|string, mixed>, string}>
     */
    public static function invalidPages(): array
    {
        return [
            'a key that is not a status' => [['not-a-status' => '/error.html.twig'], 'not-a-status'],
            'a status under 400' => [[200 => '/error.html.twig'], '200'],
            'the last status under 400' => [[399 => '/error.html.twig'], '399'],
            'a status with zeros' => [['0404' => '/error.html.twig'], '0404'],
            'a status with more digits' => [['4040' => '/error.html.twig'], '4040'],
            'the first status over 599' => [[600 => '/error.html.twig'], '600'],
            'an empty handler' => [['default' => ''], 'default'],
            'a handler of spaces' => [[404 => '   '], '404'],
            'a handler that is not text' => [['default' => 123], 'default'],
            'a handler that is a list' => [[404 => ['a', 'b']], '404'],
        ];
    }

    /**
     * @param array<int|string, mixed> $pages
     */
    #[DataProvider('invalidPages')]
    public function testRejectsAPageThatIsNotValid(array $pages, string $key): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"' . preg_quote($key, '/') . '"/');

        $this->handler($pages);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private function problem(string $accept = 'text/html', bool $debug = false, array $headers = [], string $path = '/x'): ProblemDetail
    {
        return new ProblemDetail(
            httpStatus: HttpStatus::NOT_FOUND,
            detail: 'Detail.',
            request: new Request('GET', 'http://localhost' . $path, ['Accept' => $accept]),
            throwable: new SafeThrowable('RuntimeException', 0, 'Boom.', 'project_dir:src/App.php', 9, [], null),
            timestamp: '2026-01-01T00:00:00+00:00',
            environment: 'test',
            headers: $headers,
            debug: $debug
        );
    }

    private function handlerReturning(mixed $page): ProblemHandler
    {
        return new ProblemHandler($this->dispatcher(), [404 => fn (): mixed => $page]);
    }

    /**
     * @return array<string, array{mixed, non-empty-string, string}>
     */
    public static function provideWhatAPageReturns(): array
    {
        return [
            'a string' => ['<p>Not found</p>', 'text/html', '<p>Not found</p>'],
            'null' => [null, 'text/html', ''],
            'a number' => [404, 'text/html', '404'],
            'an array' => [['error' => 'x'], 'application/json', '{"error":"x"}'],
        ];
    }

    /**
     * @param non-empty-string $type
     */
    #[DataProvider('provideWhatAPageReturns')]
    public function testWhatAPageReturnsIsSentWithTheStatusOfTheError(mixed $page, string $type, string $body): void
    {
        $response = $this->handlerReturning($page)->handle($this->problem());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringStartsWith($type, $response->getHeaderLine('Content-Type'));
        $this->assertSame($body, (string) $response->getBody());
    }

    public function testAResponseOfThePageIsSentAsItIs(): void
    {
        $page = (new Response())->asText('mine', ContentType::PLAIN)->withHttpStatus(HttpStatus::GONE);

        $response = $this->handlerReturning($page)->handle($this->problem());

        $this->assertSame(410, $response->getStatusCode());
        $this->assertSame('mine', (string) $response->getBody());
    }

    public function testAPageThatCanNotBeRenderedFallsBackToMarkdown(): void
    {
        foreach ([new NyholmResponse(200, [], 'x'), ["\xB1\x31"]] as $page) {
            $response = $this->handlerReturning($page)->handle($this->problem());

            $this->assertSame(404, $response->getStatusCode());
            $this->assertStringStartsWith('text/markdown', $response->getHeaderLine('Content-Type'));
        }
    }

    public function testAJsonRequestGetsTheProblemAsJson(): void
    {
        $response = (new ProblemHandler($this->dispatcher()))->handle($this->problem('application/json'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertSame(404, $body['status']);
        $this->assertSame('Detail.', $body['detail']);
    }

    public function testTheHeadersOfTheProblemAreInTheResponseInAnyFormat(): void
    {
        $handler = new ProblemHandler($this->dispatcher());

        foreach (['application/json', 'text/html'] as $accept) {
            $response = $handler->handle($this->problem($accept, false, ['Retry-After' => '30', 'Link' => ['</a>', '</b>']]));

            $this->assertSame('30', $response->getHeaderLine('Retry-After'));
            $this->assertSame(['</a>', '</b>'], $response->getHeader('Link'));
        }

        $response = $handler->handle($this->problem(headers: ['Retry-After' => '30'], path: '/notes.md'));
        $this->assertSame('30', $response->getHeaderLine('Retry-After'));
    }

    public function testAMarkdownRequestGetsTheTechnicalReport(): void
    {
        // The format is markdown by the extension of the path.
        $response = (new ProblemHandler($this->dispatcher()))->handle($this->problem(path: '/notes.md'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringStartsWith('text/markdown', $response->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('# An Error Occurred', (string) $response->getBody());
    }

    public function testHtmlWithoutPagesIsTheTechnicalReportInMarkdown(): void
    {
        $response = (new ProblemHandler($this->dispatcher()))->handle($this->problem('text/html'));

        $this->assertStringStartsWith('text/markdown', $response->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('# An Error Occurred', (string) $response->getBody());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideFormatsThatAreNotHtml(): array
    {
        return [
            'plain by the header' => ['/page', 'text/plain'],
            'an image by the extension' => ['/img/x.png', 'image/png'],
            'a stylesheet by the extension' => ['/x.css', 'text/css'],
        ];
    }

    #[DataProvider('provideFormatsThatAreNotHtml')]
    public function testThePageOfAnErrorIsHtmlWhateverFormatTheRequestHas(string $path, string $accept): void
    {
        $handler = new ProblemHandler($this->dispatcher(), [404 => fn (): string => '<p>Not found</p>']);

        $response = $handler->handle($this->problem($accept, path: $path));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(ContentType::HTML->withCharset(), $response->getHeaderLine('Content-Type'));
        $this->assertSame('<p>Not found</p>', (string) $response->getBody());
    }

    public function testAPageThatReturnsAnArrayIsJson(): void
    {
        $handler = new ProblemHandler($this->dispatcher(), [404 => fn (): array => ['error' => 'x']]);

        $response = $handler->handle($this->problem('text/html'));

        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"error":"x"}', (string) $response->getBody());
    }

    public function testAPageIsATemplateThatTheRendererRenders(): void
    {
        $handler = new ProblemHandler($this->dispatcher(), ['default' => __FILE__]);

        $response = $handler->handle($this->problem('text/html'));

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertSame(__FILE__, $body['template']);
        $this->assertIsArray($body['data']);
        $this->assertContains('context', $body['data']);
    }

    public function testTheDefaultPageIsForTheStatusesThatHaveNone(): void
    {
        $handler = new ProblemHandler($this->dispatcher(), [
            500 => fn (): string => 'page 500',
            'default' => fn (): string => 'default page',
        ]);

        $this->assertSame('default page', (string) $handler->handle($this->problem())->getBody());
    }

    public function testThePageOfTheStatusIsBeforeTheDefaultOne(): void
    {
        $handler = new ProblemHandler($this->dispatcher(), [
            404 => fn (): string => 'page 404',
            'default' => fn (): string => 'default page',
        ]);

        $this->assertSame('page 404', (string) $handler->handle($this->problem())->getBody());
    }

    public function testWhenThePageOfTheStatusFailsTheDefaultOneIsTried(): void
    {
        $handler = new ProblemHandler($this->dispatcher(), [
            404 => function (): never {
                throw new RuntimeException('The page broke.');
            },
            'default' => fn (): string => 'default page',
        ]);

        $response = $handler->handle($this->problem());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('default page', (string) $response->getBody());
    }

    public function testWhenEveryPageFailsTheReportIsMarkdownAndSaysWhyOnlyInDebug(): void
    {
        $handler = new ProblemHandler($this->dispatcher(), [
            404 => function (): never {
                throw new RuntimeException('The page 404 broke.');
            },
            'default' => function (): never {
                throw new RuntimeException('The default page broke.');
            },
        ]);

        $debug = (string) $handler->handle($this->problem('text/html', true))->getBody();
        $quiet = (string) $handler->handle($this->problem('text/html', false))->getBody();

        $this->assertStringContainsString("## Error pages that failed\n\n", $debug);
        $this->assertStringContainsString('- `404`: RuntimeException: The page 404 broke.', $debug);
        $this->assertStringContainsString('- `default`: RuntimeException: The default page broke.', $debug);
        $this->assertStringNotContainsString('Error pages that failed', $quiet);
        $this->assertStringStartsWith('# An Error Occurred', $quiet);
    }
}
