<?php

/**
 * CategoryRepository.php – Database access for categories.
 *
 * All queries run as prepared statements to prevent SQL injection.
 * Supports create, update, read, delete and list operations
 * as needed by the REST endpoints.
 */

declare(strict_types=1);

namespace App;

use mysqli;

final class CategoryRepository
{
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Returns all categories ordered by their ID.
     *
     * @return array<int, array<string, mixed>> list of category rows
     */
    public function findAll(): array
    {
        $result = $this->db->query(
            'SELECT id_category, name, active FROM category ORDER BY id_category'
        );

        return $result !== false ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Finds a single category by its ID.
     *
     * @param int $id the category id from the route
     * @return array<string, mixed>|null the category row or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id_category, name, active FROM category WHERE id_category = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row !== null ? $row : null;
    }

    /**
     * Creates a new category and returns its generated ID.
     *
     * @param array<string, mixed> $data validated category data (name, active)
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO category (name, active) VALUES (?, ?)'
        );

        $stmt->bind_param('si', $data['name'], $data['active']);
        $stmt->execute();

        $newId = (int) $this->db->insert_id;
        $stmt->close();

        return $newId;
    }

    /**
     * Updates an existing category. Only the provided fields are changed
     * missing fields keep their current value.
     *
     * @param int                  $id      the category id to update
     * @param array<string, mixed> $data    subset of name/active
     * @param array<string, mixed> $current current row values for fallback
     */
    public function update(int $id, array $data, array $current): void
    {
        // PATCH semantics: fall back to the stored value for missing fields
        $name   = isset($data['name'])   ? $data['name']   : $current['name'];
        $active = isset($data['active']) ? $data['active'] : (int) $current['active'];

        $stmt = $this->db->prepare('UPDATE category SET name = ?, active = ? WHERE id_category = ?');

        $stmt->bind_param('sii', $name, $active, $id);
        $stmt->execute();

        $stmt->close();
    }

    /**
     * Deletes a category by ID.
     *
     * Products pointing at this category keep existing afterwards –
     * the foreign key moves them to id_category = NULL (ON DELETE SET NULL).
     *
     * @param int $id the category id to delete
     */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM category WHERE id_category = ?');

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $stmt->close();
    }
}
