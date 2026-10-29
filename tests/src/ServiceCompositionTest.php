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
use Derafu\Http\Contract\RequestStackInterface;
use Derafu\Http\Twig\AppVariable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Verify the shared standalone graph, tag ordering and opt-in static serving.
 */
#[CoversNamespace('Derafu\Http')]
final class ServiceCompositionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu-http-services-' . bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($this->directory));
        file_put_contents($this->directory . '/site.css', 'body{color:blue}');
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/site.css');
        rmdir($this->directory);
    }

    private function container(string $config = 'contributions.yaml'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 2));
        $container->setParameter('derafu.http.static_files.directory', $this->directory);
        $directories = [__DIR__ . '/../fixtures/config'];
        foreach (['derafu/routing', 'derafu/renderer'] as $package) {
            $directories[] = (InstalledVersions::getInstallPath($package)
                ?? throw new RuntimeException($package . ' must be installed for these tests.')) . '/resources/config';
        }
        (new YamlFileLoader($container, new FileLocator($directories)))->load($config);
        $container->compile(true);

        return $container;
    }

    private function handler(ContainerBuilder $container): RequestHandlerInterface
    {
        $handler = $container->get(RequestHandlerInterface::class);
        $this->assertInstanceOf(RequestHandlerInterface::class, $handler);

        return $handler;
    }

    private function request(string $path): ServerRequest
    {
        return new ServerRequest('GET', 'https://example.com' . $path, [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
    }

    public function testContributionsRunAtTheirPrioritiesAroundTheRealRouter(): void
    {
        $handler = $this->handler($this->container());
        foreach (['/' => 'home', '/page' => 'page'] as $path => $name) {
            $response = $handler->handle($this->request($path));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('derafu', $response->getHeaderLine('X-Before-Request'));
            $this->assertSame('derafu', $response->getHeaderLine('X-After-Request'));
            $this->assertSame('127.0.0.1', $response->getHeaderLine('X-Before-Client'));
            $this->assertSame('127.0.0.1', $response->getHeaderLine('X-After-Client'));
            $this->assertSame('none', $response->getHeaderLine('X-Before-Route'));
            $this->assertSame($name, $response->getHeaderLine('X-After-Route'));
        }
    }

    public function testSharedTwigProviderReadsTheActiveRequestAndLeavesNoStateAfterwards(): void
    {
        $container = $this->container();
        $handler = $this->handler($container);
        $response = $handler->handle($this->request('/page'));
        $this->assertStringContainsString('<h1>page</h1>', (string) $response->getBody());
        $this->assertStringContainsString('<p>/page</p>', (string) $response->getBody());

        $stack = $container->get('test.requests');
        $app = $container->get('test.app');
        $this->assertInstanceOf(RequestStackInterface::class, $stack);
        $this->assertInstanceOf(AppVariable::class, $app);
        $this->assertNull($stack->getCurrentRequest());
        $this->assertNull($app->getRequest());

        $error = $handler->handle($this->request('/missing')->withHeader('Accept', 'application/json'));
        $this->assertSame(404, $error->getStatusCode());
        $this->assertNull($stack->getCurrentRequest());
        $this->assertNull($app->getRequest());
        $this->assertSame('home', (string) $handler->handle($this->request('/'))->getBody());
    }

    public function testStaticFilesRequireTheirOptionalConfiguration(): void
    {
        $base = $this->handler($this->container());
        $this->assertSame(404, $base->handle($this->request('/site.css'))->getStatusCode());

        $handler = $this->handler($this->container('static-services.yaml'));
        $response = $handler->handle($this->request('/site.css'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('body{color:blue}', (string) $response->getBody());
        $this->assertStringStartsWith('text/css', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($response->hasHeader('X-Before-Request'));
        $this->assertStringContainsString('max-age=86400', $response->getHeaderLine('Cache-Control'));

        // A later dynamic request still reaches the complete shared pipeline.
        $dynamic = $handler->handle($this->request('/'));
        $this->assertSame('home', (string) $dynamic->getBody());
        $this->assertSame('home', $dynamic->getHeaderLine('X-After-Route'));
    }
}
