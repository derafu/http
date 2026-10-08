<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Service;

use Closure;
use Derafu\Http\Contract\DispatcherInterface;
use Derafu\Http\Contract\ProblemDetailInterface;
use Derafu\Http\Contract\ProblemHandlerInterface;
use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Response;
use Derafu\Routing\ValueObject\Route;
use Derafu\Routing\ValueObject\RouteMatch;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Throwable;

/**
 * Handles application errors and generates appropriate error responses.
 *
 * The response follows the format that the client prefers (see
 * `RequestInterface::getPreferredFormat()`): JSON, HTML or Markdown. For HTML
 * the strategies are, in order:
 *
 *   - The error page of the status (`404`, `500`...), if there is one.
 *   - The default error page (`default`), if there is one.
 *   - Markdown text, as last resort: if there is no page, or if the pages fail.
 *     With debug on, it says why the pages failed.
 *
 * The error pages are not routes: a route is a resource with a URL that anyone
 * can ask for, and an error page only makes sense as the answer to an error.
 * They are given to the handler as a map from the status (or `default`) to a
 * handler, of the same kind as the handler of a route: the path of a template
 * (`/path/to/error404.html.twig`) or a `Controller::action`. The problem is
 * passed to the handler in the `context` array, under the `error` key.
 */
class ProblemHandler implements ProblemHandlerInterface
{
    /**
     * The key of the error page that is used when there is none for the status.
     */
    public const DEFAULT_PAGE = 'default';

    /**
     * The error pages: the handlers by status, and the default.
     *
     * @var array<int|string, string|Closure>
     */
    private readonly array $pages;

    /**
     * Creates a new error handler.
     *
     * @param DispatcherInterface $dispatcher For rendering error pages.
     * @param array<int|string, string|Closure> $pages The error pages: the
     * handler of each status (`404`, `500`...) and the `default` one, that is
     * used for the statuses that have none. Without pages the errors in HTML
     * are sent as Markdown.
     * @throws InvalidArgumentException If a key is not a status (from 400 to
     * 599) or `default`, or a handler is not a string that is not empty or a
     * Closure: a configuration that is wrong is not ignored.
     */
    public function __construct(
        private readonly DispatcherInterface $dispatcher,
        array $pages = []
    ) {
        $this->pages = $this->checkPages($pages);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ProblemDetailInterface $error): ResponseInterface
    {
        // Determine response format based on request.
        $format = $error->getRequest()->getPreferredFormat();

        // Render the error response.
        $response = match($format) {
            'json' => $this->renderJsonError($error),
            'html' => $this->renderHtmlError($error),
            'markdown' => $this->renderMarkdownError($error),
            default => $this->renderHtmlError($error), // Fallback to HTML.
        };

        // Add additional headers.
        foreach ($error->getHeaders() as $name => $value) {
            if ($value !== null) {
                $response = $response->withHeader($name, $value);
            }
        }

        // Return the response.
        return $response;
    }

    /**
     * Renders an error response in JSON format.
     *
     * @param ProblemDetailInterface $error
     * @return ResponseInterface
     */
    private function renderJsonError(ProblemDetailInterface $error): ResponseInterface
    {
        return (new Response())
            ->asJson($error)
            ->withHttpStatus($error->getHttpStatus())
        ;
    }

    /**
     * Renders an error response in HTML format.
     *
     * The page of the status and then the default one: if the first fails the
     * second is tried. If none can be rendered it is Markdown.
     *
     * @param ProblemDetailInterface $error
     * @return ResponseInterface
     */
    private function renderHtmlError(ProblemDetailInterface $error): ResponseInterface
    {
        $failures = [];

        foreach ([$error->getStatus(), self::DEFAULT_PAGE] as $key) {
            if (!isset($this->pages[$key])) {
                continue;
            }

            try {
                return $this->renderErrorPage($this->pages[$key], $error);
            } catch (Throwable $e) {
                $failures[(string) $key] = $e;
            }
        }

        return $this->renderMarkdownError($error, $failures);
    }

    /**
     * Renders a markdown text error message.
     *
     * @param ProblemDetailInterface $error
     * @param array<string, Throwable> $failures The error pages that failed,
     * by key. They are told only with debug on.
     * @return ResponseInterface
     */
    private function renderMarkdownError(
        ProblemDetailInterface $error,
        array $failures = []
    ): ResponseInterface {
        $text = $error->__toString();

        if ($error->isDebug() && $failures !== []) {
            $text .= "## Error pages that failed\n\n";
            foreach ($failures as $key => $failure) {
                $text .= "- `{$key}`: " . $failure::class . ': ' . $failure->getMessage() . "\n";
            }
            $text .= "\n";
        }

        return (new Response())
            ->asText($text, ContentType::MARKDOWN)
            ->withHttpStatus($error->getHttpStatus())
        ;
    }

    /**
     * Renders an error page using the dispatcher.
     *
     * The handler is dispatched as the one of a route that is made for the
     * occasion, with the path of the request: it is not a route of the router.
     *
     * @param string|Closure $handler The handler of the page.
     * @param ProblemDetailInterface $error The error to render.
     * @return ResponseInterface
     * @throws Throwable If the page can not be rendered.
     */
    private function renderErrorPage(
        string|Closure $handler,
        ProblemDetailInterface $error
    ): ResponseInterface {
        $request = $error->getRequest();
        $response = $this->dispatcher->dispatch(
            new RouteMatch(new Route('error', $request->getUri()->getPath(), $handler)),
            $request,
            ['error' => $error]
        );

        if (!$response instanceof ResponseInterface) {
            return (new Response())
                ->asHtml((string) $response)
                ->withHttpStatus($error->getHttpStatus())
            ;
        }

        return $response;
    }

    /**
     * Checks the error pages.
     *
     * @param array<int|string, mixed> $pages
     * @return array<int|string, string|Closure>
     */
    private function checkPages(array $pages): array
    {
        $checked = [];
        foreach ($pages as $key => $handler) {
            if (
                $key !== self::DEFAULT_PAGE
                && (!preg_match('/^[1-9][0-9]{2}$/', (string) $key) || $key < 400 || $key > 599)
            ) {
                throw new InvalidArgumentException([
                    'The key of the error page "{key}" is not valid: it must be a status from 400 to 599 or "{default}".',
                    'key' => (string) $key,
                    'default' => self::DEFAULT_PAGE,
                ]);
            }

            if (!$handler instanceof Closure && (!is_string($handler) || trim($handler) === '')) {
                throw new InvalidArgumentException([
                    'The handler of the error page "{key}" is not valid: it must be a text that is not empty or a Closure.',
                    'key' => (string) $key,
                ]);
            }

            // A key of digits is already an integer in a PHP array.
            $checked[$key] = $handler;
        }

        return $checked;
    }
}
