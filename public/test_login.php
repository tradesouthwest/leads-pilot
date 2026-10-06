<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Database.php';

$db = \LeadsPilot\Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE email = 'acmedirector@acme.com'");

if (!$user) {
    echo "<h3 style='color:red;'>User 'acmedirector@acme.com' NOT found in database!</h3>";
} else {
    echo "<h3>User found: ID " . $user['id'] . "</h3>";
    $passCheck = password_verify('j4k3m5j7d2f1z0t9', $user['password_hash']);
    echo "Password Match Test: " . ($passCheck ? "<strong style='color:green;'>SUCCESS</strong>" : "<strong style='color:red;'>FAILED</strong>");
}