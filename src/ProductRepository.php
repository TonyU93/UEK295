<?php

declare(strict_types=1);

namespace App;

use mysqli;

final class ProductRepository
{
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Get all products.
     */
    public function findAll(bool $onlyListed = false): array
    {
        $sql = 'SELECT p.id_product, p.id_category, p.name, p.description, p.image, p.price, p.stock, p.active
                FROM product p';

        if ($onlyListed) {
            $sql .= ' WHERE p.id_category IS NOT NULL';
        }

        $sql .= ' ORDER BY p.id_product';

        $result = $this->db->query($sql);

        return $result !== false ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Find product by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id_product, id_category, name, description, image, price, stock, active
             FROM product WHERE id_product = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Create product.
     */
    public function create(array $data, ?int $explicitId = null): int
    {
        if ($explicitId !== null) {
            $stmt = $this->db->prepare(
                'INSERT INTO product (id_product, id_category, name, description, image, price, stock, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $idCategory  = $data['id_category'] ?? null;
            $description = $data['description'] ?? null;
            $image       = $data['image'] ?? null;

            $stmt->bind_param('issssdii', $explicitId, $idCategory, $data['name'], $description, $image, $data['price'], $data['stock'], $data['active']);
            $stmt->execute();
            $stmt->close();

            return $explicitId;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO product (id_category, name, description, image, price, stock, active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $idCategory  = $data['id_category'] ?? null;
        $description = $data['description'] ?? null;
        $image       = $data['image'] ?? null;

        $stmt->bind_param('ssssdii', $idCategory, $data['name'], $description, $image, $data['price'], $data['stock'], $data['active']);
        $stmt->execute();

        $newId = (int) $this->db->insert_id;
        $stmt->close();

        return $newId;
    }

    /**
     * Update product.
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE product
             SET id_category = ?, name = ?, description = ?, image = ?, price = ?, stock = ?, active = ?
             WHERE id_product = ?'
        );

        $idCategory  = $data['id_category'] ?? null;
        $description = $data['description'] ?? null;
        $image       = $data['image'] ?? null;

        $stmt->bind_param('ssssdiii', $idCategory, $data['name'], $description, $image, $data['price'], $data['stock'], $data['active'], $id);
        $stmt->execute();

        $stmt->close();
    }

    /**
     * Delete product.
     */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM product WHERE id_product = ?');

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $stmt->close();
    }

    /**
     * Check if category exists.
     */
    public function hasCategory(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM category WHERE id_category = ?');

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}