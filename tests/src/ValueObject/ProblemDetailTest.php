<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\ValueObject;

use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Request;
use Derafu\Http\ValueObject\ProblemDetail;
use Derafu\Http\ValueObject\SafeThrowable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The title of a problem is the one it is given, or the reason phrase of its
 * HTTP status when it is not given any.
 *
 * Whoever creates the problem decides the language of the title: with
 * `about:blank` the title should be the reason phrase of the status, and it may
 * be localized (RFC 9457, section 4.2.1).
 */
#[CoversClass(ProblemDetail::class)]
#[UsesClass(Request::class)]
#[UsesClass(HttpStatus::class)]
#[UsesClass(SafeThrowable::class)]
final class ProblemDetailTest extends TestCase
{
    private function throwable(): SafeThrowable
    {
        return new SafeThrowable('RuntimeException', 3, 'Boom.', 'project_dir:src/App.php', 9, [], null);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,string|list<string>> $headers
     */
    private function problem(
        string $type,
        ?string $title,
        array $headers = [],
        array $context = [],
        bool $debug = true
    ): ProblemDetail {
        return new ProblemDetail(
            httpStatus: HttpStatus::NOT_FOUND,
            detail: 'Detail.',
            request: new Request('GET', 'http://localhost/users/7'),
            throwable: $this->throwable(),
            timestamp: '2026-01-01T00:00:00+00:00',
            environment: 'test',
            type: $type,
            title: $title,
            context: $context,
            headers: $headers,
            debug: $debug,
        );
    }

    public function testWithoutATitleItIsTheReasonPhraseOfTheStatus(): void
    {
        $this->assertSame('Not Found', $this->problem('about:blank', null)->getTitle());
        $this->assertSame('Not Found', $this->problem('https://example.com/problem', null)->getTitle());
    }

    public function testTheTitleThatIsGivenIsUsedWithAboutBlank(): void
    {
        $this->assertSame('No encontrado', $this->problem('about:blank', 'No encontrado')->getTitle());
    }

    public function testTheTitleThatIsGivenIsUsedWithAnotherType(): void
    {
        $this->assertSame('Recurso', $this->problem('https://example.com/problem', 'Recurso')->getTitle());
    }

    public function testTheHeadersAreInTheMarkdownTextOneByLine(): void
    {
        $text = (string) $this->problem('about:blank', null, [
            'Retry-After' => '30',
            'Link' => ['</a>; rel="next"', '</b>; rel="prev"'],
        ]);

        $this->assertStringContainsString("## Headers\n\n- Retry-After: 30\n- Link: </a>; rel=\"next\", </b>; rel=\"prev\"\n", $text);
    }

    public function testWithoutHeadersThereIsNoSectionOfThem(): void
    {
        $this->assertStringNotContainsString('## Headers', (string) $this->problem('about:blank', null));
    }

    public function testItHasTheDataOfTheProblem(): void
    {
        $problem = $this->problem('https://example.com/problem', 'Title.', ['Retry-After' => '30'], ['id' => 7], false);

        $this->assertSame('https://example.com/problem', $problem->getType());
        $this->assertSame(404, $problem->getStatus());
        $this->assertSame(HttpStatus::NOT_FOUND, $problem->getHttpStatus());
        $this->assertSame('Detail.', $problem->getDetail());
        $this->assertSame('/users/7', $problem->getInstance());
        $this->assertSame('/users/7', $problem->getRequest()->getUri()->getPath());
        $this->assertSame('2026-01-01T00:00:00+00:00', $problem->getTimestamp());
        $this->assertSame('test', $problem->getEnvironment());
        $this->assertFalse($problem->isDebug());
        $this->assertSame(['id' => 7], $problem->getContext());
        $this->assertSame(['Retry-After' => '30'], $problem->getHeaders());
        $this->assertSame('Boom.', $problem->getThrowable()->getMessage());
    }

    public function testTheTextHasTheProblemAndTheEnvironment(): void
    {
        $text = (string) $this->problem('about:blank', null);

        $this->assertStringStartsWith("# An Error Occurred\n\n## HTTP Problem Detail\n\n", $text);
        $this->assertStringContainsString("- Type: `about:blank`.\n", $text);
        $this->assertStringContainsString("- Title: `Not Found`.\n", $text);
        $this->assertStringContainsString("- Status: `404 (Not Found)`.\n", $text);
        $this->assertStringContainsString("- Detail: Detail.\n", $text);
        $this->assertStringContainsString("- Instance: `/users/7`.\n", $text);
        $this->assertStringContainsString("## Environment\n\n- Timestamp: `2026-01-01T00:00:00+00:00`.\n- Environment: `test`.\n", $text);
    }

    public function testTheContextIsInTheTextAsJsonWithoutEscapingSlashesNorUnicode(): void
    {
        $text = (string) $this->problem('about:blank', null, [], ['url' => 'https://a.cl/ñ', 'bad' => "\xB1\x31"]);

        $this->assertStringContainsString("## Context\n\n```json\n", $text);
        $this->assertStringContainsString('"url": "https://a.cl/ñ"', $text);
        // The text that is not UTF-8 is substituted: the report is always given.
        $this->assertStringContainsString("\"bad\": \"\u{FFFD}1\"", $text);
    }

    public function testWithoutContextThereIsNoSectionOfIt(): void
    {
        $this->assertStringNotContainsString('## Context', (string) $this->problem('about:blank', null));
    }

    public function testTheThrowableIsInTheTextOnlyInDebug(): void
    {
        $this->assertStringContainsString("## Throwable\n\n```\nClass: RuntimeException", (string) $this->problem('about:blank', null, debug: true));
        $this->assertStringNotContainsString('## Throwable', (string) $this->problem('about:blank', null, debug: false));
    }

    public function testItIsSerializedToJsonAsAProblemWithExtensions(): void
    {
        $data = json_decode((string) json_encode($this->problem('about:blank', null, ['Retry-After' => '30'], ['id' => 7])), true);

        $this->assertIsArray($data);
        $this->assertSame('about:blank', $data['type']);
        $this->assertSame('Not Found', $data['title']);
        $this->assertSame(404, $data['status']);
        $this->assertSame('Detail.', $data['detail']);
        $this->assertSame('/users/7', $data['instance']);
        $this->assertIsArray($data['extensions']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $data['extensions']['timestamp']);
        $this->assertSame('test', $data['extensions']['environment']);
        $this->assertTrue($data['extensions']['debug']);
        $this->assertSame(['id' => 7], $data['extensions']['context']);
        $this->assertSame(['Retry-After' => '30'], $data['extensions']['headers']);
        $this->assertIsArray($data['extensions']['throwable']);
        $this->assertSame('Boom.', $data['extensions']['throwable']['message']);
    }

    public function testTheJsonHasNoThrowableOutsideOfDebug(): void
    {
        $data = json_decode((string) json_encode($this->problem('about:blank', null, debug: false)), true);

        $this->assertIsArray($data);
        $this->assertIsArray($data['extensions']);
        $this->assertNull($data['extensions']['throwable']);
        $this->assertFalse($data['extensions']['debug']);
    }
}
