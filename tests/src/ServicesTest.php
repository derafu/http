<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp;

use Derafu\Http\Contract\DispatcherInterface;
use Derafu\Http\Contract\ProblemHandlerInterface;
use Derafu\Http\Middleware\ClientIpMiddleware;
use Derafu\Http\Service\ProblemHandler;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * What an application gets by importing the file of the package, and how the
 * environment variables configure who the client of a request is.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private const VARIABLES = [
        'HTTP_TRUSTED_PROXIES',
        'HTTP_CLIENT_IP_HEADERS',
        'HTTP_CLIENT_NETWORK_IPV4_PREFIX',
        'HTTP_CLIENT_NETWORK_IPV6_PREFIX',
    ];

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $name) {
            putenv($name);
        }
    }

    /**
     * The middleware, made by the container from the file of the package.
     */
    private function middleware(): ClientIpMiddleware
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('http-services.yaml');

        // Only this service: the others need the application around them.
        foreach (array_keys($container->getDefinitions()) as $id) {
            if ($id !== ClientIpMiddleware::class && $id !== 'service_container') {
                $container->removeDefinition($id);
            }
        }
        $container->getDefinition(ClientIpMiddleware::class)->setPublic(true);
        $container->compile(true);

        $middleware = $container->get(ClientIpMiddleware::class);
        $this->assertInstanceOf(ClientIpMiddleware::class, $middleware);

        return $middleware;
    }

    /**
     * What the middleware says about a connection and a header.
     *
     * @param array<string, string> $headers
     * @return array{string, string}
     */
    private function client(ClientIpMiddleware $middleware, string $remote, array $headers = []): array
    {
        $request = new ServerRequest('GET', '/', $headers, null, '1.1', ['REMOTE_ADDR' => $remote]);

        $seen = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };
        $middleware->process($request, $seen);

        return [
            (string) $seen->request?->getAttribute(ClientIpMiddleware::ATTRIBUTE),
            (string) $seen->request?->getAttribute(ClientIpMiddleware::NETWORK_ATTRIBUTE),
        ];
    }

    #[Test]
    public function shouldNotBelieveAnyHeaderByDefault(): void
    {
        $middleware = $this->middleware();

        $this->assertSame(
            ['10.0.0.1', '10.0.0.1/32'],
            $this->client($middleware, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9'])
        );
        $this->assertSame(
            ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::/64'],
            $this->client($middleware, '2001:db8:1:2:3:4:5:6')
        );
    }

    #[Test]
    public function shouldBelieveTheTrustedProxiesOfTheEnvironment(): void
    {
        putenv('HTTP_TRUSTED_PROXIES=private, 203.0.113.0/24');

        $middleware = $this->middleware();

        // A proxy of the shortcut, and one of the range.
        $this->assertSame('198.51.100.4', $this->client($middleware, '10.0.0.1', ['X-Forwarded-For' => '198.51.100.4'])[0]);
        $this->assertSame('198.51.100.4', $this->client($middleware, '203.0.113.50', ['X-Forwarded-For' => '198.51.100.4'])[0]);

        // And not the others.
        $this->assertSame('198.51.100.9', $this->client($middleware, '198.51.100.9', ['X-Forwarded-For' => '192.0.2.1'])[0]);
    }

    #[Test]
    public function shouldReadTheHeadersOfTheEnvironmentInOrder(): void
    {
        putenv('HTTP_TRUSTED_PROXIES=private');
        putenv('HTTP_CLIENT_IP_HEADERS=CF-Connecting-IP, X-Real-IP');

        $middleware = $this->middleware();

        $both = ['CF-Connecting-IP' => '198.51.100.4', 'X-Real-IP' => '192.0.2.1', 'X-Forwarded-For' => '203.0.113.9'];
        $this->assertSame('198.51.100.4', $this->client($middleware, '10.0.0.1', $both)[0]);

        // X-Forwarded-For is not in the list.
        $this->assertSame('10.0.0.1', $this->client($middleware, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.9'])[0]);
    }

    #[Test]
    public function shouldUseThePrefixesOfTheEnvironmentForTheNetwork(): void
    {
        putenv('HTTP_CLIENT_NETWORK_IPV4_PREFIX=24');
        putenv('HTTP_CLIENT_NETWORK_IPV6_PREFIX=48');

        $middleware = $this->middleware();

        $this->assertSame('203.0.113.0/24', $this->client($middleware, '203.0.113.77')[1]);
        $this->assertSame('2001:db8:1::/48', $this->client($middleware, '2001:db8:1:2:3:4:5:6')[1]);
    }

    #[Test]
    public function shouldRefuseAConfigurationThatIsWrong(): void
    {
        putenv('HTTP_TRUSTED_PROXIES=private,cloudflare');

        $this->expectException(TranslatableInvalidArgumentException::class);

        $this->middleware();
    }

    /**
     * The error pages that the handler of the problems got from the container.
     *
     * @param array<int|string, string>|null $pages The parameter of the
     * application, or null for the one of the package.
     * @return array<int|string, mixed>
     */
    private function errorPages(?array $pages = null): array
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('http-services.yaml');
        if ($pages !== null) {
            $container->setParameter('http.error_pages', $pages);
        }

        // Only this service, with the dispatcher that the application gives.
        foreach (array_keys($container->getDefinitions()) as $id) {
            if ($id !== ProblemHandlerInterface::class && $id !== 'service_container') {
                $container->removeDefinition($id);
            }
        }
        $container->register(DispatcherInterface::class)->setSynthetic(true)->setPublic(true);
        $container->getDefinition(ProblemHandlerInterface::class)->setPublic(true)->setLazy(false);
        $container->compile(true);
        $container->set(DispatcherInterface::class, $this->createStub(DispatcherInterface::class));

        $handler = $container->get(ProblemHandlerInterface::class);
        $this->assertInstanceOf(ProblemHandler::class, $handler);

        $property = new \ReflectionProperty(ProblemHandler::class, 'pages');
        $value = $property->getValue($handler);
        $this->assertIsArray($value);

        return $value;
    }

    #[Test]
    public function shouldHaveNoErrorPagesByDefault(): void
    {
        $this->assertSame([], $this->errorPages());
    }

    #[Test]
    public function shouldGiveTheErrorPagesOfTheApplicationToTheHandler(): void
    {
        $pages = [404 => '/templates/error404.html.twig', 'default' => '/templates/error.html.twig'];

        $this->assertSame($pages, $this->errorPages($pages));
    }
}
