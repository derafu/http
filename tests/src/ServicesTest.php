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

use Composer\InstalledVersions;
use Derafu\Http\Contract\ProblemFactoryInterface;
use Derafu\Http\Contract\ProblemHandlerInterface;
use Derafu\Http\Contract\RequestStackInterface;
use Derafu\Http\Exception\NotFoundException;
use Derafu\Http\Middleware\ClientIpMiddleware;
use Derafu\Http\Request;
use Derafu\Http\Service\ProblemHandler;
use Derafu\Http\Translation\HttpTranslationResourceProvider;
use Derafu\Http\Twig\AppVariable;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\VarExporter\LazyObjectInterface;

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
        $container = $this->fullContainer();

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

        $client = $seen->request?->getAttribute(ClientIpMiddleware::ATTRIBUTE);
        $network = $seen->request?->getAttribute(ClientIpMiddleware::NETWORK_ATTRIBUTE);
        $this->assertIsString($client);
        $this->assertIsString($network);

        return [$client, $network];
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
        $container = $this->fullContainer($pages);

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

    /**
     * Import the complete YAML and add only the application-owned services.
     *
     * @param array<int|string, string>|null $pages
     */
    private function fullContainer(?array $pages = null): ContainerBuilder
    {
        $root = dirname(__DIR__, 2);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $root);
        $directories = [$root . '/tests/fixtures/config'];
        foreach (['derafu/routing', 'derafu/renderer'] as $package) {
            $directories[] = (InstalledVersions::getInstallPath($package)
                ?? throw new RuntimeException($package . ' must be installed for these tests.')) . '/resources/config';
        }
        (new YamlFileLoader($container, new FileLocator($directories)))
            ->load('services.yaml');
        if ($pages !== null) {
            $container->setParameter('derafu.http.error_pages', $pages);
        }
        // Keep every declaration reachable, including intentionally lazy ones.
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile(true);

        return $container;
    }

    #[Test]
    public function everyDeclaredServiceCanBeConstructedInACompleteApplication(): void
    {
        $container = $this->fullContainer();
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'Derafu\\Http\\')) {
                continue;
            }
            $class = $definition->getClass();
            $this->assertNotNull($class);
            $this->assertTrue(class_exists($class) || interface_exists($class));
            $service = $container->get($id);
            if ($service instanceof LazyObjectInterface) {
                $service = $service->initializeLazyObject();
            }
            $this->assertInstanceOf($class, $service, $id);
        }
    }

    #[Test]
    public function configuredPipelineDispatchesControllersAndTemplatesAndClearsTheRequestStack(): void
    {
        $container = $this->fullContainer();
        $handler = $container->get(RequestHandlerInterface::class);
        $this->assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handle(new ServerRequest('GET', 'https://example.com/'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('home', (string) $response->getBody());

        $page = $handler->handle(new ServerRequest('GET', 'https://example.com/page'));
        $this->assertSame(200, $page->getStatusCode());
        $this->assertStringContainsString('<h1>page</h1>', (string) $page->getBody());
        $this->assertStringContainsString('<p>/page</p>', (string) $page->getBody());
        $stack = $container->get(RequestStackInterface::class);
        $this->assertInstanceOf(RequestStackInterface::class, $stack);
        $this->assertNull($stack->getCurrentRequest());
        $app = $container->get(AppVariable::class);
        $this->assertInstanceOf(AppVariable::class, $app);
        $this->assertNull($app->getRequest());
    }

    #[Test]
    public function configuredErrorPagesRenderThroughTheRealDispatcher(): void
    {
        $root = dirname(__DIR__, 2) . '/tests/fixtures/services/';
        $container = $this->fullContainer([404 => $root . 'not-found.html.twig', 'default' => $root . 'error.html.twig']);
        $handler = $container->get(RequestHandlerInterface::class);
        $this->assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handle(new ServerRequest('GET', 'https://example.com/missing', ['Accept' => 'text/html']));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('<h1>Custom 404</h1>', (string) $response->getBody());
        $this->assertStringContainsString('/missing', (string) $response->getBody());

        $factory = $container->get(ProblemFactoryInterface::class);
        $this->assertInstanceOf(ProblemFactoryInterface::class, $factory);
        $problem = $factory->create(new \RuntimeException('Application failure'), new Request('GET', '/failure', ['Accept' => 'text/html']));
        $problems = $container->get(ProblemHandlerInterface::class);
        $this->assertInstanceOf(ProblemHandlerInterface::class, $problems);
        $fallback = $problems->handle($problem);
        $this->assertSame(500, $fallback->getStatusCode());
        $this->assertStringContainsString('<h1>Custom error 500</h1>', (string) $fallback->getBody());
    }

    #[Test]
    public function taggedTranslationsReachTheProblemFactoryAndJsonResponse(): void
    {
        $container = $this->fullContainer();
        $resources = $container->get(HttpTranslationResourceProvider::class);
        $this->assertInstanceOf(HttpTranslationResourceProvider::class, $resources);
        $this->assertNotEmpty([...$resources->getDirectories()]);
        $factory = $container->get(ProblemFactoryInterface::class);
        $this->assertInstanceOf(ProblemFactoryInterface::class, $factory);
        $problem = $factory->create(new NotFoundException(), new Request('GET', '/missing', ['Accept' => 'application/json']));
        $this->assertSame('No encontrado', $problem->getTitle());
        $this->assertSame('No encontrado.', $problem->getDetail());
        $handler = $container->get(ProblemHandlerInterface::class);
        $this->assertInstanceOf(ProblemHandlerInterface::class, $handler);
        $response = $handler->handle($problem);
        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertSame('No encontrado', $data['title'] ?? null);
        $this->assertSame('No encontrado.', $data['detail'] ?? null);
    }
}
