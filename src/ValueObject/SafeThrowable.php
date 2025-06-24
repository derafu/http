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

use Derafu\Http\Contract\SafeThrowableInterface;

class SafeThrowable implements SafeThrowableInterface
{
    public function __construct(
        private readonly string $class,
        private readonly int $code,
        private readonly string $message,
        private readonly string $file,
        private readonly int $line,
        private readonly array $trace,
        private readonly ?SafeThrowableInterface $previous
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * {@inheritDoc}
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritDoc}
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * {@inheritDoc}
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * {@inheritDoc}
     */
    public function getTrace(bool $includeArgs = false): array
    {
        if ($includeArgs) {
            return $this->trace;
        }

        return array_map(function ($frame) {
            if (isset($frame['args'])) {
                $frame['args'] = [];
            }
            return $frame;
        }, $this->trace);
    }

    /**
     * {@inheritDoc}
     */
    public function getTraceAsString(bool $includeArgs = false): string
    {
        $output = [];

        foreach ($this->getTrace($includeArgs) as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? '?';
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $args = isset($frame['args'])
                ? implode(', ', array_map('gettype', $frame['args']))
                : ''
            ;

            $output[] = "#$index $file($line): $class$type$function(" . $args . ")";
        }

        return implode("\n", $output);
    }

    /**
     * {@inheritDoc}
     */
    public function getPrevious(): ?SafeThrowableInterface
    {
        return $this->previous;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        $output = "Class: {$this->class}\n";
        $output .= "Message: {$this->message}\n";
        $output .= "Code: {$this->code}\n";
        $output .= "File: {$this->file} ({$this->line})\n\n";
        $output .= "Stack trace:\n" . $this->getTraceAsString() . "\n";

        if ($this->previous) {
            $output .= "\nCaused by:\n" . $this->previous->__toString();
        }

        return $output;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): mixed
    {
        return [
            'class' => $this->class,
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->getTrace(),
            'previous' => $this->previous,
        ];
    }
}
