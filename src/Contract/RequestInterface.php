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
use Derafu\Routing\Contract\RouteMatchInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Flash\FlashMessagesInterface;
use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Extended PSR-7 ServerRequestInterface with additional functionality.
 *
 * Provides methods for:
 *
 *   - Content type negotiation.
 *   - Request type detection (API, XHR).
 *   - Common request patterns.
 *   - Session management.
 *   - Flash messages.
 *   - User management.
 */
interface RequestInterface extends ServerRequestInterface
{
    /**
     * Gets a query parameter safely.
     *
     * @param string $key The parameter name.
     * @param mixed $default Value to return if parameter doesn't exist.
     * @return mixed The parameter value or default.
     */
    public function query(string $key, mixed $default = null): mixed;

    /**
     * Gets a post parameter safely.
     *
     * @param string $key The parameter name.
     * @param mixed $default Value to return if parameter doesn't exist.
     * @return mixed The parameter value or default.
     */
    public function post(string $key, mixed $default = null): mixed;

    /**
     * Gets a header value safely.
     *
     * Returns the first value of the header if multiple exist.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    public function header(string $name, string $default = ''): string;

    /**
     * Checks if the request has JSON content.
     *
     * @return bool
     */
    public function isJson(): bool;

    /**
     * Gets decoded JSON body safely.
     *
     * @return array<string,mixed>|null Array of decoded JSON data or `null`` if
     * invalid.
     */
    public function json(): ?array;

    /**
     * Gets all input data (query + post + json).
     *
     * @return array<string,mixed>
     */
    public function all(): array;

    /**
     * Gets input from any source (query, post, json).
     *
     * @param string $key The parameter name.
     * @param mixed $default Value to return if parameter doesn't exist.
     * @return mixed The parameter value or default.
     */
    public function input(string $key, mixed $default = null): mixed;

    /**
     * Gets multiple input values.
     *
     * @param array<string> $keys The parameter names.
     * @return array<string,mixed> The found parameters.
     */
    public function only(array $keys): array;

    /**
     * Gets an uploaded file safely.
     *
     * @param string $key The file input name.
     * @return UploadedFileInterface|null The file or null if not exists.
     */
    public function file(string $key): ?UploadedFileInterface;

    /**
     * Checks if a file was uploaded.
     *
     * @param string $key The file input name.
     */
    public function hasFile(string $key): bool;

    /**
     * Gets all uploaded files.
     *
     * @return array<string,UploadedFileInterface>
     */
    public function files(): array;

    /**
     * Checks if request prefers a specific content type.
     *
     *   - URL extension (.json, .html).
     *   - Accept header.
     *   - Request path pattern (/api/).
     *   - XHR header.
     *
     * @return ContentType
     */
    public function getPreferredContentType(): ContentType;

    /**
     * Gets the preferred response format.
     *
     * This is just the string of the getPreferredContentType().
     *
     * @return string
     */
    public function getPreferredFormat(): string;

    /**
     * Determines if this is an API request.
     *
     * @return bool True if request path is used for serve the API.
     */
    public function isApiRequest(): bool;

    /**
     * Determines if this is an AJAX/XHR request.
     *
     * @return bool True if `X-Requested-With` header is `XMLHttpRequest`.
     */
    public function isXmlHttpRequest(): bool;

    /**
     * Gets the route match.
     *
     * @return RouteMatchInterface
     * @throws LogicException If route match not found.
     */
    public function route(): RouteMatchInterface;

    /**
     * Gets the session.
     *
     * @return SessionInterface
     * @throws LogicException If session not found.
     */
    public function session(): SessionInterface;

    /**
     * Gets the flash messages.
     *
     * @return FlashMessagesInterface
     * @throws LogicException If flash messages not found.
     */
    public function flash(): FlashMessagesInterface;

    /**
     * Gets the user related to the request or null if not found.
     *
     * @return UserInterface|null
     */
    public function user(): ?UserInterface;
}
