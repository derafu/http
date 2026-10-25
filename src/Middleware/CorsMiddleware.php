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

use Derafu\Array\ArrayReader;
use Derafu\Http\Response;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handles Cross-Origin Resource Sharing (CORS) using path-based rules.
 *
 * This middleware is responsible for:
 *
 *   - Matching the request path against a list of configured rules.
 *   - Responding to OPTIONS preflight requests immediately (short-circuit).
 *   - Adding CORS headers to responses for matched paths and allowed origins.
 *
 * Rules are evaluated in order; the first match wins. If no rules are given,
 * a default rule matching all paths with permissive settings is applied.
 *
 * This should be placed first in the middleware stack so that preflight
 * requests never reach the router or dispatcher.
 *
 * Example configuration in services.yaml:
 *
 *   Derafu\Http\Middleware\CorsMiddleware:
 *       arguments:
 *           $rules:
 *               -   path: '^/api'
 *                   allowedOrigins: ['https://app.example.com']
 *                   allowedMethods: ['GET', 'POST', 'OPTIONS']
 *                   allowedHeaders: ['Content-Type', 'Authorization']
 *                   allowCredentials: true
 *                   maxAge: 3600
 *               -   path: '^/webhook'
 *                   allowedOrigins: ['*']
 *                   allowedMethods: ['POST', 'OPTIONS']
 *                   allowedHeaders: ['Content-Type']
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Default values applied to any rule that omits a field.
     */
    private const DEFAULTS = [
        'allowedOrigins'   => ['*'],
        'allowedMethods'   => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowedHeaders'   => ['Content-Type', 'Authorization', 'Accept'],
        'allowCredentials' => false,
        'maxAge'           => 3600,
    ];

    /**
     * Resolved list of rules, each with all fields populated.
     *
     * @var list<array{path: string, allowedOrigins: list<string>, allowedMethods: list<string>, allowedHeaders: list<string>, allowCredentials: bool, maxAge: int}>
     */
    private array $rules;

    /**
     * Creates a new CORS middleware.
     *
     * Each rule is an associative array with the following keys:
     *
     *   - path (string):             Regex matched against the request path.
     *   - allowedOrigins (string[]): Allowed origins, e.g. ['*'] or ['https://example.com'].
     *   - allowedMethods (string[]): Allowed HTTP methods.
     *   - allowedHeaders (string[]): Allowed request headers.
     *   - allowCredentials (bool):   Whether credentials are allowed.
     *   - maxAge (int):              Preflight cache lifetime in seconds.
     *
     * All keys except `path` are optional and fall back to permissive defaults.
     * If no rules are provided, a single catch-all rule with permissive defaults
     * is used (equivalent to allowing CORS for every path).
     *
     * @param array<int, array<string, mixed>> $rules List of CORS rules.
     * @throws InvalidArgumentException If a rule has no `path`, or an option of
     * a rule is not of its type: a configuration that is wrong is not ignored.
     */
    public function __construct(array $rules = [])
    {
        // If no rules are provided, use a single catch-all rule with permissive
        // defaults.
        if (empty($rules)) {
            $rules = [['path' => '.*']];
        }

        // Read each rule with its defaults so downstream logic can always rely
        // on every key being present.
        $this->rules = [];
        foreach ($rules as $index => $rule) {
            $this->rules[] = $this->readRule($index, $rule);
        }
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
        $origin = $request->getHeaderLine('Origin');

        // Not a cross-origin request, pass through.
        if ($origin === '') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $rule = $this->matchRule($path);

        // Path not covered by any CORS rule, pass through.
        if ($rule === null) {
            return $handler->handle($request);
        }

        // Origin not allowed by the matched rule, pass through.
        if (!$this->isOriginAllowed($origin, $rule['allowedOrigins'])) {
            return $handler->handle($request);
        }

        // OPTIONS preflight: short-circuit, never reach the router/dispatcher.
        if ($request->getMethod() === 'OPTIONS') {
            return $this->buildPreflightResponse($origin, $rule);
        }

        // Actual cross-origin request: delegate, then add CORS headers.
        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $origin, $rule);
    }

    /**
     * Reads a rule, with the defaults for what it omits.
     *
     * @param int|string $index The position of the rule, to say which one it is.
     * @param array<string, mixed> $rule
     * @return array{path: string, allowedOrigins: list<string>, allowedMethods: list<string>, allowedHeaders: list<string>, allowCredentials: bool, maxAge: int}
     * @throws InvalidArgumentException
     */
    private function readRule(int|string $index, array $rule): array
    {
        $reader = new ArrayReader($rule, 'rules.' . $index);

        $path = $reader->requireString('path');
        if (trim($path) === '') {
            throw new InvalidArgumentException([
                'The CORS rule {index} must have a "path" that is not empty: the regular expression that is matched against the path.',
                'index' => (string) $index,
            ]);
        }

        return [
            'path' => $path,
            'allowedOrigins' => $reader->getStringList('allowedOrigins') ?? self::DEFAULTS['allowedOrigins'],
            'allowedMethods' => $reader->getStringList('allowedMethods') ?? self::DEFAULTS['allowedMethods'],
            'allowedHeaders' => $reader->getStringList('allowedHeaders') ?? self::DEFAULTS['allowedHeaders'],
            'allowCredentials' => $reader->getBool('allowCredentials') ?? self::DEFAULTS['allowCredentials'],
            'maxAge' => $reader->getInt('maxAge') ?? self::DEFAULTS['maxAge'],
        ];
    }

    /**
     * Returns the first rule whose path pattern matches the given path,
     * or null if none match.
     *
     * @param string $path The request path.
     * @return array{path: string, allowedOrigins: list<string>, allowedMethods: list<string>, allowedHeaders: list<string>, allowCredentials: bool, maxAge: int}|null
     */
    private function matchRule(string $path): ?array
    {
        foreach ($this->rules as $rule) {
            if (preg_match('{' . $rule['path'] . '}', $path)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Checks whether the given origin is allowed by the rule.
     *
     * @param string $origin The Origin header value.
     * @param string[] $allowedOrigins The rule's allowed origins.
     * @return bool
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $allowedOrigins, true);
    }

    /**
     * Builds the 204 response for an OPTIONS preflight request.
     *
     * @param string $origin The allowed origin.
     * @param array{path: string, allowedOrigins: list<string>, allowedMethods: list<string>, allowedHeaders: list<string>, allowCredentials: bool, maxAge: int} $rule The matched rule.
     * @return PsrResponseInterface
     */
    private function buildPreflightResponse(
        string $origin,
        array $rule
    ): PsrResponseInterface {
        $response = new Response(204);
        $response = $this->addCorsHeaders($response, $origin, $rule);
        $response = $response->withHeader(
            'Access-Control-Max-Age',
            (string) $rule['maxAge']
        );

        return $response;
    }

    /**
     * Adds CORS headers to a response.
     *
     * @param PsrResponseInterface $response The response to decorate.
     * @param string $origin The request origin.
     * @param array{path: string, allowedOrigins: list<string>, allowedMethods: list<string>, allowedHeaders: list<string>, allowCredentials: bool, maxAge: int} $rule The matched rule.
     * @return PsrResponseInterface
     */
    private function addCorsHeaders(
        PsrResponseInterface $response,
        string $origin,
        array $rule
    ): PsrResponseInterface {
        // If wildcard is configured but credentials are allowed, the spec
        // requires reflecting the specific origin instead of '*'.
        $allowOrigin = (
            in_array('*', $rule['allowedOrigins'], true) && !$rule['allowCredentials']
        ) ? '*' : $origin;

        $response = $response->withHeader('Access-Control-Allow-Origin', $allowOrigin);
        $response = $response->withHeader(
            'Access-Control-Allow-Methods',
            implode(', ', $rule['allowedMethods'])
        );
        $response = $response->withHeader(
            'Access-Control-Allow-Headers',
            implode(', ', $rule['allowedHeaders'])
        );

        if ($rule['allowCredentials']) {
            $response = $response->withHeader(
                'Access-Control-Allow-Credentials',
                'true'
            );
        }

        return $response;
    }
}
