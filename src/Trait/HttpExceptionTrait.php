<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Trait;

use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Trait\HeadersAwareTrait;

/**
 * What every HTTP exception has in common: the title and the URI reference that
 * come from its status, the context and the headers of the response.
 *
 * The class that uses it only says its status. It is a trait, and not only the
 * base class {@see \Derafu\Http\Exception\HttpException}, so an exception that
 * must be of another family (`BadRequestException` is an invalid argument) is
 * HTTP too.
 *
 * @see \Derafu\Http\Contract\HttpExceptionInterface
 */
trait HttpExceptionTrait
{
    use HeadersAwareTrait;

    /**
     * Additional context of the exception (for example, the errors of each field
     * of a `422`). It is part of the problem details of the response.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * {@inheritDoc}
     */
    abstract public function getStatus(): HttpStatus;

    /**
     * {@inheritDoc}
     *
     * By default, the page of the status in MDN.
     */
    public function getUriReference(): string
    {
        return 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/'
            . $this->getStatus()->value
        ;
    }

    /**
     * {@inheritDoc}
     *
     * By default, the reason phrase of the status.
     */
    public function getTitle(): string
    {
        return $this->getStatus()->getReasonPhrase();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Sets the headers and the context of the response. It is called by the
     * constructor of the exception.
     *
     * @param array<string,string|list<string>> $headers
     * @param array<string, mixed> $context
     */
    protected function setHttpData(array $headers, array $context): void
    {
        $this->headers = $headers;
        $this->context = $context;
    }
}
