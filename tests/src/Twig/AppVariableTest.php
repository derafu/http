<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Twig;

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Factory\RequestFactory;
use Derafu\Http\Request;
use Derafu\Http\Service\Dispatcher;
use Derafu\Http\Service\RequestStack;
use Derafu\Http\Twig\AppExtension;
use Derafu\Http\Twig\AppVariable;
use Derafu\Routing\Contract\RouteMatchInterface;
use Derafu\Routing\ValueObject\Route;
use Derafu\Routing\ValueObject\RouteMatch;
use Derafu\TestsHttp\Fixture\UnusedRenderer;
use Invoker\Invoker;
use Mezzio\Authentication\UserInterface;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The `app` variable of the templates: it is in every template and it reads the
 * request that is being handled, so what a template needs to know (who the user
 * is, the route, the session, the flash messages) is not passed by a controller.
 */
#[CoversClass(AppVariable::class)]
#[CoversClass(AppExtension::class)]
#[UsesClass(RequestStack::class)]
#[UsesClass(Dispatcher::class)]
#[UsesClass(RequestFactory::class)]
#[UsesClass(Request::class)]
final class AppVariableTest extends TestCase
{
    private RequestStack $requests;

    private AppVariable $app;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
        $this->app = new AppVariable($this->requests);
    }

    /**
     * A request with some attributes, as the middlewares leave it.
     *
     * @param array<string, mixed> $attributes
     */
    private function request(array $attributes): RequestInterface
    {
        $request = (new RequestFactory())->createFromPsrRequest(
            new ServerRequest('GET', 'https://example.com/page')
        );
        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }
        $this->assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    /**
     * Gives the stack a request with some attributes, as the dispatcher would.
     *
     * @param array<string, mixed> $attributes
     */
    private function handle(array $attributes): void
    {
        $this->requests->push($this->request($attributes));
    }

    #[Test]
    public function withoutARequestEverythingIsEmpty(): void
    {
        $this->assertNull($this->app->getRequest());
        $this->assertNull($this->app->getRoute());
        $this->assertNull($this->app->getUser());
        $this->assertNull($this->app->getSession());
        $this->assertSame([], $this->app->getFlashes());
    }

    #[Test]
    public function aRequestWithoutAttributesHasNoUserNorSessionNorRoute(): void
    {
        $this->handle([]);

        $this->assertNotNull($this->app->getRequest());
        $this->assertNull($this->app->getRoute());
        $this->assertNull($this->app->getUser());
        $this->assertNull($this->app->getSession());
        $this->assertSame([], $this->app->getFlashes());
    }

    #[Test]
    public function theRequestAtTheTopOfTheStackIsTheOneThatIsSeen(): void
    {
        $this->handle([]);
        $this->assertNull($this->app->getUser());

        // A dispatch inside the other one, with a request that has a user.
        $user = $this->createStub(UserInterface::class);
        $this->handle([UserInterface::class => $user]);
        $this->assertSame($user, $this->app->getUser());

        // When it ends the request below is the current one again.
        $this->requests->pop();
        $this->assertNull($this->app->getUser());
    }

    #[Test]
    public function aHandlerSeesTheUserThatTheMiddlewaresAddedToItsRequest(): void
    {
        $user = $this->createStub(UserInterface::class);
        $dispatcher = new Dispatcher(new Invoker(), new UnusedRenderer(), $this->requests);
        $match = new RouteMatch(new Route('page', '/page', fn (): ?UserInterface => $this->app->getUser()));

        $seen = $dispatcher->dispatch($match, $this->request([UserInterface::class => $user]));

        $this->assertSame($user, $seen);
        $this->assertNull($this->app->getUser());
    }

    #[Test]
    public function anAnonymousUserIsNobody(): void
    {
        $anonymous = new class () implements UserInterface {
            public function getIdentity(): string
            {
                return 'anonymous';
            }

            public function getRoles(): iterable
            {
                return ['anonymous'];
            }

            public function getDetail(string $name, $default = null)
            {
                return $default;
            }

            public function getDetails(): array
            {
                return [];
            }

            public function isAnonymous(): bool
            {
                return true;
            }
        };
        $this->handle([UserInterface::class => $anonymous]);

        $this->assertNull($this->app->getUser());
    }

    #[Test]
    public function theSessionTheRouteAndTheFlashMessagesAreRead(): void
    {
        $session = $this->createStub(SessionInterface::class);
        $route = $this->createStub(RouteMatchInterface::class);
        $flash = $this->createStub(FlashMessagesInterface::class);
        $flash->method('getFlashes')->willReturn(['success' => 'Done']);
        $this->handle([
            SessionMiddleware::SESSION_ATTRIBUTE => $session,
            'derafu.route' => $route,
            FlashMessageMiddleware::FLASH_ATTRIBUTE => $flash,
        ]);

        $this->assertSame($session, $this->app->getSession());
        $this->assertSame($route, $this->app->getRoute());
        // The flash messages can be read more than once.
        $first = $this->app->getFlashes();
        $this->assertSame($first, $this->app->getFlashes());
        $this->assertSame(['success' => 'Done'], $first);
    }

    #[Test]
    public function everyTemplateHasTheVariableApp(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getIdentity')->willReturn('ana');
        $twig = new Environment(new ArrayLoader([
            'menu' => '{% if app.user %}Hello {{ app.user.identity }}{% else %}Login{% endif %}',
        ]));
        $twig->addExtension(new AppExtension($this->app));

        $this->assertSame('Login', $twig->render('menu'));

        $this->handle([UserInterface::class => $user]);
        $this->assertSame('Hello ana', $twig->render('menu'));
    }
}
