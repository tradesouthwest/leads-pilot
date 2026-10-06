<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helper.php';

use LeadsPilot\Auth;
use LeadsPilot\Helper;

$auth = new Auth();
$errorMessage = '';

// If user is already logged in, redirect directly to the main workspace
if ($auth->isLoggedIn()) {
    header('Location: /index.php');
    exit();
}

// Handle Form POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    Helper::validateCsrfOrDie();

    // 2. Sanitize and Extract Inputs
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 3. Validate Inputs & Authenticate
    if (empty($email) || empty($password)) {
        $errorMessage = 'Please enter both your email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        if ($auth->login($email, $password)) {
            // Redirect to pipeline view upon successful login
            header('Location: /public/index.php');
            exit();
        } else {
            $errorMessage = 'Invalid email address or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Leads Pilot</title>
  <style>
    :root {
      --bg-app: #f8f9fa;
      --bg-surface: #ffffff;
      --text-main: #212529;
      --text-muted: #6c757d;
      --border-color: #e9ecef;
      --primary-color: #0d6efd;
      --primary-hover: #0b5ed7;
      --danger-color: #dc3545;
      --danger-bg: #f8d7da;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background-color: var(--bg-app);
      color: var(--text-main);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1.5rem;
      background: url(../imgs/grandtetonnatprk.jpg);
    }

    .login-card {
      background-color: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      width: 100%;
      max-width: 400px;
      padding: 2.5rem 2rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .login-brand {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-brand h1 {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--primary-color);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .login-brand p {
      font-size: 0.875rem;
      color: var(--text-muted);
      margin-top: 0.25rem;
    }

    .alert-error {
      background-color: var(--danger-bg);
      color: var(--danger-color);
      padding: 0.75rem 1rem;
      border-radius: 4px;
      font-size: 0.875rem;
      margin-bottom: 1.5rem;
      border: 1px solid #f5c2c7;
    }

    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-group label {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .form-group input {
      width: 100%;
      padding: 0.6rem 0.8rem;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 0.95rem;
      transition: border-color 0.15s ease;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    }

    .btn-submit {
      width: 100%;
      padding: 0.75rem;
      background-color: var(--primary-color);
      color: #ffffff;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.15s ease;
      margin-top: 0.5rem;
    }

    .btn-submit:hover {
      background-color: var(--primary-hover);
    }

    .login-footer {
      text-align: center;
      margin-top: 2rem;
      font-size: 0.8rem;
      color: var(--text-muted);
    }
  </style>
</head>
<body>

  <div class="login-card">
    <div class="login-brand">
      <h1><span>✈️</span> Leads Pilot</h1>
      <p>Authorized Workspace Portal</p>
    </div>

    <?php if (!empty($errorMessage)): ?>
      <div class="alert-error">
        <?= Helper::e($errorMessage) ?>
      </div>
    <?php endif; ?>

    <form action="" method="POST" autocomplete="off">
      <?= Helper::csrfInput() ?>

      <div class="form-group">
        <label for="email">Work Email</label>
        <input type="email" id="email" name="email" value="<?= Helper::e($_POST['email'] ?? '') ?>" required autofocus placeholder="name@company.com">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <button type="submit" class="btn-submit">Sign In to Workspace</button>
    </form>

    <div class="login-footer">
      &copy; <?= date('Y') ?> Leads Pilot. All rights reserved.
    </div>
  </div>
  <section class="instructions">
    <pre>
  For demo user name is: acmesalesa
  And password is:  j4k3m5j7d2f1z0t9
    </pre>
    </section>
</body>
</html>