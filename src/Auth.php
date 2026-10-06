<?php
declare(strict_types=1);

namespace LeadsPilot;

class Auth
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->ensureSessionStarted();
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public function login(string $email, string $password): bool
    {
        $email = strtolower(trim($email));

        $sql = "SELECT u.id, u.agency_id, u.first_name, u.last_name, u.email, u.password_hash, u.role,
                       a.name AS agency_name
                FROM users u
                INNER JOIN agencies a ON u.agency_id = a.id
                WHERE LOWER(u.email) = :email
                LIMIT 1";

        $user = $this->db->fetchOne($sql, ['email' => $email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['logged_in']   = true;
        $_SESSION['user_id']     = (int) $user['id'];
        $_SESSION['agency_id']   = (int) $user['agency_id'];
        $_SESSION['agency_name'] = $user['agency_name'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['user_name']   = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['email']       = $user['email'];

        session_write_close();
        return true;
    }

    public function isLoggedIn(): bool
    {
        $this->ensureSessionStarted();
        return isset($_SESSION['logged_in'], $_SESSION['agency_id'], $_SESSION['user_id']) 
            && $_SESSION['logged_in'] === true;
    }

    public function requireAuth(string $redirectUrl = '/public/login.php'): void
    {
        if (!$this->isLoggedIn()) {
            header("Location: {$redirectUrl}");
            exit();
        }
    }

    public function getAgencyId(): int
    {
        $this->requireAuth();
        return (int) $_SESSION['agency_id'];
    }

    public function getRole(): string
    {
        $this->requireAuth();
        return $_SESSION['role'] ?? 'sales';
    }

    public function isGuest(): bool
    {
        return $this->getRole() === 'guest';
    }

    public function requireWritePermission(): void
    {
        $this->requireAuth();
        if ($this->isGuest()) {
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>Read-only account.</p>');
        }
    }

    public function getUserSession(): array
    {
        $this->requireAuth();
        return [
            'id'          => $_SESSION['user_id'],
            'agency_id'   => $_SESSION['agency_id'],
            'agency_name' => $_SESSION['agency_name'] ?? 'Workspace',
            'user_name'   => $_SESSION['user_name'] ?? 'User',
            'email'       => $_SESSION['email'] ?? '',
            'role'        => $_SESSION['role'] ?? 'sales',
        ];
    }
}