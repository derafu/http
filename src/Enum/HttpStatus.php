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
 * HTTP Status Codes.
 *
 * Represents standard HTTP status codes as defined in RFC 7231 and others.
 *
 * @see https://tools.ietf.org/html/rfc7231#section-6
 * @see https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
 */
enum HttpStatus: int
{
    // 1xx Informational.
    case CONTINUE = 100;

    // 2xx Successful.
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case NO_CONTENT = 204;

    // 3xx Redirection.
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case SEE_OTHER = 303;
    case NOT_MODIFIED = 304;
    case TEMPORARY_REDIRECT = 307;

    // 4xx Client Error.
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case PAYMENT_REQUIRED = 402;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case NOT_ACCEPTABLE = 406;
    case REQUEST_TIMEOUT = 408;
    case CONFLICT = 409;
    case GONE = 410;
    case PAYLOAD_TOO_LARGE = 413;
    case UNSUPPORTED_MEDIA_TYPE = 415;
    case UNPROCESSABLE_CONTENT = 422;
    case LOCKED = 423;
    case TOO_MANY_REQUESTS = 429;

    // 5xx Server Error.
    case INTERNAL_SERVER_ERROR = 500;
    case NOT_IMPLEMENTED = 501;
    case BAD_GATEWAY = 502;
    case SERVICE_UNAVAILABLE = 503;
    case GATEWAY_TIMEOUT = 504;
    case INSUFFICIENT_STORAGE = 507;
    case UNKNOWN_ERROR = 520;           // Non RFC status.

    /**
     * Gets the standard reason phrase for this status code.
     *
     * @return string
     */
    public function getReasonPhrase(): string
    {
        return match($this) {
            // 1xx Informational.
            self::CONTINUE => 'Continue',

            // 2xx Successful.
            self::OK => 'OK',
            self::CREATED => 'Created',
            self::ACCEPTED => 'Accepted',
            self::NO_CONTENT => 'No Content',

            // 3xx Redirection.
            self::MOVED_PERMANENTLY => 'Moved Permanently',
            self::SEE_OTHER => 'See Other',
            self::FOUND => 'Found',
            self::NOT_MODIFIED => 'Not Modified',
            self::TEMPORARY_REDIRECT => 'Temporary Redirect',

            // 4xx Client Error.
            self::BAD_REQUEST => 'Bad Request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::PAYMENT_REQUIRED => 'Payment Required',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::NOT_ACCEPTABLE => 'Not Acceptable',
            self::REQUEST_TIMEOUT => 'Request Timeout',
            self::CONFLICT => 'Conflict',
            self::GONE => 'Gone',
            self::PAYLOAD_TOO_LARGE => 'Payload Too Large',
            self::UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
            self::UNPROCESSABLE_CONTENT => 'Unprocessable Content',
            self::LOCKED => 'Locked',
            self::TOO_MANY_REQUESTS => 'Too Many Requests',

            // 5xx Server Error.
            self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
            self::NOT_IMPLEMENTED => 'Not Implemented',
            self::BAD_GATEWAY => 'Bad Gateway',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::GATEWAY_TIMEOUT => 'Gateway Timeout',
            self::INSUFFICIENT_STORAGE => 'Insufficient Storage',
            self::UNKNOWN_ERROR => 'Unknown Error',
        };
    }

    /**
     * Determines if this status code is informational (1xx).
     *
     * @return bool
     */
    public function isInformational(): bool
    {
        return $this->value < 200;
    }

    /**
     * Determines if this status code indicates success (2xx).
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->value >= 200 && $this->value < 300;
    }

    /**
     * Determines if this status code indicates redirection (3xx).
     *
     * @return bool
     */
    public function isRedirection(): bool
    {
        return $this->value >= 300 && $this->value < 400;
    }

    /**
     * Determines if this status code indicates client error (4xx).
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->value >= 400 && $this->value < 500;
    }

    /**
     * Determines if this status code indicates server error (5xx).
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->value >= 500;
    }

    /**
     * Determines if this status code indicates an error (4xx or 5xx).
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->value >= 400;
    }
}
