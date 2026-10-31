<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Support;

use Derafu\Http\Support\RangeStream;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(RangeStream::class)]
final class RangeStreamTest extends TestCase
{
    private function window(int $offset, int $length): RangeStream
    {
        return new RangeStream(Stream::create('0123456789'), $offset, $length);
    }

    public function testItIsTheWindowOfTheStream(): void
    {
        $window = $this->window(2, 5);

        $this->assertSame(5, $window->getSize());
        $this->assertSame('23456', (string) $window);
    }

    public function testItReadsInPiecesAndStopsAtTheEndOfTheWindow(): void
    {
        $window = $this->window(2, 5);

        $this->assertSame('23', $window->read(2));
        $this->assertSame(2, $window->tell());
        $this->assertFalse($window->eof());
        $this->assertSame('456', $window->read(100));
        $this->assertTrue($window->eof());
        $this->assertSame('', $window->read(1));
    }

    public function testItIsReadAgainAfterRewinding(): void
    {
        $window = $this->window(0, 4);
        $window->getContents();
        $window->rewind();

        $this->assertSame('0123', $window->getContents());
    }

    public function testItSeeksInsideTheWindow(): void
    {
        $window = $this->window(3, 4);

        $window->seek(2);
        $this->assertSame('56', $window->read(10));

        $window->seek(-1, SEEK_END);
        $this->assertSame('6', $window->read(10));

        $window->seek(1);
        $window->seek(1, SEEK_CUR);
        $this->assertSame('5', $window->read(1));
    }

    public function testItKeepsItsPlaceAlthoughTheStreamIsMoved(): void
    {
        $inner = Stream::create('0123456789');
        $window = new RangeStream($inner, 2, 5);

        $this->assertSame('23', $window->read(2));
        $inner->seek(9);

        $this->assertSame('456', $window->read(3));
    }

    public function testItCanOnlyBeRead(): void
    {
        $window = $this->window(0, 1);

        $this->assertTrue($window->isReadable());
        $this->assertFalse($window->isWritable());
        $this->assertTrue($window->isSeekable());

        $this->expectException(Throwable::class);
        $window->write('x');
    }

    public function testASeekBeforeTheStartIsAnError(): void
    {
        $this->expectException(Throwable::class);

        $this->window(0, 3)->seek(-1);
    }

    public function testASeekModeThatIsNotValidIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->window(0, 3)->seek(0, 99);
    }

    public function testAnOffsetOrLengthBelowZeroIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->window(-1, 3);
    }

    public function testAStreamThatCannotBeMovedIsAnError(): void
    {
        $handle = fopen('php://output', 'w');
        $this->assertNotFalse($handle);

        try {
            new RangeStream(Stream::create($handle), 0, 1);
            $this->fail('A stream that cannot be moved was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(TranslatableInterface::class, $e);
        }
    }

    public function testTheMetadataAndTheCloseAreThoseOfTheStream(): void
    {
        $window = $this->window(0, 3);

        $this->assertSame('w+b', $window->getMetadata('mode'));

        $window->close();
        $this->assertNull($window->detach());
    }

    private function brokenStream(): \Psr\Http\Message\StreamInterface
    {
        $stream = $this->createStub(\Psr\Http\Message\StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('seek')->willThrowException(new \RuntimeException('Cannot seek.'));

        return $stream;
    }

    public function testTheTextOfAWindowThatCannotBeReadIsEmpty(): void
    {
        $this->assertSame('', (string) new RangeStream($this->brokenStream(), 0, 5));
    }

    public function testTheContentsStopWhenTheStreamHasNoMoreData(): void
    {
        // The file became shorter while it was sent.
        $stream = $this->createStub(\Psr\Http\Message\StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('read')->willReturn('');

        $this->assertSame('', (new RangeStream($stream, 0, 5))->getContents());
    }
}
