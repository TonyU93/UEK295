<?php

/**
 * ProductRepository.php – Database access for products.
 *
 * All queries run as prepared statements to prevent SQL injection.
 * Supports create-or-update (upsert), read, delete and list operations
 * as needed by the REST endpoints.
 */

declare(strict_types=1);

namespace App;

use mysqli;

final class ProductRepository
{
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Returns all products (optionally only listed ones).
     *
     * @param bool $onlyListed true = only products WITH a category (id_category IS NOT NULL)
     * @return array<int, array<string, mixed>> list of product rows
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
     * Finds a single product by its ID.
     *
     * @param int $id the product id from the route
     * @return array<string, mixed>|null the product row or null if not found
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

        return $row !== null ? $row : null;
    }

    /**
     * Creates a new product and returns its generated ID.
     *
     * @param int|null $explicitId optional explicit ID for the upsert case
     *                             (PUT /product/{id} on a non-existing product
     *                             must create the product with EXACTLY this ID,
     *                             e.g. 12345678 in the Bruno collection)
     * @param array<string, mixed> $data validated product data
     */
    public function create(array $data, ?int $explicitId = null): int
    {
        if ($explicitId !== null) {
            // Upsert case: insert with the ID from the route
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

        // Regular create: let AUTO_INCREMENT choose the ID
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
     * Updates an existing product.
     *
     * @param int                  $id   the product id to update
     * @param array<string, mixed> $data validated product data
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
     * Deletes a product by ID.
     *
     * @param int $id the product id to delete
     */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM product WHERE id_product = ?');

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $stmt->close();
    }

    /**
     * Checks whether a category ID exists (used to validate id_category on create/update).
     *
     * @param int $id the category id to check
     */
    public function categoryExists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM category WHERE id_category = ?');

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}
