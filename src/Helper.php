<?php
declare(strict_types=1);

namespace LeadsPilot;

class Helper
{
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function formatDate(?string $dateStr): string
    {
        if (!$dateStr) return 'N/A';
        return date('M j, Y', strtotime($dateStr));
    }

    public static function csrfInput(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public static function validateCsrfOrDie(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('Invalid CSRF token.');
        }
    }
}