<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Database.php';

$db = \LeadsPilot\Database::getInstance();

$newPassword = 'j4k3m5j7d2f1z0t9';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password_hash = :hash WHERE email IN ('acmedirector@acme.com', 'acmesalesa@acme.com')";
$db->query($sql, ['hash' => $hash]);

echo "<h2 style='color:green;'>Passwords successfully reset!</h2>";
echo "<p>Test Hash generated: " . htmlspecialchars($hash) . "</p>";