<?php

/**
 * JsonErrorRenderer.php JSON-Renderer for all fails.
 *
 * ensures that, in the event of an error, the API always returns clean JSON with a
 * consistent structure ({“error”: “...”}), never HTML.
 * Is registered in public/index.php as a renderer for Slim's ErrorMiddleware.
 */

declare(strict_types=1);

namespace App;

use Slim\Error\AbstractErrorRenderer;
use Throwable;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_UNESCAPED_SLASHES;

final class JsonErrorRenderer extends AbstractErrorRenderer
{
    /**
     * Renders an exception as a JSON string.
     *
     * @param Throwable $exception            the actual Exception
     * @param bool      $displayErrorDetails  true = gives details back
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        // Consistent error structure for the entire API
        $error = ['error' => $exception->getMessage() ?: $this->defaultErrorTitle];

        // Deliver additional details only in development mode
        if ($displayErrorDetails) {
            $error['details'] = [
                'type' => get_class($exception),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return (string) json_encode($error, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    }
}
