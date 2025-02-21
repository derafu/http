<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Enum;

/**
 * Common HTTP content types.
 *
 * A collection of the most commonly used content types in HTTP responses.
 * Each type includes both the main type and subtype as per RFC 2616.
 *
 * @see https://www.ietf.org/rfc/rfc2616.txt
 */
enum ContentType: string
{
    // Application types.
    case JSON = 'application/json';
    case XML = 'application/xml';
    case FORM = 'application/x-www-form-urlencoded';
    case PDF = 'application/pdf';
    case ZIP = 'application/zip';
    case JAVASCRIPT = 'application/javascript';

    // Application types RFC 7807.
    case PROBLEM_JSON = 'application/problem+json';
    case PROBLEM_XML = 'application/problem+xml';

    // Text types.
    case HTML = 'text/html';
    case PLAIN = 'text/plain';
    case CSS = 'text/css';
    case CSV = 'text/csv';
    case MARKDOWN = 'text/markdown';

    // Image types.
    case PNG = 'image/png';
    case JPEG = 'image/jpeg';
    case GIF = 'image/gif';
    case SVG = 'image/svg+xml';
    case WEBP = 'image/webp';

    // Audio/Video types.
    case MP3 = 'audio/mpeg';
    case MP4 = 'video/mp4';
    case WEBM = 'video/webm';

    // Special types.
    case MULTIPART_FORM = 'multipart/form-data';
    case EVENT_STREAM = 'text/event-stream';

    /**
     * Gets the main type (e.g., 'application', 'text', etc).
     *
     * @return string
     */
    public function getMainType(): string
    {
        return explode('/', $this->value)[0];
    }

    /**
     * Gets the subtype (e.g., 'json', 'html', etc).
     *
     * @return string
     */
    public function getSubType(): string
    {
        return explode('/', $this->value)[1];
    }

    /**
     * Gets the charset parameter string for text-based types.
     *
     * @param string $charset
     * @return string
     */
    public function withCharset(string $charset = 'UTF-8'): string
    {
        return "{$this->value}; charset={$charset}";
    }

    /**
     * Determines if this is a text-based content type.
     *
     * @return bool
     */
    public function isText(): bool
    {
        return
            $this->getMainType() === 'text'
            || in_array($this->value, [
                self::JSON->value,
                self::XML->value,
                self::JAVASCRIPT->value,
                self::PROBLEM_JSON->value,
                self::PROBLEM_XML->value,
            ])
        ;
    }
}
