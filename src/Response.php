<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http;

use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Enum\HttpStatus;
use Nyholm\Psr7\Response as PsrResponse;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * Extended PSR-7 Response implementation.
 *
 * This class extends the PSR-7 Response with additional functionality for
 * common response types and content handling.
 */
class Response implements ResponseInterface
{
    private PsrResponse $psrResponse;

    /**
     * @param int $status Status code.
     * @param array $headers Response headers.
     * @param string|resource|StreamInterface|null $body Response body.
     * @param string $version Protocol version.
     * @param string|null $reason Reason phrase (when empty a default will be
     * used based on the status code).
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        ?string $reason = null
    ) {
        $this->psrResponse = new PsrResponse(
            $status,
            $headers,
            $body,
            $version,
            $reason
        );
    }

    /**
     * {@inheritDoc}
     */
    public function withHttpStatus(HttpStatus $status): static
    {
        return $this->withStatus(
            $status->value,
            $status->getReasonPhrase()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function withContentType(ContentType $type): static
    {
        return $this->withHeader(
            'Content-Type',
            $type->isText() ? $type->withCharset() : $type->value
        );
    }

    /**
     * {@inheritDoc}
     */
    public function asText(
        string $data,
        ContentType $type = ContentType::PLAIN
    ): static {
        return $this
            ->withContentType($type)
            ->withBody(Stream::create($data))
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function asHtml(string $html): static
    {
        return $this->asText($html, ContentType::HTML);
    }

    /**
     * {@inheritDoc}
     */
    public function asJson(
        mixed $data,
        int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ): static {
        $encodedData = json_encode($data, $flags | JSON_THROW_ON_ERROR);

        return $this->asText($encodedData, ContentType::JSON);
    }

    /**
     * {@inheritDoc}
     */
    public function redirect(
        string $url,
        HttpStatus $status = HttpStatus::FOUND
    ): static {
        return $this
            ->withHeader('Location', $url)
            ->withHttpStatus($status)
            ->withBody(Stream::create(''))
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return $this->psrResponse->getStatusCode();
    }

    /**
     * {@inheritDoc}
     */
    public function withStatus($code, $reasonPhrase = ''): static
    {
        $this->psrResponse = $this->psrResponse->withStatus($code, $reasonPhrase);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getReasonPhrase(): string
    {
        return $this->psrResponse->getReasonPhrase();
    }

    /**
     * {@inheritDoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->psrResponse->getProtocolVersion();
    }

    /**
     * {@inheritDoc}
     */
    public function withProtocolVersion($version): static
    {
        $this->psrResponse = $this->psrResponse->withProtocolVersion($version);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return $this->psrResponse->getHeaders();
    }

    /**
     * {@inheritDoc}
     */
    public function hasHeader($name): bool
    {
        return $this->psrResponse->hasHeader($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getHeader($name): array
    {
        return $this->psrResponse->getHeader($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderLine($name): string
    {
        return $this->psrResponse->getHeaderLine($name);
    }

    /**
     * {@inheritDoc}
     */
    public function withHeader($name, $value): static
    {
        $this->psrResponse = $this->psrResponse->withHeader($name, $value);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withAddedHeader($name, $value): static
    {
        $this->psrResponse = $this->psrResponse->withAddedHeader($name, $value);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutHeader($name): static
    {
        $this->psrResponse = $this->psrResponse->withoutHeader($name);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(): StreamInterface
    {
        return $this->psrResponse->getBody();
    }

    /**
     * {@inheritDoc}
     */
    public function withBody(StreamInterface $body): static
    {
        $this->psrResponse = $this->psrResponse->withBody($body);
        return $this;
    }
}
