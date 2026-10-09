<?php

declare(strict_types=1);

namespace App\Repository;

use mysqli;

final class CategoryRepository
{
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Get all categories.
     */
    public function findAll(): array
    {
        $result = $this->db->query(
            'SELECT id_category, name, active FROM category ORDER BY id_category'
        );

        return $result !== false ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Find category by ID.
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

        return $row ?: null;
    }

    /**
     * Create category.
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
     * Update category.
     */
    public function update(int $id, array $data, array $current): void
    {
        $name   = isset($data['name'])   ? $data['name']   : $current['name'];
        $active = isset($data['active']) ? $data['active'] : (int) $current['active'];

        $stmt = $this->db->prepare('UPDATE category SET name = ?, active = ? WHERE id_category = ?');

        $stmt->bind_param('sii', $name, $active, $id);
        $stmt->execute();

        $stmt->close();
    }

    /**
     * Delete category.
     */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM category WHERE id_category = ?');

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $stmt->close();
    }
}