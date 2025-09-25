<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Abstract;

use Derafu\Http\Contract\ControllerInterface;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Abstract controller class.
 */
abstract class AbstractController implements ControllerInterface
{
    /**
     * Creates a new abstract controller.
     *
     * @param RendererInterface $renderer The renderer.
     */
    public function __construct(
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Renders a template with the app variable.
     *
     * @param string $template The template.
     * @param array $data The data.
     * @param RequestInterface|null $request The request.
     * @return string The rendered template.
     */
    protected function _render(
        string $template,
        array $data = [],
        ?RequestInterface $request = null
    ): string {
        if ($request !== null) {
            $data['app'] = $this->_createAppVariable($request);
        }

        return $this->renderer->render($template, $data);
    }

    /**
     * Creates a new app variable.
     *
     * @param RequestInterface $request The request.
     * @return object The app variable.
     */
    protected function _createAppVariable(RequestInterface $request): object
    {
        return (object) [
            'request' => $request,
            'route' => $request->route(),
            'user' => $request->user(),
            'session' => $request->session(),
            'flashes' => $request->flash()->getFlashes(),
        ];
    }
}
