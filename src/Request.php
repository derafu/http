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

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Routing\Contract\RouteMatchInterface;
use JsonException;
use LogicException;
use Mezzio\Authentication\UserInterface;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Nyholm\Psr7\ServerRequest as PsrRequest;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Extended PSR-7 ServerRequest implementation.
 *
 * This class extends the PSR-7 ServerRequest with additional functionality for
 * content type negotiation and request type detection.
 */
class Request implements RequestInterface
{
    private PsrRequest $psrRequest;

    /**
     * @param string $method HTTP method.
     * @param string|UriInterface $uri URI.
     * @param array $headers Request headers.
     * @param string|resource|StreamInterface|null $body Request body.
     * @param string $version Protocol version.
     * @param array $serverParams Typically the $_SERVER superglobal.
     */
    public function __construct(
        string $method,
        string|UriInterface $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        array $serverParams = []
    ) {
        $this->psrRequest = new PsrRequest(
            $method,
            $uri,
            $headers,
            $body,
            $version,
            $serverParams
        );
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->getQueryParams()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $key, mixed $default = null): mixed
    {
        $body = $this->getParsedBody();
        if (!is_array($body)) {
            return $default;
        }
        return $body[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function header(string $name, string $default = ''): string
    {
        $values = $this->getHeader($name);
        return $values[0] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeaderLine('Content-Type');
        return str_contains($contentType, 'application/json');
    }

    /**
     * {@inheritDoc}
     */
    public function json(): ?array
    {
        if (!$this->isJson()) {
            return null;
        }

        try {
            $content = (string) $this->getBody();
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return array_merge(
            $this->getQueryParams(),
            (array)$this->getParsedBody(),
            $this->json() ?? []
        );
    }

    /**
     * {@inheritDoc}
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * {@inheritDoc}
     */
    public function file(string $key): ?UploadedFileInterface
    {
        return $this->getUploadedFiles()[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file !== null && $file->getError() === UPLOAD_ERR_OK;
    }

    /**
     * {@inheritDoc}
     */
    public function files(): array
    {
        return $this->getUploadedFiles();
    }

    /**
     * {@inheritDoc}
     */
    public function getPreferredContentType(): ContentType
    {
        // Check URL extension.
        $path = $this->getUri()->getPath();
        $contentType = ContentType::fromFilename($path);
        if ($contentType !== ContentType::OCTET_STREAM) {
            return $contentType;
        }

        // Check if it's an API request.
        if ($this->isApiRequest()) {
            return ContentType::JSON;
        }

        // Check if it's an XHR request.
        // https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest.
        if ($this->isXmlHttpRequest()) {
            return ContentType::JSON;
        }

        // Check Accept header.
        return $this->getAcceptedContentType();
    }

    /**
     * {@inheritDoc}
     */
    public function getPreferredFormat(): string
    {
        return $this->getPreferredContentType()->getSubType();
    }

    /**
     * {@inheritDoc}
     */
    public function isApiRequest(): bool
    {
        $path = $this->getUri()->getPath();

        return $path === '/api' || str_starts_with($path, '/api/');
    }

    /**
     * {@inheritDoc}
     */
    public function isXmlHttpRequest(): bool
    {
        return $this->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Determines the content type based on the Accept header.
     *
     * Processes the Accept header and returns the most appropriate content
     * type. If no Accept header is present or none of the accepted types are
     * supported, defaults to HTML.
     *
     * @return ContentType The most appropriate content type.
     */
    private function getAcceptedContentType(): ContentType
    {
        $accept = $this->getHeaderLine('Accept');
        if (empty($accept)) {
            return ContentType::HTML;
        }

        // Parse Accept header with q values.
        $types = [];
        foreach (explode(',', $accept) as $type) {
            $parts = explode(';', trim($type));
            $mediaType = $parts[0];

            // Get q value if present, default to 1.0.
            $q = 1.0;
            if (isset($parts[1]) && str_starts_with($parts[1], 'q=')) {
                $q = (float)substr($parts[1], 2);
            }

            $types[$mediaType] = $q;
        }

        // Sort by q value, highest first.
        arsort($types);

        // Return first matching supported type.
        foreach ($types as $type => $q) {
            if (str_contains($type, 'application/json')) {
                return ContentType::JSON;
            }
            if (str_contains($type, 'text/html')) {
                return ContentType::HTML;
            }
            if (str_contains($type, 'text/plain')) {
                return ContentType::PLAIN;
            }
        }

        return ContentType::HTML;
    }

    /**
     * {@inheritDoc}
     */
    public function getServerParams(): array
    {
        return $this->psrRequest->getServerParams();
    }

    /**
     * {@inheritDoc}
     */
    public function getCookieParams(): array
    {
        return $this->psrRequest->getCookieParams();
    }

    /**
     * {@inheritDoc}
     */
    public function withCookieParams(array $cookies): static
    {
        $this->psrRequest = $this->psrRequest->withCookieParams($cookies);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryParams(): array
    {
        return $this->psrRequest->getQueryParams();
    }

    /**
     * {@inheritDoc}
     */
    public function withQueryParams(array $query): static
    {
        $this->psrRequest = $this->psrRequest->withQueryParams($query);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getUploadedFiles(): array
    {
        return $this->psrRequest->getUploadedFiles();
    }

    /**
     * {@inheritDoc}
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $this->psrRequest = $this->psrRequest->withUploadedFiles($uploadedFiles);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getParsedBody()
    {
        return $this->psrRequest->getParsedBody();
    }

    /**
     * {@inheritDoc}
     */
    public function withParsedBody($data): static
    {
        $this->psrRequest = $this->psrRequest->withParsedBody($data);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes(): array
    {
        return $this->psrRequest->getAttributes();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute($name, $default = null)
    {
        return $this->psrRequest->getAttribute($name, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function withAttribute($name, $value): static
    {
        $this->psrRequest = $this->psrRequest->withAttribute($name, $value);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutAttribute($name): static
    {
        $this->psrRequest = $this->psrRequest->withoutAttribute($name);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequestTarget(): string
    {
        return $this->psrRequest->getRequestTarget();
    }

    /**
     * {@inheritDoc}
     */
    public function withRequestTarget($requestTarget): static
    {
        $this->psrRequest = $this->psrRequest->withRequestTarget($requestTarget);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): string
    {
        return $this->psrRequest->getMethod();
    }

    /**
     * {@inheritDoc}
     */
    public function withMethod($method): static
    {
        $this->psrRequest = $this->psrRequest->withMethod($method);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getUri(): UriInterface
    {
        return $this->psrRequest->getUri();
    }

    /**
     * {@inheritDoc}
     */
    public function withUri($uri, $preserveHost = false): static
    {
        $this->psrRequest = $this->psrRequest->withUri($uri, $preserveHost);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->psrRequest->getProtocolVersion();
    }

    /**
     * {@inheritDoc}
     */
    public function withProtocolVersion($version): static
    {
        $this->psrRequest = $this->psrRequest->withProtocolVersion($version);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return $this->psrRequest->getHeaders();
    }

    /**
     * {@inheritDoc}
     */
    public function hasHeader($name): bool
    {
        return $this->psrRequest->hasHeader($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getHeader($name): array
    {
        return $this->psrRequest->getHeader($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderLine($name): string
    {
        return $this->psrRequest->getHeaderLine($name);
    }

    /**
     * {@inheritDoc}
     */
    public function withHeader($name, $value): static
    {
        $this->psrRequest = $this->psrRequest->withHeader($name, $value);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withAddedHeader($name, $value): static
    {
        $this->psrRequest = $this->psrRequest->withAddedHeader($name, $value);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutHeader($name): static
    {
        $this->psrRequest = $this->psrRequest->withoutHeader($name);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(): StreamInterface
    {
        return $this->psrRequest->getBody();
    }

    /**
     * {@inheritDoc}
     */
    public function withBody(StreamInterface $body): static
    {
        $this->psrRequest = $this->psrRequest->withBody($body);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function route(): RouteMatchInterface
    {
        $route = $this->psrRequest->getAttribute(RouterMiddleware::ROUTE_ATTRIBUTE);

        if (!$route instanceof RouteMatchInterface) {
            throw new LogicException(
                'Route match not found. Ensure RouterMiddleware is executed before using the $request->route() method.'
            );
        }

        return $route;
    }

    /**
     * {@inheritDoc}
     */
    public function session(): SessionInterface
    {
        $session = $this->psrRequest->getAttribute(
            SessionMiddleware::SESSION_ATTRIBUTE
        );

        if (!$session instanceof SessionInterface) {
            throw new LogicException('Session not found.');
        }

        return $session;
    }

    /**
     * {@inheritDoc}
     */
    public function flash(): FlashMessagesInterface
    {
        $flashMessages = $this->psrRequest->getAttribute(
            FlashMessageMiddleware::FLASH_ATTRIBUTE
        );

        if (!$flashMessages instanceof FlashMessagesInterface) {
            throw new LogicException('Flash messages not found.');
        }

        return $flashMessages;
    }

    /**
     * {@inheritDoc}
     */
    public function user(): ?UserInterface
    {
        return $this->psrRequest->getAttribute(UserInterface::class);
    }
}
