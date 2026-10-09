<?php

declare(strict_types=1);

namespace App;

use Slim\Error\AbstractErrorRenderer;
use Throwable;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_UNESCAPED_SLASHES;

final class JsonErrorRenderer extends AbstractErrorRenderer
{
    /**
     * Render exception as JSON.
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $error = ['error' => $exception->getMessage() ?: $this->defaultErrorTitle];

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