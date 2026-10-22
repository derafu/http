<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Enum;

use Derafu\Http\Enum\HttpStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every status has its reason phrase and belongs to one class: informational,
 * successful, redirection, client error or server error.
 */
#[CoversClass(HttpStatus::class)]
final class HttpStatusTest extends TestCase
{
    /**
     * @return array<string, array{HttpStatus}>
     */
    public static function provideStatuses(): array
    {
        $cases = [];
        foreach (HttpStatus::cases() as $status) {
            $cases[$status->name] = [$status];
        }

        return $cases;
    }

    #[DataProvider('provideStatuses')]
    public function testEveryStatusHasAReasonPhrase(HttpStatus $status): void
    {
        $this->assertNotSame('', $status->getReasonPhrase());
    }

    #[DataProvider('provideStatuses')]
    public function testEveryStatusBelongsToExactlyOneClass(HttpStatus $status): void
    {
        $classes = array_filter([
            $status->isInformational(),
            $status->isSuccessful(),
            $status->isRedirection(),
            $status->isClientError(),
            $status->isServerError(),
        ]);

        $this->assertCount(1, $classes);
    }

    #[DataProvider('provideStatuses')]
    public function testAnErrorIsAClientErrorOrAServerError(HttpStatus $status): void
    {
        $this->assertSame($status->isClientError() || $status->isServerError(), $status->isError());
    }

    #[DataProvider('provideStatuses')]
    public function testTheClassIsTheOneOfItsHundred(HttpStatus $status): void
    {
        $hundred = intdiv($status->value, 100);

        $this->assertSame($hundred === 1, $status->isInformational());
        $this->assertSame($hundred === 2, $status->isSuccessful());
        $this->assertSame($hundred === 3, $status->isRedirection());
        $this->assertSame($hundred === 4, $status->isClientError());
        $this->assertSame($hundred === 5, $status->isServerError());
    }

    public function testTheReasonPhrasesAreTheStandardOnes(): void
    {
        $this->assertSame('OK', HttpStatus::OK->getReasonPhrase());
        $this->assertSame('Not Found', HttpStatus::NOT_FOUND->getReasonPhrase());
        $this->assertSame('Too Many Requests', HttpStatus::TOO_MANY_REQUESTS->getReasonPhrase());
        $this->assertSame('Unprocessable Content', HttpStatus::UNPROCESSABLE_CONTENT->getReasonPhrase());
        $this->assertSame('Internal Server Error', HttpStatus::INTERNAL_SERVER_ERROR->getReasonPhrase());
    }

    public function testAStatusIsCreatedFromItsNumber(): void
    {
        $this->assertSame(HttpStatus::NOT_FOUND, HttpStatus::from(404));

        // A number that is not a status of the enum is not one.
        $known = array_map(fn (HttpStatus $status): int => $status->value, HttpStatus::cases());
        foreach (range(100, 599) as $code) {
            if (!in_array($code, $known, true)) {
                $this->assertNull(HttpStatus::tryFrom($code));
            }
        }
    }
}
