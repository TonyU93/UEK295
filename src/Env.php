<?php

/**
 * Env.php minimal loader for .env-data
 *
 * Reads KEY=VALUE lines and makes them available via Env::get(‘KEY’).
 * Deliberately omits parse_ini_file() so that special characters in the JWT secret
 * (“!” or “*”) are not interpreted as INI control characters.
 */

declare(strict_types=1);

namespace App;

final class Env
{
    /** @var array<string, string> Cache of loading elements */
    private static array $values = [];

    /** flag to ensure the file is read only once */
    private static bool $loaded = false;

    /**
     * load the .env-data
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        // FallbackPath: <projektroot>/.env relativ to this file (src/Env.php)
        $path ??= dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            // skips commits an empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            // remove optional quotation marks around the value
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
     * Returns the value of a configuration variable.
     *
     * Order: .env file → environment variable (getenv) → default.
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
