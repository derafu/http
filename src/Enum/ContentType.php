<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
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
    case YAML = 'application/yaml';
    case XML = 'application/xml';
    case FORM = 'application/x-www-form-urlencoded';
    case PDF = 'application/pdf';
    case ZIP = 'application/zip';
    case RAR = 'application/vnd.rar';
    case TAR = 'application/x-tar';
    case GZIP = 'application/gzip';
    case JAVASCRIPT = 'application/javascript';
    case TYPESCRIPT = 'application/typescript';
    case WASM = 'application/wasm';
    case OCTET_STREAM = 'application/octet-stream';

    // Application types RFC 7807.
    case PROBLEM_JSON = 'application/problem+json';
    case PROBLEM_XML = 'application/problem+xml';

    // Text types.
    case HTML = 'text/html';
    case PLAIN = 'text/plain';
    case CSS = 'text/css';
    case CSV = 'text/csv';
    case MARKDOWN = 'text/markdown';
    case XML_TEXT = 'text/xml';

    // Image types.
    case PNG = 'image/png';
    case JPEG = 'image/jpeg';
    case GIF = 'image/gif';
    case SVG = 'image/svg+xml';
    case WEBP = 'image/webp';
    case ICO = 'image/x-icon';
    case BMP = 'image/bmp';
    case TIFF = 'image/tiff';
    case AVIF = 'image/avif';

    // Audio types.
    case MP3 = 'audio/mpeg';
    case WAV = 'audio/wav';
    case OGG_AUDIO = 'audio/ogg';
    case AAC = 'audio/aac';
    case FLAC = 'audio/flac';

    // Video types.
    case MP4 = 'video/mp4';
    case WEBM = 'video/webm';
    case OGG_VIDEO = 'video/ogg';
    case AVI = 'video/x-msvideo';
    case MOV = 'video/quicktime';

    // Font types.
    case WOFF = 'font/woff';
    case WOFF2 = 'font/woff2';
    case TTF = 'font/ttf';
    case OTF = 'font/otf';
    case EOT = 'application/vnd.ms-fontobject';

    // Document types.
    case DOC = 'application/msword';
    case DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    case XLS = 'application/vnd.ms-excel';
    case XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case PPT = 'application/vnd.ms-powerpoint';
    case PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

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
                self::YAML->value,
                self::XML->value,
                self::JAVASCRIPT->value,
                self::TYPESCRIPT->value,
                self::PROBLEM_JSON->value,
                self::PROBLEM_XML->value,
                self::MARKDOWN->value,
            ])
        ;
    }

    /**
     * Determines if this is an image content type.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return $this->getMainType() === 'image';
    }

    /**
     * Determines if this is an audio content type.
     *
     * @return bool
     */
    public function isAudio(): bool
    {
        return $this->getMainType() === 'audio';
    }

    /**
     * Determines if this is a video content type.
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        return $this->getMainType() === 'video';
    }

    /**
     * Determines if this is a font content type.
     *
     * @return bool
     */
    public function isFont(): bool
    {
        return
            $this->getMainType() === 'font'
            || $this->value === self::EOT->value
        ;
    }

    /**
     * Determines if this is a static resource content type.
     *
     * @return bool
     */
    public function isStatic(): bool
    {
        // If it's not the default OCTET_STREAM, it's a recognized static
        // resource.
        if ($this !== self::OCTET_STREAM) {
            return true;
        }

        return false;
    }

    /**
     * Gets ContentType enum from a file extension.
     *
     * @param string $extension The file extension without dot (e.g., 'js', 'css').
     * @return self The corresponding ContentType enum, or OCTET_STREAM if not found.
     */
    public static function fromExtension(string $extension): self
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'js' => self::JAVASCRIPT,
            'ts' => self::TYPESCRIPT,
            'json' => self::JSON,
            'yaml', 'yml' => self::YAML,
            'xml' => self::XML,
            'html', 'htm' => self::HTML,
            'txt' => self::PLAIN,
            'css' => self::CSS,
            'csv' => self::CSV,
            'md', 'markdown' => self::MARKDOWN,
            'pdf' => self::PDF,
            'zip' => self::ZIP,
            'rar' => self::RAR,
            'tar' => self::TAR,
            'gz' => self::GZIP,
            'map' => self::JSON,
            'wasm' => self::WASM,
            'png' => self::PNG,
            'jpg', 'jpeg' => self::JPEG,
            'gif' => self::GIF,
            'svg' => self::SVG,
            'webp' => self::WEBP,
            'ico' => self::ICO,
            'bmp' => self::BMP,
            'tiff', 'tif' => self::TIFF,
            'avif' => self::AVIF,
            'mp3' => self::MP3,
            'wav' => self::WAV,
            'ogg' => self::OGG_AUDIO,
            'aac' => self::AAC,
            'flac' => self::FLAC,
            'mp4', 'm4v' => self::MP4,
            'webm' => self::WEBM,
            'avi' => self::AVI,
            'mov' => self::MOV,
            'woff' => self::WOFF,
            'woff2' => self::WOFF2,
            'ttf' => self::TTF,
            'otf' => self::OTF,
            'eot' => self::EOT,
            'doc' => self::DOC,
            'docx' => self::DOCX,
            'xls' => self::XLS,
            'xlsx' => self::XLSX,
            'ppt' => self::PPT,
            'pptx' => self::PPTX,
            default => self::OCTET_STREAM,
        };
    }

    /**
     * Gets ContentType enum from a filename.
     *
     * @param string $filename The filename including extension.
     * @return self The corresponding ContentType enum, or OCTET_STREAM if not found.
     */
    public static function fromFilename(string $filename): self
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return self::fromExtension($extension);
    }
}
