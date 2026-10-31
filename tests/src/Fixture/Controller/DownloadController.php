<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Fixture\Controller;

use Derafu\Http\Contract\ControllerInterface;
use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Response;
use Derafu\Routing\Attribute\Route;

/**
 * A controller whose route answers with a download.
 */
final class DownloadController implements ControllerInterface
{
    public const CONTENT = '0123456789';

    #[Route(path: '/download', name: 'download')]
    public function index(): ResponseInterface
    {
        return (new Response())->asDownload(self::CONTENT, 'data.txt', ContentType::PLAIN);
    }
}
