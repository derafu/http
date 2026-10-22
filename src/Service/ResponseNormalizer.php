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

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Contract\ResponseInterface;
use Derafu\Http\Contract\ResponseNormalizerInterface;
use Derafu\Http\Enum\ContentType;
use Derafu\Http\Exception\ResponseSerializationException;
use Derafu\Http\Response;
use JsonException;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Stringable;

/**
 * Turns what a handler returns (a controller, the page of an error) into a
 * PSR-7 response.
 *
 *   - A response is kept as it is (a `Content-Type` is added if it has none).
 *   - A value that is `JsonSerializable` is always encoded as JSON, even if it
 *     is `Stringable` too: it says how it is represented.
 *   - With the format JSON, any other value is encoded as JSON (an object that
 *     is only `Stringable` is encoded as its text).
 *   - With any other format, a text, a number, a boolean or an object that can be
 *     a string is sent as text of that format, `null` is an empty body, and an
 *     array or an object that is not a string is sent as JSON, which is the only
 *     way to represent it.
 */
final class ResponseNormalizer implements ResponseNormalizerInterface
{
    /**
     * {@inheritDoc}
     *
     * @throws ResponseSerializationException If the value can not be encoded as
     * JSON and it has no text to send instead.
     */
    public function normalize(
        RequestInterface $request,
        mixed $result,
        ?ContentType $format = null
    ): PsrResponseInterface {
        $format ??= $request->getPreferredContentType();

        // If it is already an answer, just check/add Content-Type.
        if ($result instanceof ResponseInterface) {
            if (!$result->hasHeader('Content-Type')) {
                return $result->withContentType($format);
            }
            return $result;
        }

        // If it is already a PSR-7 response, just return it.
        if ($result instanceof PsrResponseInterface) {
            return $result;
        }

        // For other types, determine format and create response.
        $response = new Response();

        if ($format === ContentType::JSON && $result instanceof Stringable && !$result instanceof JsonSerializable) {
            $result = (string) $result;
        }

        if ($format === ContentType::JSON) {
            try {
                return $response->asJson($result);
            } catch (JsonException $e) {
                if (is_string($result) || $result instanceof Stringable) {
                    return $response->asText((string) $result, ContentType::PLAIN);
                }

                throw new ResponseSerializationException([
                    'Cannot fall back to plain text: response is of type {type}, not string (JSON encoding failed: {reason}).',
                    'type' => get_debug_type($result),
                    'reason' => $e->getMessage(),
                ], previous: $e);
            }
        }

        return $this->asFormat($response, $result, $format);
    }

    /**
     * A value that is not a response, in a format that is not JSON.
     */
    private function asFormat(Response $response, mixed $result, ContentType $format): PsrResponseInterface
    {
        if ($result instanceof JsonSerializable) {
            return $this->asJson($response, $result);
        }

        if (is_string($result)) {
            return $response->asText($result, $format);
        }

        if ($result === null) {
            return $response->asText('', $format);
        }

        if (is_scalar($result) || $result instanceof Stringable) {
            return $response->asText((string) $result, $format);
        }

        // An array or an object has no text: JSON is how it is sent.
        return $this->asJson($response, $result);
    }

    /**
     * A value that is sent as JSON, because it has no text.
     */
    private function asJson(Response $response, mixed $result): PsrResponseInterface
    {
        try {
            return $response->asJson($result);
        } catch (JsonException $e) {
            throw new ResponseSerializationException([
                'Cannot fall back to plain text: response is of type {type}, not string (JSON encoding failed: {reason}).',
                'type' => get_debug_type($result),
                'reason' => $e->getMessage(),
            ], previous: $e);
        }
    }
}
