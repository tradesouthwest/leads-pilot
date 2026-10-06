<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

use LeadsPilot\Auth;

$auth = new Auth();

// Terminate session, unbind cookies, and clear session array
$auth->logout();

// Redirect back to login page
header('Location: /login.php');
exit();