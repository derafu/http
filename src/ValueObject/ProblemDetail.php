<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\ValueObject;

use Derafu\Http\Contract\ProblemDetailInterface;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Contract\SafeThrowableInterface;
use Derafu\Http\Enum\HttpStatus;

/**
 * Encapsulates error information using as a base RFC 7807.
 *
 * This value object contains all relevant information about an error including:
 *
 *   - HTTP status and basic error details.
 *   - Exception information and stack trace.
 *   - Request context.
 *   - Environment information.
 *
 * It provides an immutable representation of error data that can be used
 * consistently across different error rendering formats (JSON, HTML, etc).
 */
class ProblemDetail implements ProblemDetailInterface
{
    /**
     * Creates a new HTTP Problem Detail instance.
     *
     * @param string $type
     * @param string|null $title
     * @param HttpStatus $httpStatus
     * @param string $detail
     * @param RequestInterface $request
     * @param SafeThrowableInterface $throwable
     * @param array $context
     * @param string $timestamp
     * @param string $environment
     * @param boolean $debug
     */
    public function __construct(
        private readonly HttpStatus $httpStatus,
        private readonly string $detail,
        private readonly RequestInterface $request,
        private readonly SafeThrowableInterface $throwable,
        private readonly string $timestamp,
        private readonly string $environment,
        private readonly string $type = 'about:blank',
        private readonly ?string $title = null,
        private readonly array $context = [],
        private readonly bool $debug = true
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        if ($this->type === 'about:blank' || $this->title === null) {
            return $this->httpStatus->getReasonPhrase();
        }

        return $this->title;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): int
    {
        return $this->httpStatus->value;
    }

    /**
     * {@inheritDoc}
     */
    public function getDetail(): string
    {
        return $this->detail;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstance(): ?string
    {
        return $this->request->getUri()->getPath();
    }

    /**
     * {@inheritDoc}
     */
    public function getHttpStatus(): HttpStatus
    {
        return $this->httpStatus;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * {@inheritDoc}
     */
    public function getThrowable(): SafeThrowableInterface
    {
        return $this->throwable;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    /**
     * {@inheritDoc}
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * {@inheritDoc}
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        $output = "# An Error Occurred\n\n";

        $output .= "## HTTP Problem Detail\n\n";
        $output .= "- Type: `{$this->getType()}`.\n";
        $output .= "- Title: `{$this->getTitle()}`.\n";
        $output .= "- Status: `{$this->getStatus()} ({$this->httpStatus->getReasonPhrase()})`.\n";
        $output .= "- Detail: {$this->getDetail()}\n";
        $output .= "- Instance: `{$this->getInstance()}`.\n\n";

        $output .= "## Environment\n\n";
        $output .= "- Timestamp: `{$this->getTimestamp()}`.\n";
        $output .= "- Environment: `{$this->getEnvironment()}`.\n";
        $output .= "- Debug: `{$this->isDebug()}`.\n\n";

        if (!empty($this->context)) {
            $flags =
                JSON_PRETTY_PRINT
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_UNESCAPED_LINE_TERMINATORS
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ;
            $output .= "## Context\n\n";
            $output .= "```json\n" . json_encode($this->context, $flags) . "\n```\n\n";
        }

        if ($this->isDebug()) {
            $output .= "## Throwable\n\n";
            $output .= "```\n" . $this->getThrowable() . "\n```\n\n";
        }

        return $output;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'title' => $this->getTitle(),
            'status' => $this->getStatus(),
            'detail' => $this->getDetail(),
            'instance' => $this->getInstance(),
            'extensions' => [
                'timestamp' => $this->getTimeStamp(),
                'environment' => $this->getEnvironment(),
                'debug' => $this->isDebug(),
                'context' => $this->getContext(),
                'throwable' => $this->isDebug() ? $this->getThrowable() : null,
            ],
        ];
    }
}
