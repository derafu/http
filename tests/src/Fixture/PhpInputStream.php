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

/**
 * A stream wrapper for the protocol `php` that gives `php://input` a body: in
 * a command line there is no body of a request to read, and a test needs one.
 *
 * Every other `php://` stream (`php://temp`, `php://memory`...) is the real
 * one, that the wrapper opens and gives back as it is.
 *
 *     PhpInputStream::register('{"name":"Ana"}');
 *     try {
 *         // The code that reads php://input.
 *     } finally {
 *         PhpInputStream::restore();
 *     }
 */
final class PhpInputStream
{
    /**
     * @var resource|null
     */
    public $context;

    private static string $input = '';

    /**
     * The real stream, when it is not `php://input`.
     *
     * @var resource|null
     */
    private $handle = null;

    private int $position = 0;

    public static function register(string $input): void
    {
        self::$input = $input;

        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);
    }

    public static function restore(): void
    {
        stream_wrapper_restore('php');
        self::$input = '';
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if ($path === 'php://input') {
            $this->position = 0;

            return true;
        }

        // The real wrapper is back only while the stream is opened.
        stream_wrapper_restore('php');
        $handle = fopen($path, $mode);
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);

        if ($handle === false) {
            return false;
        }
        $this->handle = $handle;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->handle !== null) {
            return fread($this->handle, max(1, $count));
        }

        $chunk = substr(self::$input, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_write(string $data): int
    {
        if ($this->handle !== null) {
            return (int) fwrite($this->handle, $data);
        }

        return 0;
    }

    public function stream_eof(): bool
    {
        if ($this->handle !== null) {
            return feof($this->handle);
        }

        return $this->position >= strlen(self::$input);
    }

    public function stream_tell(): int
    {
        if ($this->handle !== null) {
            return (int) ftell($this->handle);
        }

        return $this->position;
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        if ($this->handle !== null) {
            return fseek($this->handle, $offset, $whence) === 0;
        }

        $position = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            default => strlen(self::$input) + $offset,
        };
        if ($position < 0) {
            return false;
        }
        $this->position = $position;

        return true;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        if ($this->handle !== null) {
            return fstat($this->handle);
        }

        return ['size' => strlen(self::$input)];
    }

    public function stream_flush(): bool
    {
        return $this->handle !== null ? fflush($this->handle) : true;
    }

    public function stream_close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }
}
