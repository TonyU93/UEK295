<?php

declare(strict_types=1);

namespace App\Repository;

use mysqli;

final class UserRepository
{
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Find user by username.
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT id_user, username, password FROM user WHERE username = ?');

        $stmt->bind_param('s', $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        return $row ?: null;
    }
}