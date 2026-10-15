<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Exception;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Exception\BadGatewayException;
use Derafu\Http\Exception\BadRequestException;
use Derafu\Http\Exception\ConflictException;
use Derafu\Http\Exception\ForbiddenException;
use Derafu\Http\Exception\GatewayTimeoutException;
use Derafu\Http\Exception\HttpException;
use Derafu\Http\Exception\MethodNotAllowedException;
use Derafu\Http\Exception\NotFoundException;
use Derafu\Http\Exception\PayloadTooLargeException;
use Derafu\Http\Exception\ServiceUnavailableException;
use Derafu\Http\Exception\TooManyRequestsException;
use Derafu\Http\Exception\UnauthorizedException;
use Derafu\Http\Exception\UnprocessableContentException;
use Derafu\Http\Exception\UnsupportedMediaTypeException;
use Derafu\Http\Trait\HttpExceptionTrait;
use Derafu\Http\Translation\HttpTranslationResourceProvider;
use Derafu\Translation\Contract\HeadersAwareInterface;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\TranslatorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Every exception that is an HTTP status is translatable, says its status, its
 * title and the page that explains it, carries the headers and the context of
 * the response, and has a default message in Spanish too.
 */
#[CoversClass(HttpException::class)]
#[CoversTrait(HttpExceptionTrait::class)]
#[CoversClass(UnauthorizedException::class)]
#[CoversClass(ForbiddenException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(MethodNotAllowedException::class)]
#[CoversClass(ConflictException::class)]
#[CoversClass(PayloadTooLargeException::class)]
#[CoversClass(UnsupportedMediaTypeException::class)]
#[CoversClass(UnprocessableContentException::class)]
#[CoversClass(BadGatewayException::class)]
#[CoversClass(ServiceUnavailableException::class)]
#[CoversClass(GatewayTimeoutException::class)]
#[CoversClass(BadRequestException::class)]
#[CoversClass(TooManyRequestsException::class)]
#[UsesClass(HttpStatus::class)]
#[UsesClass(HttpTranslationResourceProvider::class)]
final class HttpExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{class-string<HttpExceptionInterface&Throwable>, HttpStatus, string, string, string}>
     */
    public static function exceptionsProvider(): array
    {
        return [
            'UnauthorizedException' => [UnauthorizedException::class, HttpStatus::UNAUTHORIZED, 'Unauthorized', 'Unauthorized.', 'No autorizado.'],
            'ForbiddenException' => [ForbiddenException::class, HttpStatus::FORBIDDEN, 'Forbidden', 'Forbidden.', 'Prohibido.'],
            'NotFoundException' => [NotFoundException::class, HttpStatus::NOT_FOUND, 'Not Found', 'Not found.', 'No encontrado.'],
            'MethodNotAllowedException' => [MethodNotAllowedException::class, HttpStatus::METHOD_NOT_ALLOWED, 'Method Not Allowed', 'Method not allowed.', 'Método no permitido.'],
            'ConflictException' => [ConflictException::class, HttpStatus::CONFLICT, 'Conflict', 'Conflict.', 'Conflicto.'],
            'PayloadTooLargeException' => [PayloadTooLargeException::class, HttpStatus::PAYLOAD_TOO_LARGE, 'Payload Too Large', 'Payload too large.', 'Contenido demasiado grande.'],
            'UnsupportedMediaTypeException' => [UnsupportedMediaTypeException::class, HttpStatus::UNSUPPORTED_MEDIA_TYPE, 'Unsupported Media Type', 'Unsupported media type.', 'Tipo de medio no soportado.'],
            'UnprocessableContentException' => [UnprocessableContentException::class, HttpStatus::UNPROCESSABLE_CONTENT, 'Unprocessable Content', 'Unprocessable content.', 'Contenido no procesable.'],
            'BadGatewayException' => [BadGatewayException::class, HttpStatus::BAD_GATEWAY, 'Bad Gateway', 'Bad gateway.', 'Puerta de enlace incorrecta.'],
            'ServiceUnavailableException' => [ServiceUnavailableException::class, HttpStatus::SERVICE_UNAVAILABLE, 'Service Unavailable', 'Service unavailable.', 'Servicio no disponible.'],
            'GatewayTimeoutException' => [GatewayTimeoutException::class, HttpStatus::GATEWAY_TIMEOUT, 'Gateway Timeout', 'Gateway timeout.', 'Tiempo de espera de la puerta de enlace agotado.'],
            'BadRequestException' => [BadRequestException::class, HttpStatus::BAD_REQUEST, 'Bad Request', 'Bad request.', 'Solicitud incorrecta.'],
            'TooManyRequestsException' => [TooManyRequestsException::class, HttpStatus::TOO_MANY_REQUESTS, 'Too Many Requests', 'Too many requests.', 'Demasiadas solicitudes.'],
        ];
    }

    /**
     * @return array<string, array{class-string<HttpExceptionInterface&Throwable>, HttpStatus, string}>
     */
    public static function statusesProvider(): array
    {
        return array_map(fn (array $row) => array_slice($row, 0, 3), self::exceptionsProvider());
    }

    /**
     * @return array<string, array{class-string<HttpExceptionInterface&Throwable>, string, string}>
     */
    public static function messagesProvider(): array
    {
        return array_map(fn (array $row) => [$row[0], $row[3], $row[4]], self::exceptionsProvider());
    }

    /**
     * @return array<string, array{class-string<HttpExceptionInterface&Throwable>}>
     */
    public static function classesProvider(): array
    {
        return array_map(fn (array $row) => [$row[0]], self::exceptionsProvider());
    }

    /**
     * @param class-string<HttpExceptionInterface&Throwable> $class
     */
    #[DataProvider('statusesProvider')]
    public function testItSaysItsStatusItsTitleAndItsPage(
        string $class,
        HttpStatus $status,
        string $title,
    ): void {
        $exception = new $class();

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertInstanceOf(HeadersAwareInterface::class, $exception);
        $this->assertSame($status, $exception->getStatus());
        $this->assertSame($title, $exception->getTitle());
        $this->assertStringStartsWith('https://', $exception->getUriReference());
    }

    /**
     * @param class-string<HttpExceptionInterface&Throwable> $class
     */
    #[DataProvider('messagesProvider')]
    public function testTheDefaultMessageIsTheOneOfTheStatusAndItIsTranslated(
        string $class,
        string $message,
        string $spanish,
    ): void {
        $exception = new $class();

        $this->assertSame($message, $exception->getMessage());

        $translator = TranslatorFactory::create('es', ['en'], [new HttpTranslationResourceProvider()]);
        $this->assertSame($spanish, $exception->trans($translator));
    }

    /**
     * @param class-string<HttpExceptionInterface&Throwable> $class
     */
    #[DataProvider('classesProvider')]
    public function testItCarriesTheMessageTheCodeThePreviousTheHeadersAndTheContext(
        string $class,
    ): void {
        $previous = new RuntimeException('Cause.');
        $exception = new $class(
            ['The field "{field}" is not valid.', 'field' => 'rut'],
            7,
            $previous,
            ['Retry-After' => '30'],
            ['errors' => ['rut' => 'Wrong digit.']]
        );

        $this->assertSame('The field "rut" is not valid.', $exception->getMessage());
        $this->assertSame(7, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(['Retry-After' => '30'], $exception->getHeaders());
        $this->assertSame(['errors' => ['rut' => 'Wrong digit.']], $exception->getContext());
    }

    /**
     * @param class-string<HttpExceptionInterface&Throwable> $class
     */
    #[DataProvider('classesProvider')]
    public function testWithoutHeadersAndContextTheyAreEmpty(string $class): void
    {
        $exception = new $class();

        $this->assertSame([], $exception->getHeaders());
        $this->assertSame([], $exception->getContext());
    }

    /**
     * @param class-string<HttpExceptionInterface&Throwable> $class
     */
    #[DataProvider('classesProvider')]
    public function testTheHeadersCanBeChangedAfterwards(string $class): void
    {
        $exception = new $class();

        $this->assertSame($exception, $exception->setHeaders(['Allow' => 'GET, POST']));
        $this->assertSame(['Allow' => 'GET, POST'], $exception->getHeaders());
    }

    public function testTheTooManyRequestsKeepsItsPageOfTheRfc(): void
    {
        $this->assertSame(
            'https://tools.ietf.org/html/rfc6585#section-4',
            (new TooManyRequestsException())->getUriReference()
        );
    }

    public function testTheOthersHaveThePageOfTheStatusInMdn(): void
    {
        $this->assertSame(
            'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/422',
            (new UnprocessableContentException())->getUriReference()
        );
        $this->assertSame(
            'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/400',
            (new BadRequestException())->getUriReference()
        );
    }

    public function testAnExceptionOfAnotherFamilyCanBeHttpWithTheTrait(): void
    {
        $exception = new BadRequestException();

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertContains(HttpExceptionTrait::class, class_uses($exception));
    }

    public function testAnHttpExceptionOnlyNeedsToSayItsStatus(): void
    {
        $exception = new class ('Teapot.') extends HttpException {
            public function getStatus(): HttpStatus
            {
                return HttpStatus::NOT_FOUND;
            }
        };

        $this->assertSame('Teapot.', $exception->getMessage());
        $this->assertSame('Not Found', $exception->getTitle());
        $this->assertSame([], $exception->getContext());
    }
}
