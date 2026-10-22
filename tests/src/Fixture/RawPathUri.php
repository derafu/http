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

use Nyholm\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * A URI that gives its path as it was written, without encoding it. PSR-7 does
 * not say that a path is encoded, so a middleware must not trust that the URI
 * it receives does it (the one of Nyholm does: a null byte is `%00`).
 */
final class RawPathUri implements UriInterface
{
    private Uri $uri;

    public function __construct(private string $path)
    {
        $this->uri = new Uri('http://localhost');
    }

    public function getScheme(): string
    {
        return $this->uri->getScheme();
    }

    public function getAuthority(): string
    {
        return $this->uri->getAuthority();
    }

    public function getUserInfo(): string
    {
        return $this->uri->getUserInfo();
    }

    public function getHost(): string
    {
        return $this->uri->getHost();
    }

    public function getPort(): ?int
    {
        return $this->uri->getPort();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->uri->getQuery();
    }

    public function getFragment(): string
    {
        return $this->uri->getFragment();
    }

    public function withScheme(string $scheme): static
    {
        $new = clone $this;
        $new->uri = $this->uri->withScheme($scheme);

        return $new;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $new = clone $this;
        $new->uri = $this->uri->withUserInfo($user, $password);

        return $new;
    }

    public function withHost(string $host): static
    {
        $new = clone $this;
        $new->uri = $this->uri->withHost($host);

        return $new;
    }

    public function withPort(?int $port): static
    {
        $new = clone $this;
        $new->uri = $this->uri->withPort($port);

        return $new;
    }

    public function withPath(string $path): static
    {
        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    public function withQuery(string $query): static
    {
        $new = clone $this;
        $new->uri = $this->uri->withQuery($query);

        return $new;
    }

    public function withFragment(string $fragment): static
    {
        $new = clone $this;
        $new->uri = $this->uri->withFragment($fragment);

        return $new;
    }

    public function __toString(): string
    {
        return 'http://localhost' . $this->path;
    }
}
