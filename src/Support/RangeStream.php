<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Support;

use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * A read-only window over a seekable stream: the bytes from `$offset` for
 * `$length` bytes.
 *
 * It is the body of a partial response (`206 Partial Content`): the file is not
 * read into memory, only the requested part is read, when it is sent.
 */
final class RangeStream implements StreamInterface
{
    /**
     * Position inside the window.
     */
    private int $position = 0;

    public function __construct(
        private readonly StreamInterface $stream,
        private readonly int $offset,
        private readonly int $length
    ) {
        if ($offset < 0 || $length < 0) {
            throw new InvalidArgumentException('The window of a stream must not have a negative offset or length.');
        }

        if (!$stream->isSeekable() || !$stream->isReadable()) {
            throw new InvalidArgumentException('The stream of a window must be seekable and readable.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        try {
            $this->position = 0;

            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->stream->close();
    }

    /**
     * {@inheritDoc}
     */
    public function detach()
    {
        return $this->stream->detach();
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return $this->length;
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * {@inheritDoc}
     */
    public function eof(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * {@inheritDoc}
     */
    public function isSeekable(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $position = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => $this->length + $offset,
            default => throw new InvalidArgumentException(['The seek mode {whence} is not valid.', 'whence' => $whence]),
        };

        if ($position < 0) {
            throw new RuntimeException(['Cannot seek to the position {position} of the window.', 'position' => $position]);
        }

        $this->position = $position;
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $string): int
    {
        throw new RuntimeException('The window of a stream is read-only.');
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $length): string
    {
        $length = min($length, $this->length - $this->position);
        if ($length <= 0) {
            return '';
        }

        // The stream may have been moved by whoever shares it.
        $this->stream->seek($this->offset + $this->position);
        $data = $this->stream->read($length);
        $this->position += strlen($data);

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getContents(): string
    {
        $contents = '';
        while (!$this->eof()) {
            $chunk = $this->read(8192);
            if ($chunk === '') {
                break;
            }
            $contents .= $chunk;
        }

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(?string $key = null)
    {
        return $this->stream->getMetadata($key);
    }
}
