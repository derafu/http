<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Translation;

use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Exception\TooManyRequestsException;
use Derafu\Http\Translation\HttpTranslationResourceProvider;
use Derafu\Translation\Lint\TranslationAudit;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The package is translated: every message has its Spanish translation, every
 * message can be checked by reading the code, and every exception that the
 * package throws is translatable. The catalogue has nothing the code does not
 * use, except what the code does not write as a message: the reason phrases of
 * the HTTP statuses (the titles of the problems), which the code takes from the
 * `HttpStatus` enum, and these are checked here against it.
 */
#[CoversClass(HttpTranslationResourceProvider::class)]
#[UsesClass(HttpStatus::class)]
#[UsesClass(TooManyRequestsException::class)]
final class HttpMessagesTest extends TestCase
{
    public function testThePackageIsTranslated(): void
    {
        $report = (new TranslationAudit())->audit(
            dirname(__DIR__, 3) . '/src',
            new HttpTranslationResourceProvider()
        );

        // Finding nothing would look like a clean result.
        $this->assertFalse($report->nothingFound);
        $this->assertSame([], $report->describe($report->dynamicMessages));
        $this->assertSame([], $report->describe($report->missingTranslations));
        $this->assertSame([], $report->describe($report->notTranslatable));

        // The only entries that the code does not use as a message are the
        // reason phrases of the statuses.
        $phrases = array_map(fn (HttpStatus $status) => sprintf('"%s" [errors]', $status->getReasonPhrase()), HttpStatus::cases());
        $this->assertEqualsCanonicalizing(
            array_values(array_unique($phrases)),
            $report->describe($report->notUsedBySources)
        );
    }

    public function testEveryReasonPhraseOfAStatusHasATranslation(): void
    {
        $catalogue = TranslatorFactory::create('es', [], [new HttpTranslationResourceProvider()])->getCatalogue('es');

        foreach (HttpStatus::cases() as $status) {
            $this->assertTrue(
                $catalogue->has($status->getReasonPhrase(), 'errors'),
                sprintf('"%s" (%d) has no translation.', $status->getReasonPhrase(), $status->value)
            );
        }
    }

    public function testTheTitleOfAnExceptionOfThePackageHasATranslation(): void
    {
        $catalogue = TranslatorFactory::create('es', [], [new HttpTranslationResourceProvider()])->getCatalogue('es');

        $this->assertTrue($catalogue->has((new TooManyRequestsException())->getTitle(), 'errors'));
    }
}
