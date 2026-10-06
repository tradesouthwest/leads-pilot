<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helper.php';
require_once __DIR__ . '/../src/Deal.php';

use LeadsPilot\Auth;
use LeadsPilot\Helper;
use LeadsPilot\Deal;

$auth = new Auth();

// Enforce login AND strict Administrator role access
$auth->requireAdmin();

$agencyId = $auth->getAgencyId();
$user     = $auth->getUserSession();

$dealModel = new Deal();
$message = '';
$messageType = 'success';

// Handle Restore and Permanent Purge POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helper::validateCsrfOrDie();

    $action = $_POST['action'] ?? '';
    $dealId = (int) ($_POST['deal_id'] ?? 0);

    if ($dealId > 0) {
        if ($action === 'restore_deal') {
            if ($dealModel->restoreDeal($agencyId, $dealId)) {
                $message = 'Deal successfully restored to active pipeline.';
                $messageType = 'success';
            } else {
                $message = 'Failed to restore deal or deal not found.';
                $messageType = 'error';
            }
        } elseif ($action === 'purge_deal') {
            if ($dealModel->purgeDeal($agencyId, $dealId)) {
                $message = 'Deal permanently purged from database.';
                $messageType = 'success';
            } else {
                $message = 'Failed to purge deal or deal not found.';
                $messageType = 'error';
            }
        }
    }
}

// Fetch all archived deals for the current agency workspace
$archivedDeals = $dealModel->getArchivedDeals($agencyId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Archive | <?= Helper::e($user['agency_name']) ?> - Leads Pilot</title>
  <style>
    :root {
      --bg-app: #f8f9fa;
      --bg-surface: #ffffff;
      --text-main: #212529;
      --text-muted: #6c757d;
      --border-color: #e9ecef;
      --primary-color: #0d6efd;
      --success-color: #198754;
      --danger-color: #dc3545;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background-color: var(--bg-app);
      color: var(--text-main);
      line-height: 1.5;
    }

    .app-container { min-height: 100vh; display: flex; flex-direction: column; }
    
    .app-header {
      height: 56px;
      background-color: var(--bg-surface);
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
    }

    .header-brand {
      font-weight: 700;
      color: var(--primary-color);
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .header-user {
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: 0.875rem;
    }

    .badge-agency {
      background-color: #e9ecef;
      padding: 0.2rem 0.6rem;
      border-radius: 4px;
      font-weight: 600;
    }

    .nav-link { color: var(--text-muted); text-decoration: none; font-weight: 600; }
    .nav-link:hover { color: var(--primary-color); }

    .main-workspace {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 1.5rem;
      flex: 1;
    }

    .toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .toolbar h1 { font-size: 1.5rem; font-weight: 700; }

    .alert {
      padding: 0.75rem 1rem;
      border-radius: 4px;
      font-size: 0.875rem;
      margin-bottom: 1.5rem;
    }

    .alert-success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .alert-error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

    .btn {
      padding: 0.45rem 0.85rem;
      border-radius: 4px;
      border: 1px solid var(--border-color);
      background-color: var(--bg-surface);
      color: var(--text-main);
      font-weight: 600;
      cursor: pointer;
      font-size: 0.85rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
    }

    .btn:hover { background-color: #f1f3f5; }

    .card-table {
      background-color: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 6px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .archive-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left; }
    .archive-table th, .archive-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); }
    .archive-table th { background: #fafafa; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }
    .archive-table tr:last-child td { border-bottom: none; }
    .archive-table tr:hover { background-color: #f8f9fa; }

    .cell-title { font-weight: 600; color: var(--text-main); }
    .cell-value { font-weight: 700; }

    .btn-restore {
      background-color: #e8f5e9;
      color: var(--success-color);
      border: 1px solid #c8e6c9;
      padding: 0.3rem 0.6rem;
      border-radius: 3px;
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
    }
    .btn-restore:hover { background-color: #c8e6c9; }

    .btn-purge {
      background-color: #ffebee;
      color: var(--danger-color);
      border: 1px solid #ffcdd2;
      padding: 0.3rem 0.6rem;
      border-radius: 3px;
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
    }
    .btn-purge:hover { background-color: #ffcdd2; }

    .badge-stage {
      background-color: #e9ecef;
      padding: 0.15rem 0.45rem;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: capitalize;
    }

    @media (max-width: 768px) {
      .archive-table thead { display: none; }
      .archive-table, .archive-table tr, .archive-table td { display: block; width: 100%; }
      .archive-table tr { padding: 0.75rem; border-bottom: 1px solid var(--border-color); }
      .archive-table td { padding: 0.25rem 0; border: none; }
    }
  </style>
</head>
<body>

  <div class="app-container">
    <!-- Header Navigation -->
    <header class="app-header">
      <div class="header-brand">
        <span>✈️️</span> Leads Pilot
      </div>
      <div class="header-user">
        <span class="badge-agency">🏢 <?= Helper::e($user['agency_name']) ?></span>
        <span>👤 <?= Helper::e($user['first_name'] . ' ' . $user['last_name']) ?> (Admin)</span>
        <a href="/index.php" class="nav-link">📊 Active Pipeline</a>
        <a href="/logout.php" class="nav-link">Logout</a>
      </div>
    </header>

    <!-- Main Workspace -->
    <main class="main-workspace">
      
      <!-- Toolbar & Actions -->
      <div class="toolbar">
        <div>
          <h1>🗄️ Admin Archive Controls</h1>
          <p style="font-size: 0.875rem; color: var(--text-muted);">
            Review, restore, or permanently purge archived sales pipeline entries.
          </p>
        </div>

        <div>
          <a href="/index.php" class="btn">⬅️ Back to Active Pipeline</a>
        </div>
      </div>

      <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
          <?= Helper::e($message) ?>
        </div>
      <?php endif; ?>

      <!-- Archived Deals Table -->
      <div class="card-table">
        <?php if (empty($archivedDeals)): ?>
          <p style="padding: 2rem; color: var(--text-muted); text-align: center; font-size: 0.9rem;">
            No archived deals found in this workspace.
          </p>
        <?php else: ?>
          <table class="archive-table">
            <thead>
              <tr>
                <th>Deal Title</th>
                <th>Company</th>
                <th>Contact</th>
                <th>Value</th>
                <th>Original Stage</th>
                <th>Archived Date</th>
                <th style="text-align: right;">Admin Controls</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($archivedDeals as $deal): ?>
                <tr>
                  <td class="cell-title"><?= Helper::e($deal['title']) ?></td>
                  <td><?= Helper::e($deal['company_name'] ?? 'N/A') ?></td>
                  <td><?= Helper::e($deal['contact_name'] ?? 'N/A') ?></td>
                  <td class="cell-value"><?= Helper::formatCurrency($deal['value']) ?></td>
                  <td>
                    <span class="badge-stage"><?= Helper::e(str_replace('-', ' ', $deal['stage'])) ?></span>
                  </td>
                  <td><?= Helper::formatDate($deal['updated_at'], 'M j, Y g:i A') ?></td>
                  
                  <td style="text-align: right;">
                    <!-- Restore Form -->
                    <form action="/archive.php" method="POST" style="display:inline-block; margin-right: 0.25rem;">
                      <?= Helper::csrfInput() ?>
                      <input type="hidden" name="action" value="restore_deal">
                      <input type="hidden" name="deal_id" value="<?= $deal['id'] ?>">
                      <button type="submit" class="btn-restore" title="Restore to Pipeline">
                        🔄 Restore
                      </button>
                    </form>

                    <!-- Permanent Purge Form -->
                    <form action="/archive.php" method="POST" style="display:inline-block;" onsubmit="return confirm('PERMANENTLY DELETE this deal? This action cannot be undone.');">
                      <?= Helper::csrfInput() ?>
                      <input type="hidden" name="action" value="purge_deal">
                      <input type="hidden" name="deal_id" value="<?= $deal['id'] ?>">
                      <button type="submit" class="btn-purge" title="Permanently Purge">
                        ❌ Purge
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </main>
  </div>

</body>
</html>