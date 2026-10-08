<?php

/**
 * UserRepository.php – Database access for API users.
 *
 * All queries run as prepared statements to prevent SQL injection.
 */

declare(strict_types=1);

namespace App;

use mysqli;

final class UserRepository
{
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Finds a user by its username.
     *
     * @param string $username the username from the login request
     * @return array<string, mixed>|null the row (id_user, username, password) or null if not found
     */
    public function findByUsername(string $username): ?array
    {
        // Prepared statement: the username is bound as parameter, never concatenated
        $stmt = $this->db->prepare('SELECT id_user, username, password FROM user WHERE username = ?');

        $stmt->bind_param('s', $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        return $row !== null ? $row : null;
    }
}
