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

use Derafu\Support\ArrayReader;
use Derafu\Support\ArrayType;
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
        $server = ArrayReader::from($_SERVER);
        $headers = [];
        foreach (array_keys($_SERVER) as $key) {
            $value = str_starts_with($key, 'HTTP_') ? $server->string($key) : null;
            if ($value !== null) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }

        // Read the request body.
        $body = file_get_contents('php://input');

        // Create PSR-7 server request using the factory.
        $request = $psr17Factory->createServerRequest(
            $server->string('REQUEST_METHOD') ?? 'GET',
            $server->string('REQUEST_URI') ?? '/',
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
        $context['URL_SCHEME'] = (($context['HTTPS'] ?? false) || $context['URL_PORT'] == 443) ? 'https' : 'http';

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
     * @param array<int|string,mixed> $files The $_FILES superglobal.
     * @param Psr17Factory $factory
     * @return array<int|string,mixed> Normalized array of UploadedFileInterface.
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
                if (is_array($value['tmp_name'])) {
                    // Restructure PHP's flat $_FILES layout into a nested
                    // tree grouped by index, then normalize recursively.
                    $restructured = static::restructureNestedFiles($value);
                    $normalized[$key] = static::normalizeFiles($restructured, $factory);
                } else {
                    // Single file.
                    $file = new ArrayReader($value, (string) $key);
                    $error = $file->require('error', ArrayType::int());
                    $tmpName = $file->require('tmp_name', ArrayType::string());
                    if ($error === UPLOAD_ERR_OK && $tmpName !== '') {
                        $normalized[$key] = $factory->createUploadedFile(
                            $factory->createStreamFromFile($tmpName),
                            $file->int('size'),
                            $error,
                            $file->string('name'),
                            $file->string('type')
                        );
                    }
                }

                continue;
            }

            // Recurse if there is no 'tmp_name' but there are more arrays.
            if (is_array($value)) {
                $normalized[$key] = static::normalizeFiles($value, $factory);
                continue;
            }
        }

        return $normalized;
    }

    /**
     * Restructures PHP's flat $_FILES array into a nested tree.
     *
     * PHP stores nested file uploads like name[0][caf] as:
     *   ['tmp_name' => [0 => ['caf' => '...']], 'name' => [0 => ['caf' => '...']], ...]
     *
     * This method converts that into:
     *   [0 => ['caf' => ['tmp_name' => '...', 'name' => '...', ...]]]
     *
     * So each leaf becomes a standard single-file array that normalizeFiles()
     * can process.
     *
     * @param array<int|string,mixed> $fileData A single entry from $_FILES with array tmp_name.
     * @return array<int|string,mixed> Restructured tree grouped by index/key.
     */
    private static function restructureNestedFiles(array $fileData): array
    {
        $result = [];
        $reader = ArrayReader::from($fileData);

        foreach (['tmp_name', 'name', 'type', 'error', 'size'] as $column) {
            foreach ($reader->require($column, ArrayType::array()) as $idx => $value) {
                $result[$idx][$column] = $value;
            }
        }

        return $result;
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
