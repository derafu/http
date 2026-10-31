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
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Nyholm\Psr7\Response as PsrResponse;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use SplFileInfo;

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
     * @param array<string,string|list<string>> $headers Response headers.
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
    public function asDownload(
        mixed $source,
        ?string $filename = null,
        ContentType $type = ContentType::OCTET_STREAM,
        bool $deleteAfter = false
    ): static {
        $file = $source instanceof SplFileInfo ? $source : null;

        if ($deleteAfter && $file === null) {
            throw new InvalidArgumentException([
                'A file can only be deleted after it is sent when the source is a file, {type} given.',
                'type' => get_debug_type($source),
            ]);
        }

        $stream = $this->downloadStream($source);

        // The stream keeps the file open: on the systems where this runs it can
        // be deleted now and still be sent, so nothing is left behind whatever
        // happens while sending it.
        if ($file !== null && $deleteAfter && !unlink($file->getPathname())) {
            $stream->close();

            throw new RuntimeException([
                'The file "{path}" could not be deleted.',
                'path' => $file->getPathname(),
            ]);
        }

        $filename ??= $file?->getBasename();

        $response = $this->withContentType($type)->withBody($stream);

        if ($filename !== null) {
            $response = $response->withHeader(
                'Content-Disposition',
                self::contentDisposition($filename)
            );
        }

        // Without a known size the body is sent as it is read, and it cannot be
        // asked for by ranges.
        $size = $stream->getSize();
        if ($size === null) {
            return $response->withoutHeader('Content-Length')->withoutHeader('Accept-Ranges');
        }

        $response = $response->withHeader('Content-Length', (string) $size);

        return $stream->isSeekable()
            ? $response->withHeader('Accept-Ranges', 'bytes')
            : $response->withoutHeader('Accept-Ranges');
    }

    /**
     * Gives the stream of the body of a download. Nothing is read into memory.
     */
    private function downloadStream(mixed $source): StreamInterface
    {
        if ($source instanceof StreamInterface) {
            return $source;
        }

        if (is_string($source)) {
            return Stream::create($source);
        }

        if (is_resource($source) && get_resource_type($source) === 'stream') {
            return Stream::create($source);
        }

        if ($source instanceof SplFileInfo) {
            $path = $source->getPathname();
            $handle = is_file($path) && is_readable($path) ? fopen($path, 'rb') : false;
            if ($handle === false) {
                throw new InvalidArgumentException([
                    'The file "{path}" does not exist or cannot be read.',
                    'path' => $path,
                ]);
            }

            return Stream::create($handle);
        }

        throw new InvalidArgumentException([
            'The source of a download must be a string, a stream, a resource or a file, {type} given.',
            'type' => get_debug_type($source),
        ]);
    }

    /**
     * Builds the `Content-Disposition` of a download (RFC 6266). The name of the
     * file is whatever the caller has, so what could break the header or look
     * like a path is replaced.
     */
    private static function contentDisposition(string $filename): string
    {
        if (!mb_check_encoding($filename, 'UTF-8')) {
            $filename = mb_convert_encoding($filename, 'UTF-8', 'ISO-8859-1');
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '_', $filename));
        if ($name === '') {
            $name = 'download';
        }

        // Every client understands the name in ASCII, and the ones that can
        // read the real one take it from `filename*`.
        $fallback = str_replace('"', '_', (string) preg_replace('/[^\x20-\x7E]/u', '_', $name));

        $disposition = sprintf('attachment; filename="%s"', $fallback);
        if ($fallback !== $name) {
            $disposition .= "; filename*=UTF-8''" . rawurlencode($name);
        }

        return $disposition;
    }

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
        $new = clone $this;
        $new->psrResponse = $this->psrResponse->withStatus($code, $reasonPhrase);

        return $new;
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
        $new = clone $this;
        $new->psrResponse = $this->psrResponse->withProtocolVersion($version);

        return $new;
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
        $new = clone $this;
        $new->psrResponse = $this->psrResponse->withHeader($name, $value);

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withAddedHeader($name, $value): static
    {
        $new = clone $this;
        $new->psrResponse = $this->psrResponse->withAddedHeader($name, $value);

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutHeader($name): static
    {
        $new = clone $this;
        $new->psrResponse = $this->psrResponse->withoutHeader($name);

        return $new;
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
        $new = clone $this;
        $new->psrResponse = $this->psrResponse->withBody($body);

        return $new;
    }
}
