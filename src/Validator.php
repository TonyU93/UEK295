<?php

declare(strict_types=1);

namespace App;

final class Validator
{
    private static array $lastTrimmedData = [];

    /**
     * Validate product payload.
     */
    public static function validateProduct(array $data): array
    {
        $errors = [];

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            $errors[] = 'name is required and must be a non-empty string';
        } elseif (mb_strlen(trim($data['name'])) > 150) {
            $errors[] = 'name must not exceed 150 characters';
        } else {
            $data['name'] = trim($data['name']);
        }

        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors[] = 'price is required and must be a number';
        } elseif ((float) $data['price'] < 0) {
            $errors[] = 'price must not be negative';
        } elseif (preg_match('/^-?\d+(\.\d{1,2})?$/', (string) $data['price']) !== 1) {
            $errors[] = 'price must not have more than 2 decimal places';
        }

        if (!isset($data['stock']) || !is_int($data['stock'])) {
            $errors[] = 'stock is required and must be an integer';
        } elseif ($data['stock'] < 0) {
            $errors[] = 'stock must not be negative';
        }

        if (!isset($data['active']) || !in_array($data['active'], [0, 1], true)) {
            $errors[] = 'active is required and must be 0 or 1';
        }

        if (array_key_exists('id_category', $data) && $data['id_category'] !== null) {
            if (!is_int($data['id_category']) || $data['id_category'] < 1) {
                $errors[] = 'id_category must be a positive integer or null';
            }
        }

        if (isset($data['description']) && !is_string($data['description'])) {
            $errors[] = 'description must be a string';
        } elseif (isset($data['description']) && is_string($data['description'])) {
            $data['description'] = trim($data['description']);
        }

        if (isset($data['image'])) {
            if (!is_string($data['image'])) {
                $errors[] = 'image must be a string (URL)';
            } elseif (mb_strlen(trim($data['image'])) > 500) {
                $errors[] = 'image must not exceed 500 characters';
            } else {
                $data['image'] = trim($data['image']);
            }
        }

        foreach (['name', 'description', 'image'] as $trimmedKey) {
            if (array_key_exists($trimmedKey, $data)) {
                $data[$trimmedKey] = is_string($data[$trimmedKey]) ? $data[$trimmedKey] : null;
            }
        }

        self::$lastTrimmedData = $data;

        return $errors;
    }

    /**
     * Validate category payload.
     */
    public static function validateCategory(array $data, bool $full = true): array
    {
        $errors = [];
        $hasName = array_key_exists('name', $data);
        $hasActive = array_key_exists('active', $data);

        if ($full && (!$hasName || !$hasActive)) {
            $errors[] = 'name and active are required';
        }

        if (!$full && !$hasName && !$hasActive) {
            $errors[] = 'at least one of name or active must be provided';
        }

        if ($hasName && (!is_string($data['name']) || trim($data['name']) === '')) {
            $errors[] = 'name must be a non-empty string';
        } elseif ($hasName && mb_strlen(trim((string) $data['name'])) > 100) {
            $errors[] = 'name must not exceed 100 characters';
        } elseif ($hasName && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if ($hasActive && !in_array($data['active'], [0, 1], true)) {
            $errors[] = 'active must be 0 or 1';
        }

        self::$lastTrimmedData = $data;

        return $errors;
    }

    /**
     * Get trimmed payload from last validation.
     */
    public static function getTrimmedData(): array
    {
        return self::$lastTrimmedData;
    }

    /**
     * Check if value is a valid positive integer ID.
     */
    public static function isValidId(string $value): bool
    {
        return preg_match('/^\d+$/', $value) === 1 && (int) $value > 0;
    }
}