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
use Derafu\Http\Exception\BadRequestException;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A request that can not be attended because of what the client sent (a missing
 * field, a file that is not there) is a 400, not a 500, and it is still the
 * invalid argument that the code that does not know about HTTP can catch.
 */
#[CoversClass(BadRequestException::class)]
#[UsesClass(HttpStatus::class)]
final class BadRequestExceptionTest extends TestCase
{
    public function testItIsAnHttpExceptionThatIsABadRequest(): void
    {
        $exception = new BadRequestException();

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(HttpStatus::BAD_REQUEST, $exception->getStatus());
        $this->assertSame('Bad Request', $exception->getTitle());
        $this->assertSame(
            'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/400',
            $exception->getUriReference()
        );
        $this->assertSame([], $exception->getContext());
        $this->assertSame([], $exception->getHeaders());
    }

    public function testItIsAnInvalidArgumentThatIsTranslatable(): void
    {
        $exception = new BadRequestException();

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
    }

    public function testTheMessageIsGenericByDefaultAndTheOneOfTheCallerWhenGiven(): void
    {
        $this->assertSame('Bad request.', (new BadRequestException())->getMessage());
        $this->assertSame(
            'Image is required.',
            (new BadRequestException('Image is required.'))->getMessage()
        );
        $this->assertSame(
            'The field "x" is required.',
            (new BadRequestException(['The field "{field}" is required.', 'field' => 'x']))->getMessage()
        );
    }

    public function testItKeepsTheCodeAndThePrevious(): void
    {
        $previous = new RuntimeException('Cause.');
        $exception = new BadRequestException('Image is required.', 7, $previous);

        $this->assertSame(7, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
