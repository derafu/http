<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Contract;

use Derafu\Http\Enum\ContentType;
use Derafu\Http\Enum\HttpStatus;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;
use SplFileInfo;

/**
 * Extended PSR-7 ResponseInterface with additional functionality.
 *
 * Provides fluent methods for:
 *
 *   - Setting content types.
 *   - Creating common response types (JSON, HTML).
 *   - Response formatting.
 */
interface ResponseInterface extends PsrResponseInterface
{
    /**
     * Sets response status.
     *
     * @param HttpStatus $status
     * @return static
     */
    public function withHttpStatus(HttpStatus $status): static;

    /**
     * Sets the content type of the response.
     *
     * @param ContentType $type
     * @return static
     */
    public function withContentType(ContentType $type): static;

    /**
     * Creates a response in a certain text ContentType.
     *
     * @param string $data The content.
     * @param ContentType $type
     * @return static
     */
    public function asText(
        string $data,
        ContentType $type = ContentType::PLAIN
    ): static;

    /**
     * Creates an HTML response.
     *
     * @param string $html The HTML content.
     * @return static
     */
    public function asHtml(string $html): static;

    /**
     * Creates a JSON response.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $flags
     * @return static
     */
    public function asJson(
        mixed $data,
        int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ): static;

    /**
     * Creates a download response: the body is sent as a file that the client
     * saves.
     *
     * The body is never read into memory, only sent in pieces, so the size of
     * the file is not a limit. When the size is known the response has
     * `Content-Length` and, if the body can be moved to any position, offers
     * ranges (`Accept-Ranges`) to resume the download (see `RangeMiddleware`).
     *
     * @param string|StreamInterface|SplFileInfo|resource $source What to send:
     * a string is always the content (never a path); a stream or a resource is
     * sent as it is; a file is opened to be read, the path is the one of the
     * file. Any other type is an error.
     * @param string|null $filename Name that the client proposes. By default
     * the one of the file, if the source is a file, otherwise none.
     * @param ContentType $type Content type of the file.
     * @param bool $deleteAfter Delete the file once it is opened to be sent, so
     * a temporary file never stays. Only for a file as the source.
     * @return static A new response instance.
     */
    public function asDownload(
        mixed $source,
        ?string $filename = null,
        ContentType $type = ContentType::OCTET_STREAM,
        bool $deleteAfter = false
    ): static;

    /**
     * Creates a redirect response.
     *
     * @param string $url The URL to redirect to.
     * @param HttpStatus $status The redirect status code (301, 302, etc).
     * @return static A new response instance.
     */
    public function redirect(
        string $url,
        HttpStatus $status = HttpStatus::FOUND
    ): static;
}
