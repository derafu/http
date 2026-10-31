<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Fixture;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A stream of a given size whose bytes are made when they are read: it never
 * holds more than the piece that is being read, so it stands for a file that is
 * larger than the memory.
 */
final class GeneratedStream implements StreamInterface
{
    private int $position = 0;

    public function __construct(private readonly int $size, private readonly string $byte = 'x')
    {
    }

    public function __toString(): string
    {
        throw new RuntimeException('A generated stream must not be read whole.');
    }

    public function close(): void
    {
    }

    public function detach()
    {
        return null;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= $this->size;
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->position = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            default => $this->size + $offset,
        };
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('Read-only.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $length = min($length, $this->size - $this->position);
        $this->position += max($length, 0);

        return $length > 0 ? str_repeat($this->byte, $length) : '';
    }

    public function getContents(): string
    {
        throw new RuntimeException('A generated stream must not be read whole.');
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
