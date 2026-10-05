<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Middleware;

use Derafu\Http\Enum\ContentType;
use Derafu\Http\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handles serving static files directly.
 *
 * This middleware is responsible for:
 *
 *   - Detecting requests for static files (.js, .css, etc).
 *   - Serving those files directly with proper headers.
 *   - Bypassing the normal middleware chain when appropriate.
 *
 * This should be placed at the beginning in the middleware stack to intercept
 * static file requests before they reach other middlewares.
 */
class StaticFilesMiddleware implements MiddlewareInterface
{
    /**
     * The only hidden directory that may be served (RFC 8615).
     */
    private const WELL_KNOWN_DIRECTORY = '.well-known';

    /**
     * Base directory for static files.
     *
     * @var string
     */
    private string $directory;

    /**
     * Cache lifetime in seconds.
     *
     * @var int
     */
    private int $cacheMaxAge;

    /**
     * Creates a new static files middleware.
     *
     * @param string $directory Base directory for static files. It is
     * resolved with `realpath()` once, so it may carry symlinks, `..` or a
     * trailing slash. If it does not exist nothing is served.
     * @param int $cacheMaxAge Cache lifetime in seconds (default 24 hours).
     */
    public function __construct(
        string $directory,
        int $cacheMaxAge = 86400
    ) {
        $this->directory = realpath($directory) ?: $directory;
        $this->cacheMaxAge = $cacheMaxAge;
    }

    /**
     * Processes an incoming server request.
     *
     * @param PsrRequestInterface $request The request.
     * @param RequestHandlerInterface $handler The handler.
     * @return PsrResponseInterface The response.
     */
    public function process(
        PsrRequestInterface $request,
        RequestHandlerInterface $handler
    ): PsrResponseInterface {
        // Static files are only read, never written.
        $method = strtoupper($request->getMethod());
        if ($method !== 'GET' && $method !== 'HEAD') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $contentType = ContentType::fromFilename($path);

        // Only process if it has a static file extension.
        if (!$contentType->isStatic()) {
            return $handler->handle($request);
        }

        // Never climb directories nor expose hidden files.
        if ($this->isUnsafePath($path)) {
            return $handler->handle($request);
        }

        // Build the file path.
        $filePath = realpath($this->directory . $path);

        // Check if file exists and is valid. The separator after the
        // directory matters: without it a sibling such as `static-secret`
        // would pass for being inside `static`.
        if (
            $filePath === false
            || !str_starts_with($filePath, $this->directory . DIRECTORY_SEPARATOR)
            || !is_file($filePath)
            || !is_readable($filePath)
        ) {
            // File doesn't exist or isn't readable, continue to next middleware.
            return $handler->handle($request);
        }

        // Get the file content (a HEAD response has no body).
        $content = $method === 'HEAD' ? '' : file_get_contents($filePath);
        if ($content === false) {
            // Couldn't read the file, continue to next middleware.
            return $handler->handle($request);
        }

        // Create a response with the file content.
        $response = new Response();
        $response = $response->withBody(Stream::create($content));

        // Set content type based on extension.
        $response = $response->withHeader('Content-Type', $contentType->value);

        // Add charset for text-based content types.
        if ($contentType->isText()) {
            $response = $response->withHeader(
                'Content-Type',
                $contentType->withCharset()
            );
        }

        // Add cache headers.
        $response = $response->withHeader(
            'Cache-Control',
            "public, max-age={$this->cacheMaxAge}"
        );

        // Set ETag based on file modification time and size.
        $etag = sprintf('"%x-%x"', filemtime($filePath), filesize($filePath));
        $response = $response->withHeader('ETag', $etag);

        // Handle conditional requests (If-None-Match).
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch && $ifNoneMatch === $etag) {
            return new Response(304); // Not Modified.
        }

        return $response;
    }

    /**
     * Checks if a path has a null byte or a segment starting with a dot, which
     * covers `..` and hidden files or directories.
     *
     * The only exception is a first segment named exactly `.well-known`
     * (RFC 8615), where `security.txt`, `assetlinks.json` and similar files
     * are expected to live. Anything after it is checked as usual.
     */
    private function isUnsafePath(string $path): bool
    {
        if (str_contains($path, "\0")) {
            return true;
        }

        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', $path)),
            static fn (string $segment): bool => $segment !== ''
        ));

        if (($segments[0] ?? null) === self::WELL_KNOWN_DIRECTORY) {
            array_shift($segments);
        }

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }
}
