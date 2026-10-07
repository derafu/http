<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Factory;

use Derafu\Http\Contract\ProblemDetailInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Exception\BadRequestException;
use Derafu\Http\Exception\TooManyRequestsException;
use Derafu\Http\Factory\ProblemFactory;
use Derafu\Http\Factory\SafeThrowableFactory;
use Derafu\Http\Request;
use Derafu\Http\ValueObject\ProblemDetail;
use Derafu\Http\ValueObject\SafeThrowable;
use Derafu\Translation\Exception\Core\TranslatableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use TypeError;

/**
 * The `detail` of the problem is the message of the throwable, translated when
 * the throwable knows how to be translated.
 *
 * `trans()` only exists on translatable throwables: any other one must keep
 * its own message.
 */
#[CoversClass(ProblemFactory::class)]
#[UsesClass(SafeThrowableFactory::class)]
#[UsesClass(Request::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
#[UsesClass(HttpStatus::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(TooManyRequestsException::class)]
final class ProblemFactoryTest extends TestCase
{
    private function translator(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'Resource not found.' => 'Recurso no encontrado.',
            'Internal Server Error' => 'Error interno del servidor',
            'Too Many Requests' => 'Demasiadas solicitudes',
        ], 'es', 'errors');

        return $translator;
    }

    private function problem(Throwable $throwable, ?TranslatorInterface $translator): ProblemDetailInterface
    {
        $params = new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.debug' => false,
            'kernel.project_dir' => dirname(__DIR__, 3),
        ]);

        $factory = new ProblemFactory($params, new SafeThrowableFactory($params), $translator);

        return $factory->create($throwable, new Request('GET', 'http://localhost/'));
    }

    private function detail(Throwable $throwable, ?TranslatorInterface $translator): string
    {
        return $this->problem($throwable, $translator)->getDetail();
    }

    private function title(Throwable $throwable, ?TranslatorInterface $translator): string
    {
        return $this->problem($throwable, $translator)->getTitle();
    }

    public function testATranslatableThrowableIsTranslated(): void
    {
        $this->assertSame(
            'Recurso no encontrado.',
            $this->detail(new TranslatableException('Resource not found.'), $this->translator())
        );
    }

    public function testAThrowableThatOnlyImplementsTheSymfonyContractIsTranslated(): void
    {
        $throwable = new class ('Original.') extends RuntimeException implements TranslatableInterface {
            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return 'Traducido.';
            }
        };

        $this->assertSame('Traducido.', $this->detail($throwable, $this->translator()));
    }

    public function testAThrowableThatIsNotTranslatableKeepsItsMessage(): void
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());

        $this->assertSame(
            'Resource not found.',
            $this->detail(new RuntimeException('Resource not found.'), $translator)
        );
        $this->assertSame(
            'Not an exception.',
            $this->detail(new TypeError('Not an exception.'), $translator)
        );
    }

    public function testAMessageWithoutTranslationFallsBackToTheOriginal(): void
    {
        $this->assertSame(
            'Something else.',
            $this->detail(new TranslatableException('Something else.'), $this->translator())
        );
    }

    public function testWithoutATranslatorTheMessageIsKept(): void
    {
        $this->assertSame(
            'Resource not found.',
            $this->detail(new TranslatableException('Resource not found.'), null)
        );
    }

    public function testTheTitleOfAProblemWithoutTypeIsTheTranslatedReasonPhraseOfTheStatus(): void
    {
        $this->assertSame(
            'Error interno del servidor',
            $this->title(new RuntimeException('Failure.'), $this->translator())
        );
    }

    public function testTheTitleOfAnHttpExceptionIsTranslated(): void
    {
        $this->assertSame(
            'Demasiadas solicitudes',
            $this->title(new TooManyRequestsException(), $this->translator())
        );
    }

    public function testABadRequestIsAProblemOf400(): void
    {
        $problem = $this->problem(new BadRequestException('Image is required.'), $this->translator());

        $this->assertSame(HttpStatus::BAD_REQUEST, $problem->getHttpStatus());
        $this->assertSame('Bad Request', $problem->getTitle());
        $this->assertSame('Image is required.', $problem->getDetail());
    }

    public function testATitleWithoutTranslationKeepsTheOriginal(): void
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());

        $this->assertSame('Internal Server Error', $this->title(new RuntimeException('Failure.'), $translator));
        $this->assertSame('Too Many Requests', $this->title(new TooManyRequestsException(), $translator));
    }

    public function testWithoutATranslatorTheTitleIsKept(): void
    {
        $this->assertSame('Internal Server Error', $this->title(new RuntimeException('Failure.'), null));
        $this->assertSame('Too Many Requests', $this->title(new TooManyRequestsException(), null));
    }
}
