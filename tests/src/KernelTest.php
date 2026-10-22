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

use Derafu\Http\DependencyInjection\ControllerConfigurationCompilerPass;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Factory\InvokerFactory;
use Derafu\Http\Factory\ProblemFactory;
use Derafu\Http\Factory\RequestFactory;
use Derafu\Http\Factory\SafeThrowableFactory;
use Derafu\Http\Kernel;
use Derafu\Http\Middleware\DispatcherMiddleware;
use Derafu\Http\Middleware\RequestFactoryMiddleware;
use Derafu\Http\Middleware\ResponseNormalizerMiddleware;
use Derafu\Http\Middleware\RouterMiddleware;
use Derafu\Http\Request;
use Derafu\Http\Response;
use Derafu\Http\Service\Dispatcher;
use Derafu\Http\Service\ProblemHandler;
use Derafu\Http\Service\RequestHandler;
use Derafu\Http\Service\RequestStack;
use Derafu\Http\Service\ResponseNormalizer;
use Derafu\Http\Support\KernelParameters;
use Derafu\Http\ValueObject\ProblemDetail;
use Derafu\Http\ValueObject\SafeThrowable;
use Derafu\Kernel\Environment;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The kernel boots an application from its configuration files and handles a
 * request with the request handler of its container: the services of the
 * package, the routes of the files and the ones of the controllers, and the
 * invoker that calls the handlers.
 *
 * The application is the one of `tests/fixtures/app`.
 */
#[CoversClass(Kernel::class)]
#[CoversClass(InvokerFactory::class)]
#[UsesClass(ControllerConfigurationCompilerPass::class)]
#[UsesClass(ContentType::class)]
#[UsesClass(HttpStatus::class)]
#[UsesClass(ProblemFactory::class)]
#[UsesClass(RequestFactory::class)]
#[UsesClass(SafeThrowableFactory::class)]
#[UsesClass(DispatcherMiddleware::class)]
#[UsesClass(RequestFactoryMiddleware::class)]
#[UsesClass(ResponseNormalizerMiddleware::class)]
#[UsesClass(RouterMiddleware::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
#[UsesClass(Dispatcher::class)]
#[UsesClass(ProblemHandler::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(RequestStack::class)]
#[UsesClass(ResponseNormalizer::class)]
#[UsesClass(KernelParameters::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
final class KernelTest extends TestCase
{
    private string $cache;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/' . uniqid('derafu_http_kernel_');
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cache)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cache, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->cache);
    }

    private function kernel(): Kernel
    {
        $environment = new Environment('test', true, [], [
            'project' => dirname(__DIR__) . '/fixtures/app',
            'cache' => $this->cache,
        ]);

        return new Kernel($environment, true, uniqid('http_'));
    }

    private function send(Kernel $kernel, string $path, string $accept = 'text/html'): ResponseInterface
    {
        return $kernel->handle(new ServerRequest(
            'GET',
            'http://localhost' . $path,
            ['Accept' => $accept],
            null,
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        ));
    }

    public function testARouteOfAControllerIsAnswered(): void
    {
        $response = $this->send($this->kernel(), '/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertSame('home', (string) $response->getBody());
    }

    public function testARouteOfTheFileIsAnswered(): void
    {
        $response = $this->send($this->kernel(), '/helper');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('not a route', (string) $response->getBody());
    }

    public function testAPathThatNoRouteHasIsAProblem(): void
    {
        $response = $this->send($this->kernel(), '/missing', 'application/json');

        $this->assertSame(404, $response->getStatusCode());
        $problem = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($problem);
        $this->assertSame(404, $problem['status']);
    }

    public function testTheSameKernelAnswersManyRequests(): void
    {
        $kernel = $this->kernel();

        $this->assertSame('home', (string) $this->send($kernel, '/')->getBody());
        $this->assertSame('not a route', (string) $this->send($kernel, '/helper')->getBody());
        $this->assertSame(404, $this->send($kernel, '/missing')->getStatusCode());
    }
}
