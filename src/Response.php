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
use Nyholm\Psr7\Response as NyholmResponse;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * Extended PSR-7 Response implementation.
 *
 * This class extends the Nyholm Response with additional functionality for
 * common response types and content handling.
 */
class Response implements ResponseInterface
{
    private NyholmResponse $nyholmResponse;

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
        $this->nyholmResponse = new NyholmResponse(
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
        return $this->asText(json_encode($data, $flags), ContentType::JSON);
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

    /** {@inheritDoc} */
    public function getStatusCode(): int
    {
        return $this->nyholmResponse->getStatusCode();
    }

    /** {@inheritDoc} */
    public function withStatus($code, $reasonPhrase = ''): static
    {
        $this->nyholmResponse = $this->nyholmResponse->withStatus($code, $reasonPhrase);
        return $this;
    }

    /** {@inheritDoc} */
    public function getReasonPhrase(): string
    {
        return $this->nyholmResponse->getReasonPhrase();
    }

    /** {@inheritDoc} */
    public function getProtocolVersion(): string
    {
        return $this->nyholmResponse->getProtocolVersion();
    }

    /** {@inheritDoc} */
    public function withProtocolVersion($version): static
    {
        $this->nyholmResponse = $this->nyholmResponse->withProtocolVersion($version);
        return $this;
    }

    /** {@inheritDoc} */
    public function getHeaders(): array
    {
        return $this->nyholmResponse->getHeaders();
    }

    /** {@inheritDoc} */
    public function hasHeader($name): bool
    {
        return $this->nyholmResponse->hasHeader($name);
    }

    /** {@inheritDoc} */
    public function getHeader($name): array
    {
        return $this->nyholmResponse->getHeader($name);
    }

    /** {@inheritDoc} */
    public function getHeaderLine($name): string
    {
        return $this->nyholmResponse->getHeaderLine($name);
    }

    /** {@inheritDoc} */
    public function withHeader($name, $value): static
    {
        $this->nyholmResponse = $this->nyholmResponse->withHeader($name, $value);
        return $this;
    }

    /** {@inheritDoc} */
    public function withAddedHeader($name, $value): static
    {
        $this->nyholmResponse = $this->nyholmResponse->withAddedHeader($name, $value);
        return $this;
    }

    /** {@inheritDoc} */
    public function withoutHeader($name): static
    {
        $this->nyholmResponse = $this->nyholmResponse->withoutHeader($name);
        return $this;
    }

    /** {@inheritDoc} */
    public function getBody(): StreamInterface
    {
        return $this->nyholmResponse->getBody();
    }

    /** {@inheritDoc} */
    public function withBody(StreamInterface $body): static
    {
        $this->nyholmResponse = $this->nyholmResponse->withBody($body);
        return $this;
    }
}
