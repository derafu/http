<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Twig;

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Http\Service\RequestHolder;
use Derafu\Routing\Contract\RouteMatchInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;

/**
 * The `app` variable of the templates: what every template needs to know about
 * the request that is being handled, as in Symfony.
 *
 *   - `app.request`: the request.
 *   - `app.route`: the route that matched.
 *   - `app.user`: the user, or `null` if nobody is authenticated (an anonymous
 *     user is nobody: `{% if app.user %}` works).
 *   - `app.session`: the session.
 *   - `app.flashes`: the flash messages of the request.
 *
 * It is not a copy of the request: it reads it when a template asks, so it is the
 * same variable in every template, and what is not there yet (no route, no
 * session, an error before the user is known) is `null`, not an error.
 */
final class AppVariable
{
    public function __construct(private readonly RequestHolder $holder)
    {
    }

    /**
     * The request that is being handled.
     */
    public function getRequest(): ?RequestInterface
    {
        return $this->holder->get();
    }

    /**
     * The route that matched, or null if none did (yet).
     */
    public function getRoute(): ?RouteMatchInterface
    {
        $route = $this->getRequest()?->getAttribute(RouterMiddleware::ROUTE_ATTRIBUTE);

        return $route instanceof RouteMatchInterface ? $route : null;
    }

    /**
     * The authenticated user, or null if there is none.
     *
     * An anonymous user (an object that stands for nobody, see `isAnonymous()` of
     * `derafu/auth`) is not an authenticated user: it is told by what it says,
     * not by its class, so this package does not depend on the one of the users.
     */
    public function getUser(): ?UserInterface
    {
        $user = $this->getRequest()?->getAttribute(UserInterface::class);
        if (!$user instanceof UserInterface) {
            return null;
        }

        if (method_exists($user, 'isAnonymous') && $user->isAnonymous()) {
            return null;
        }

        return $user;
    }

    /**
     * The session, or null if the request has none.
     */
    public function getSession(): ?SessionInterface
    {
        $session = $this->getRequest()?->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        return $session instanceof SessionInterface ? $session : null;
    }

    /**
     * The flash messages of the request, by type. Reading them does not take them
     * away: they can be read more than once in the same page.
     *
     * @return array<string, mixed>
     */
    public function getFlashes(): array
    {
        $flash = $this->getRequest()?->getAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE);

        return $flash instanceof FlashMessagesInterface ? $flash->getFlashes() : [];
    }
}
