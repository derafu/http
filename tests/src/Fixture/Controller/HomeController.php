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
use Derafu\Routing\Attribute\Route;

/**
 * A controller with two routes, one of them with all its options, and a method
 * that is not a route.
 */
final class HomeController implements ControllerInterface
{
    #[Route(path: '/', name: 'home')]
    public function index(): string
    {
        return 'home';
    }

    #[Route(
        path: '/posts/{id}',
        name: 'post',
        methods: ['GET', 'HEAD'],
        defaults: ['id' => '1'],
        handler: 'App\\Other::show',
        roles: ['ROLE_USER']
    )]
    public function show(): string
    {
        return 'post';
    }

    public function helper(): string
    {
        return 'not a route';
    }
}
