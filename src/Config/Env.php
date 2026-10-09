<?php

declare(strict_types=1);

namespace App\Config;

final class Env
{
    private static array $values = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from file.
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $path ??= dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            // Strip enclosing quotes
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '') {
                self::$values[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get configuration value by key.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $fromEnvironment = getenv($key);
        if ($fromEnvironment !== false) {
            return $fromEnvironment;
        }

        return $default;
    }
}