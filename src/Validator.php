<?php

/**
 * Validator.php – Validation of incoming JSON bodies.
 *
 * Validates the product and category payloads from the request body.
 * Every rule returns a list of error messages; if the list is empty the
 * payload is valid. Used by the route handlers before touching the database.
 */

declare(strict_types=1);

namespace App;

final class Validator
{
    /**
     * Validates a product payload.
     *
     * Expected structure (like the Bruno collection):
     * {
     *   "active": 1,
     *   "id_category": 1,        (optional, null = product not listed)
     *   "name": "CsBe-Logo",
     *   "image": "https://...",  (optional)
     *   "description": "...",    (optional)
     *   "price": 39999.95,
     *   "stock": 3
     * }
     *
     * @param array<string, mixed> $data parsed JSON body
     * @return string[] list of error messages, empty = valid
     */
    public static function validateProduct(array $data): array
    {
        $errors = [];

        // name: required, non-empty string, max 150 chars (DB limit)
        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            $errors[] = 'name is required and must be a non-empty string';
        } elseif (mb_strlen($data['name']) > 150) {
            $errors[] = 'name must not exceed 150 characters';
        }

        // price: required, numeric, >= 0, max 2 decimals (DB DECIMAL(10,2))
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors[] = 'price is required and must be a number';
        } elseif ((float) $data['price'] < 0) {
            $errors[] = 'price must not be negative';
        }

        // stock: required, integer, >= 0
        if (!isset($data['stock']) || !is_int($data['stock'])) {
            $errors[] = 'stock is required and must be an integer';
        } elseif ($data['stock'] < 0) {
            $errors[] = 'stock must not be negative';
        }

        // active: required, integer 0 or 1
        if (!isset($data['active']) || !in_array($data['active'], [0, 1], true)) {
            $errors[] = 'active is required and must be 0 or 1';
        }

        // id_category: optional; null = not listed; otherwise positive integer
        if (array_key_exists('id_category', $data) && $data['id_category'] !== null) {
            if (!is_int($data['id_category']) || $data['id_category'] < 1) {
                $errors[] = 'id_category must be a positive integer or null';
            }
        }

        // description: optional string
        if (isset($data['description']) && !is_string($data['description'])) {
            $errors[] = 'description must be a string';
        }

        // image: optional string, max 500 chars (DB limit)
        if (isset($data['image'])) {
            if (!is_string($data['image'])) {
                $errors[] = 'image must be a string (URL)';
            } elseif (mb_strlen($data['image']) > 500) {
                $errors[] = 'image must not exceed 500 characters';
            }
        }

        return $errors;
    }

    /**
     * Validates a category payload (used by POST and PATCH).
     *
     * Expected structure: {"active": 1, "name": "Firmen-Logos"}
     * For PATCH, $full = false allows single fields only.
     *
     * @param array<string, mixed> $data parsed JSON body
     * @param bool                 $full true = both fields required (POST), false = at least one (PATCH)
     * @return string[] list of error messages, empty = valid
     */
    public static function validateCategory(array $data, bool $full = true): array
    {
        $errors = [];
        $hasName = array_key_exists('name', $data);
        $hasActive = array_key_exists('active', $data);

        // For a full validation (POST) both fields must be present
        if ($full && (!$hasName || !$hasActive)) {
            $errors[] = 'name and active are required';
        }

        // For PATCH at least one field must be present
        if (!$full && !$hasName && !$hasActive) {
            $errors[] = 'at least one of name or active must be provided';
        }

        // name: non-empty string, max 100 chars (DB limit)
        if ($hasName && (!is_string($data['name']) || trim($data['name']) === '')) {
            $errors[] = 'name must be a non-empty string';
        } elseif ($hasName && mb_strlen((string) $data['name']) > 100) {
            $errors[] = 'name must not exceed 100 characters';
        }

        // active: integer 0 or 1
        if ($hasActive && !in_array($data['active'], [0, 1], true)) {
            $errors[] = 'active must be 0 or 1';
        }

        return $errors;
    }

    /**
     * Validates that a path parameter is a positive integer ID.
     *
     * @param string $value raw route parameter (e.g. "42")
     */
    public static function validateId(string $value): bool
    {
        // ctype_digit rejects negatives, zero, decimals and non-numeric input
        return preg_match('/^\d+$/', $value) === 1 && (int) $value > 0;
    }
}
