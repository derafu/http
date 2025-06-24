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
