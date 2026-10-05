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

use Derafu\Http\Contract\SafeThrowableInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Request;
use Derafu\Http\ValueObject\ProblemDetail;
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
final class ProblemDetailTest extends TestCase
{
    private function problem(string $type, ?string $title): ProblemDetail
    {
        return new ProblemDetail(
            httpStatus: HttpStatus::NOT_FOUND,
            detail: 'Detail.',
            request: new Request('GET', 'http://localhost/'),
            throwable: $this->createStub(SafeThrowableInterface::class),
            timestamp: '2026-01-01T00:00:00+00:00',
            environment: 'test',
            type: $type,
            title: $title,
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
}
