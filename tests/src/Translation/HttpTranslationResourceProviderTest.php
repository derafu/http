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
use Derafu\Http\Translation\HttpTranslationResourceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The reason phrases of the HTTP statuses have a Spanish translation, so the
 * title of a problem can be translated.
 */
#[CoversClass(HttpTranslationResourceProvider::class)]
#[UsesClass(HttpStatus::class)]
final class HttpTranslationResourceProviderTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function catalog(): array
    {
        $directories = [...(new HttpTranslationResourceProvider())->getDirectories()];

        $this->assertCount(1, $directories);

        $file = $directories[0] . '/errors+intl-icu.es.php';
        $this->assertFileExists($file);

        return require $file;
    }

    public function testEveryReasonPhraseOfAStatusIsTranslated(): void
    {
        $catalog = $this->catalog();

        foreach (HttpStatus::cases() as $status) {
            $phrase = $status->getReasonPhrase();

            $this->assertArrayHasKey($phrase, $catalog, sprintf('"%s" (%d) has no translation.', $phrase, $status->value));
            $this->assertNotSame('', $catalog[$phrase]);
        }
    }
}
