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

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Runtime environment for handling HTTP requests.
 *
 * This class provides the runtime environment for PSR-15 compatible HTTP
 * applications.
 *
 * It handles:
 *
 *   - Application bootstrapping.
 *   - Request creation.
 *   - Response sending.
 *   - Environment context management.
 */
final class Runtime
{
    /**
     * Runs the application.
     *
     * This method:
     *
     *   1. Bootstraps the application.
     *   2. Creates a PSR-7 request.
     *   3. Handles the request.
     *   4. Sends the response.
     *
     * @param RequestHandlerInterface $handler Handler returned in index.php
     * @return int The status code of the execution.
     */
    public static function run(RequestHandlerInterface $handler): int
    {
        // Create PSR-17 factory for HTTP message objects.
        $psr17Factory = new Psr17Factory();

        // Extract headers from `$_SERVER`.
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }

        // Read the request body.
        $body = file_get_contents('php://input');

        // Create PSR-7 server request using the factory.
        $request = $psr17Factory->createServerRequest(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_SERVER
        );

        // Add headers manually since createServerRequest doesn't extract them
        // from `$_SERVER`.
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Set the body if it exists.
        if (!empty($body)) {
            $request = $request->withBody($psr17Factory->createStream($body));
        }

        // Add query params and parsed body.
        $request = $request
            ->withQueryParams($_GET)
            ->withParsedBody($_POST)
            ->withUploadedFiles(static::normalizeFiles($_FILES, $psr17Factory))
        ;

        // Handle the request and get response.
        $response = $handler->handle($request);

        // Send the response.
        static::sendResponse($response);

        // Return the status code of the execution.
        return $response->getStatusCode() < 400 ? 0 : 1;
    }

    /**
     * Gets the application context from environment.
     *
     * Provides essential context information including:
     *
     *   - Application environment (dev, test, prod).
     *   - Debug mode status.
     *   - Request information.
     *   - Server information.
     *
     * @return array<string, mixed> The application context.
     */
    public static function getApplicationContext(): array
    {
        // Merge server and environment variables.
        $context = array_merge($_SERVER, $_ENV);

        // Application environment settings.
        $context['APP_ENV'] = $context['APP_ENV'] ?? 'dev';
        $context['APP_DEBUG'] = $context['APP_DEBUG'] ?? true;
        $context['APP_NAME'] = $context['APP_NAME'] ?? null;
        $context['APP_URL'] = $context['APP_URL'] ?? null;
        $context['APP_BASE_PATH'] = $context['APP_BASE_PATH'] ?? '';

        // Request information.
        $context['URL_HOST'] = $context['SERVER_NAME'] ?? 'localhost';
        $context['URL_PORT'] = $context['SERVER_PORT'] ?? 80;
        $context['URL_METHOD'] = $context['REQUEST_METHOD'] ?? 'GET';
        $context['URL_URI'] = $context['REQUEST_URI'] ?? '/';
        $context['URL_QUERY'] = $context['QUERY_STRING'] ?? '';
        $context['URL_SCHEME'] = (($context['HTTPS'] ?? false) || $context['SERVER_PORT'] == 443) ? 'https' : 'http';

        // Server information.
        $context['SCRIPT_NAME'] = $context['SCRIPT_NAME'] ?? '/index.php';

        // Current timestamp.
        $context['REQUEST_TIME'] = $context['REQUEST_TIME'] ?? time();

        // Client information.
        $context['REMOTE_ADDR'] = $context['REMOTE_ADDR'] ?? '127.0.0.1';

        // Headers.
        $context['HTTP_HOST'] = $context['HTTP_HOST'] ?? 'localhost';
        $context['HTTP_USER_AGENT'] = $context['HTTP_USER_AGENT'] ?? '';
        $context['HTTP_ACCEPT'] = $context['HTTP_ACCEPT'] ?? '*/*';
        $context['HTTP_ACCEPT_LANGUAGE'] = $context['HTTP_ACCEPT_LANGUAGE'] ?? 'en-US';
        $context['HTTP_ACCEPT_ENCODING'] = $context['HTTP_ACCEPT_ENCODING'] ?? '';

        // Return the context.
        return $context;
    }

    /**
     * Normalizes the PHP files array for PSR-7 uploaded files.
     *
     * @param array $files The $_FILES superglobal.
     * @param Psr17Factory $factory
     * @return array Normalized array of UploadedFileInterface.
     */
    private static function normalizeFiles(array $files, Psr17Factory $factory): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }

            if (is_array($value) && isset($value['tmp_name'])) {
                // Normal or multiple.
                if (is_array($value['tmp_name'])) {
                    // Multiple files (input with [] or name[]).
                    $normalized[$key] = [];
                    foreach (array_keys($value['tmp_name']) as $idx) {
                        if ($value['error'][$idx] === UPLOAD_ERR_OK && !empty($value['tmp_name'][$idx])) {
                            $normalized[$key][$idx] = $factory->createUploadedFile(
                                $factory->createStreamFromFile($value['tmp_name'][$idx]),
                                $value['size'][$idx],
                                $value['error'][$idx],
                                $value['name'][$idx],
                                $value['type'][$idx]
                            );
                        }
                    }
                } else {
                    // Single file.
                    if ($value['error'] === UPLOAD_ERR_OK && !empty($value['tmp_name'])) {
                        $normalized[$key] = $factory->createUploadedFile(
                            $factory->createStreamFromFile($value['tmp_name']),
                            $value['size'],
                            $value['error'],
                            $value['name'],
                            $value['type']
                        );
                    }
                }

                continue;
            }

            // Recurse if there is no 'tmp_name' but there are more arrays (rare, very nested).
            if (is_array($value)) {
                $normalized[$key] = static::normalizeFiles($value, $factory);
                continue;
            }
        }

        return $normalized;
    }

    /**
     * Sends a PSR-7 response to the client.
     *
     * Handles:
     *
     *   - HTTP headers.
     *   - Status code.
     *   - Response body.
     *
     * @param ResponseInterface $response The response to send.
     */
    private static function sendResponse(ResponseInterface $response): void
    {
        // Send all response headers.
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // Send HTTP status code.
        http_response_code($response->getStatusCode());

        // Send response body.
        echo $response->getBody();
    }
}
