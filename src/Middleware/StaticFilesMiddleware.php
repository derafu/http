<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
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
     * @param string $directory Base directory for static files.
     * @param int $cacheMaxAge Cache lifetime in seconds (default 24 hours).
     */
    public function __construct(
        string $directory,
        int $cacheMaxAge = 86400
    ) {
        $this->directory = $directory;
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
        $path = $request->getUri()->getPath();
        $contentType = ContentType::fromFilename($path);

        // Only process if it has a static file extension.
        if (!$contentType->isStatic()) {
            return $handler->handle($request);
        }

        // Build the file path.
        $filePath = realpath($this->directory . $path);

        // Check if file exists and is valid.
        if (
            $filePath === false
            || !str_starts_with($filePath, $this->directory)
            || !is_file($filePath)
            || !is_readable($filePath)
        ) {
            // File doesn't exist or isn't readable, continue to next middleware.
            return $handler->handle($request);
        }

        // Get the file content.
        $content = file_get_contents($filePath);
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
}
