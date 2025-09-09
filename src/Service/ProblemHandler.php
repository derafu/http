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

use Derafu\Http\Contract\DispatcherInterface;
use Derafu\Http\Contract\ProblemDetailInterface;
use Derafu\Http\Contract\ProblemHandlerInterface;
use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Response;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Exception\RouteNotFoundException;
use Throwable;

/**
 * Handles application errors and generates appropriate error responses.
 *
 * This handler provides multiple strategies for error responses:
 *
 *   - Custom error pages using routes (error404, error500, etc.).
 *   - Generic error page fallback (default to: error).
 *   - Markdown text error messages as last resort.
 */
class ProblemHandler implements ProblemHandlerInterface
{
    /**
     * Default route name for error pages.
     *
     * @var string
     */
    private const DEFAULT_ERROR_ROUTE = 'error';

    /**
     * Creates a new error handler.
     *
     * @param RouterInterface $router For resolving error page routes.
     * @param DispatcherInterface $dispatcher For rendering error pages.
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly DispatcherInterface $dispatcher
    ) {
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
     * @param ProblemDetailInterface $error
     * @return ResponseInterface
     */
    private function renderHtmlError(ProblemDetailInterface $error): ResponseInterface
    {
        try {
            // Try specific error page.
            return $this->renderErrorPage(
                'error' . $error->getStatus(),
                $error
            );
        } catch (RouteNotFoundException) {
            try {
                // Fallback to generic error page.
                return $this->renderErrorPage(self::DEFAULT_ERROR_ROUTE, $error);
            } catch (Throwable) {
                // Last resort: markdown text.
                return $this->renderMarkdownError($error);
            }
        }
    }

    /**
     * Renders a markdown text error message.
     *
     * @param ProblemDetailInterface $error
     * @return ResponseInterface
     */
    private function renderMarkdownError(ProblemDetailInterface $error): ResponseInterface
    {
        return (new Response())
            ->asText($error->__toString(), ContentType::MARKDOWN)
            ->withHttpStatus($error->getHttpStatus())
        ;
    }

    /**
     * Renders an error page using the dispatcher.
     *
     * @param string $route The error route to render.
     * @param ProblemDetailInterface $error The error to render.
     * @return ResponseInterface
     * @throws RouteNotFoundException If the error route doesn't exist.
     */
    private function renderErrorPage(
        string $route,
        ProblemDetailInterface $error
    ): ResponseInterface {
        $route = $this->router->match($route);
        $response = $this->dispatcher->dispatch(
            $route,
            $error->getRequest(),
            ['error' => $error]
        );

        if (!$response instanceof ResponseInterface) {
            return (new Response())
                ->asHtml((string)$response)
                ->withHttpStatus($error->getHttpStatus())
            ;
        }

        return $response;
    }
}
