<?php
declare(strict_types=1);

namespace LeadsPilot;

use RuntimeException;

class User
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get all users belonging to a specific agency workspace.
     */
    public function getUsersByAgency(int $agencyId): array
    {
        $sql = "SELECT id, agency_id, first_name, last_name, email, role, created_at, updated_at
                FROM users
                WHERE agency_id = :agency_id
                ORDER BY role ASC, last_name ASC, first_name ASC";

        return $this->db->fetchAll($sql, ['agency_id' => $agencyId]);
    }

    /**
     * Fetch a single user by ID scoped to agency workspace.
     */
    public function getUserById(int $agencyId, int $userId): ?array
    {
        $sql = "SELECT id, agency_id, first_name, last_name, email, role, created_at
                FROM users
                WHERE id = :id AND agency_id = :agency_id
                LIMIT 1";

        return $this->db->fetchOne($sql, [
            'id'        => $userId,
            'agency_id' => $agencyId,
        ]);
    }

    /**
     * Create a new user account under the active agency workspace.
     */
    public function createUser(int $agencyId, array $data): int
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $email     = strtolower(trim($data['email'] ?? ''));
        $password  = $data['password'] ?? '';
        $role      = $data['role'] ?? 'sales';

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            throw new RuntimeException("All fields (first name, last name, email, password) are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Invalid email address format.");
        }

        if (strlen($password) < 8) {
            throw new RuntimeException("Password must be at least 8 characters long.");
        }

        if (!in_array($role, ['admin', 'sales', 'guest'], true)) {
            throw new RuntimeException("Invalid user role specified.");
        }

        // Check if email already exists
        $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = :email LIMIT 1", ['email' => $email]);
        if ($existing) {
            throw new RuntimeException("A user with this email address already exists.");
        }

        $sql = "INSERT INTO users (agency_id, first_name, last_name, email, password_hash, role)
                VALUES (:agency_id, :first_name, :last_name, :email, :password_hash, :role)";

        $params = [
            'agency_id'     => $agencyId,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
        ];

        $this->db->query($sql, $params);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Delete a user account scoped to the agency workspace.
     */
    public function deleteUser(int $agencyId, int $userId, int $currentUserId): bool
    {
        if ($userId === $currentUserId) {
            throw new RuntimeException("You cannot delete your own active user account.");
        }

        $sql = "DELETE FROM users WHERE id = :id AND agency_id = :agency_id";
        $stmt = $this->db->query($sql, [
            'id'        => $userId,
            'agency_id' => $agencyId,
        ]);

        return $stmt->rowCount() > 0;
    }
}