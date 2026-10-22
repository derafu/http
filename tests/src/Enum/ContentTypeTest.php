<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Enum;

use Derafu\Http\Enum\ContentType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A content type knows its parts, which kind of content it is and which one a
 * file has by its extension.
 */
#[CoversClass(ContentType::class)]
final class ContentTypeTest extends TestCase
{
    /**
     * @return array<string, array{string, ContentType}>
     */
    public static function provideExtensions(): array
    {
        $extensions = [
            'js' => ContentType::JAVASCRIPT, 'ts' => ContentType::TYPESCRIPT,
            'json' => ContentType::JSON, 'map' => ContentType::JSON,
            'yaml' => ContentType::YAML, 'yml' => ContentType::YAML,
            'xml' => ContentType::XML,
            'html' => ContentType::HTML, 'htm' => ContentType::HTML,
            'txt' => ContentType::PLAIN, 'css' => ContentType::CSS, 'csv' => ContentType::CSV,
            'md' => ContentType::MARKDOWN, 'markdown' => ContentType::MARKDOWN,
            'pdf' => ContentType::PDF, 'zip' => ContentType::ZIP, 'rar' => ContentType::RAR,
            'tar' => ContentType::TAR, 'gz' => ContentType::GZIP, 'wasm' => ContentType::WASM,
            'png' => ContentType::PNG, 'jpg' => ContentType::JPEG, 'jpeg' => ContentType::JPEG,
            'gif' => ContentType::GIF, 'svg' => ContentType::SVG, 'webp' => ContentType::WEBP,
            'ico' => ContentType::ICO, 'bmp' => ContentType::BMP,
            'tiff' => ContentType::TIFF, 'tif' => ContentType::TIFF, 'avif' => ContentType::AVIF,
            'mp3' => ContentType::MP3, 'wav' => ContentType::WAV, 'ogg' => ContentType::OGG_AUDIO,
            'aac' => ContentType::AAC, 'flac' => ContentType::FLAC,
            'mp4' => ContentType::MP4, 'm4v' => ContentType::MP4, 'webm' => ContentType::WEBM,
            'avi' => ContentType::AVI, 'mov' => ContentType::MOV,
            'woff' => ContentType::WOFF, 'woff2' => ContentType::WOFF2,
            'ttf' => ContentType::TTF, 'otf' => ContentType::OTF, 'eot' => ContentType::EOT,
            'doc' => ContentType::DOC, 'docx' => ContentType::DOCX,
            'xls' => ContentType::XLS, 'xlsx' => ContentType::XLSX,
            'ppt' => ContentType::PPT, 'pptx' => ContentType::PPTX,
        ];

        $cases = [];
        foreach ($extensions as $extension => $type) {
            $cases[$extension] = [$extension, $type];
        }

        return $cases;
    }

    #[DataProvider('provideExtensions')]
    public function testAnExtensionHasItsContentType(string $extension, ContentType $expected): void
    {
        $this->assertSame($expected, ContentType::fromExtension($extension));
    }

    public function testTheExtensionIsReadWithoutCase(): void
    {
        $this->assertSame(ContentType::PNG, ContentType::fromExtension('PNG'));
        $this->assertSame(ContentType::JAVASCRIPT, ContentType::fromExtension('Js'));
    }

    public function testAnExtensionThatIsNotKnownIsBinary(): void
    {
        $this->assertSame(ContentType::OCTET_STREAM, ContentType::fromExtension('xyz'));
        $this->assertSame(ContentType::OCTET_STREAM, ContentType::fromExtension(''));
    }

    public function testTheFilenameGivesTheContentTypeOfItsExtension(): void
    {
        $this->assertSame(ContentType::CSS, ContentType::fromFilename('/assets/css/app.css'));
        $this->assertSame(ContentType::JAVASCRIPT, ContentType::fromFilename('app.min.js'));
        $this->assertSame(ContentType::JSON, ContentType::fromFilename('/bundle.js.map'));
    }

    public function testAFilenameWithoutExtensionIsBinary(): void
    {
        $this->assertSame(ContentType::OCTET_STREAM, ContentType::fromFilename('/users/ana'));
        $this->assertSame(ContentType::OCTET_STREAM, ContentType::fromFilename('/'));
    }

    public function testTheTypeAndTheSubtypeAreItsParts(): void
    {
        $this->assertSame('application', ContentType::JSON->getMainType());
        $this->assertSame('json', ContentType::JSON->getSubType());
        $this->assertSame('svg+xml', ContentType::SVG->getSubType());
        $this->assertSame('image', ContentType::SVG->getMainType());
    }

    public function testTheCharsetIsUtf8ByDefault(): void
    {
        $this->assertSame('text/html; charset=UTF-8', ContentType::HTML->withCharset());
        $this->assertSame('text/html; charset=ISO-8859-1', ContentType::HTML->withCharset('ISO-8859-1'));
    }

    /**
     * @return array<string, array{ContentType, bool}>
     */
    public static function provideText(): array
    {
        return [
            'html' => [ContentType::HTML, true],
            'plain' => [ContentType::PLAIN, true],
            'markdown' => [ContentType::MARKDOWN, true],
            'event stream' => [ContentType::EVENT_STREAM, true],
            'json' => [ContentType::JSON, true],
            'yaml' => [ContentType::YAML, true],
            'xml' => [ContentType::XML, true],
            'javascript' => [ContentType::JAVASCRIPT, true],
            'typescript' => [ContentType::TYPESCRIPT, true],
            'problem json' => [ContentType::PROBLEM_JSON, true],
            'problem xml' => [ContentType::PROBLEM_XML, true],
            'pdf' => [ContentType::PDF, false],
            'png' => [ContentType::PNG, false],
            'zip' => [ContentType::ZIP, false],
            'octet stream' => [ContentType::OCTET_STREAM, false],
        ];
    }

    #[DataProvider('provideText')]
    public function testTheTextTypesAreTheOnesThatHaveCharset(ContentType $type, bool $isText): void
    {
        $this->assertSame($isText, $type->isText());
    }

    public function testEachKindOfContentIsTold(): void
    {
        $this->assertTrue(ContentType::PNG->isImage());
        $this->assertFalse(ContentType::PDF->isImage());

        $this->assertTrue(ContentType::MP3->isAudio());
        $this->assertFalse(ContentType::MP4->isAudio());

        $this->assertTrue(ContentType::MP4->isVideo());
        $this->assertFalse(ContentType::MP3->isVideo());

        $this->assertTrue(ContentType::WOFF2->isFont());
        $this->assertTrue(ContentType::EOT->isFont());
        $this->assertFalse(ContentType::PNG->isFont());
    }

    public function testEverythingExceptTheBinaryDefaultIsStatic(): void
    {
        $this->assertTrue(ContentType::CSS->isStatic());
        $this->assertTrue(ContentType::PNG->isStatic());
        $this->assertFalse(ContentType::OCTET_STREAM->isStatic());
    }
}
