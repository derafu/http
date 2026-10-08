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

use Derafu\Http\Contract\DispatcherInterface;
use Derafu\Http\Service\ProblemHandler;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The error pages that the handler is given are checked when it is created: a
 * configuration that is wrong is not ignored. What the handler does with the
 * pages is tested with the whole request in `HttpTest`.
 */
#[CoversClass(ProblemHandler::class)]
class ProblemHandlerTest extends TestCase
{
    /**
     * @param array<int|string, mixed> $pages
     */
    private function handler(array $pages): ProblemHandler
    {
        return new ProblemHandler($this->createStub(DispatcherInterface::class), $pages);
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
}
