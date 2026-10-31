<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Middleware;

use Derafu\Http\Support\RangeStream;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Answers the requests of a part of a file (`Range`, RFC 9110 section 14), so
 * a download can be resumed or a media file can be sought.
 *
 * It acts on the response of any later handler that offers its body as a range:
 * a `200` response with `Accept-Ranges: bytes`, whose body is a seekable stream
 * of known size (see `Response::asDownload()`). Anything else is left as is.
 *
 * Only one range is served. When several are asked for, or the `Range` header
 * is not valid, the whole response is sent, which is what the standard allows.
 * It must run before the handlers that make the response, so it has the highest
 * priority of the pipeline.
 */
class RangeMiddleware implements MiddlewareInterface
{
    /**
     * Largest amount of digits of a position: it keeps the values inside of an
     * integer.
     */
    private const MAX_DIGITS = 18;

    /**
     * {@inheritDoc}
     */
    public function process(
        PsrRequestInterface $request,
        RequestHandlerInterface $handler
    ): PsrResponseInterface {
        $response = $handler->handle($request);

        $header = trim($request->getHeaderLine('Range'));
        if (
            $header === ''
            || strtoupper($request->getMethod()) !== 'GET'
            || $response->getStatusCode() !== 200
            || !$this->acceptsRanges($response)
        ) {
            return $response;
        }

        $body = $response->getBody();
        $size = $body->getSize();
        if ($size === null || !$body->isSeekable() || !$this->isCurrent($request, $response)) {
            return $response;
        }

        $range = $this->parse($header, $size);

        // Not valid, or several ranges: the whole response.
        if ($range === null) {
            return $response;
        }

        // Nothing of the file is inside of the range.
        if ($range === false) {
            return $response
                ->withStatus(416)
                ->withHeader('Content-Range', 'bytes */' . $size)
                ->withHeader('Content-Length', '0')
                ->withBody(Stream::create(''))
            ;
        }

        [$start, $end] = $range;
        $length = $end - $start + 1;

        return $response
            ->withStatus(206)
            ->withHeader('Content-Range', sprintf('bytes %d-%d/%d', $start, $end, $size))
            ->withHeader('Content-Length', (string) $length)
            ->withBody(new RangeStream($body, $start, $length))
        ;
    }

    /**
     * Tells if the response offers its body by ranges.
     */
    private function acceptsRanges(PsrResponseInterface $response): bool
    {
        return strtolower(trim($response->getHeaderLine('Accept-Ranges'))) === 'bytes';
    }

    /**
     * `If-Range`: the range is served only if the file is still the one that
     * the client has, otherwise the client needs the whole file.
     */
    private function isCurrent(
        PsrRequestInterface $request,
        PsrResponseInterface $response
    ): bool {
        $condition = trim($request->getHeaderLine('If-Range'));
        if ($condition === '') {
            return true;
        }

        // An entity tag: it must be a strong one and the same.
        if (str_starts_with($condition, '"') || str_starts_with($condition, 'W/')) {
            $etag = trim($response->getHeaderLine('ETag'));

            return $etag !== '' && !str_starts_with($etag, 'W/') && $etag === $condition;
        }

        // A date: the same one as the last modification.
        $lastModified = strtotime($response->getHeaderLine('Last-Modified'));
        $date = strtotime($condition);

        return $lastModified !== false && $date !== false && $lastModified === $date;
    }

    /**
     * Reads the `Range` header.
     *
     * @return array{int, int}|false|null The first and last byte (both
     * included), `false` if the range cannot be satisfied, `null` if it must be
     * ignored.
     */
    private function parse(string $header, int $size): array|false|null
    {
        // Only a unit of bytes with one range: a list has a comma.
        if (preg_match('/^bytes=(\d*)-(\d*)$/i', $header, $matches) !== 1) {
            return null;
        }

        [, $first, $last] = $matches;
        if (
            ($first === '' && $last === '')
            || strlen($first) > self::MAX_DIGITS
            || strlen($last) > self::MAX_DIGITS
        ) {
            return null;
        }

        // The last bytes: `-500`.
        if ($first === '') {
            $suffix = (int) $last;
            if ($suffix === 0 || $size === 0) {
                return false;
            }

            return [max(0, $size - $suffix), $size - 1];
        }

        $start = (int) $first;

        // Ends before it starts: not a valid range.
        if ($last !== '' && (int) $last < $start) {
            return null;
        }

        if ($start >= $size) {
            return false;
        }

        return [$start, $last === '' ? $size - 1 : min((int) $last, $size - 1)];
    }
}
