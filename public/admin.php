<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helper.php';
require_once __DIR__ . '/../src/Deal.php';

use LeadsPilot\Auth;
use LeadsPilot\Helper;
use LeadsPilot\Deal;
use LeadsPilot\Database;

$auth = new Auth();
$auth->requireAuth('/public/login.php');

// Strict Admin Authorization Guard
if ($auth->getRole() !== 'admin') {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Access restricted to Agency Administrators only.</p>');
}

$agencyId    = $auth->getAgencyId();
$currentUser = $auth->getUserSession();
$dealModel   = new Deal();
$db          = Database::getInstance();

$message     = '';
$messageType = 'success';

// Handle Admin Actions (Delete Deal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helper::validateCsrfOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_deal') {
        $dealId = (int) ($_POST['deal_id'] ?? 0);
        try {
            if ($dealId > 0 && $dealModel->deleteDeal($agencyId, $dealId)) {
                $message     = 'Deal permanently deleted from pipeline.';
                $messageType = 'success';
            } else {
                $message     = 'Failed to delete deal.';
                $messageType = 'error';
            }
        } catch (\Exception $e) {
            $message     = 'Error deleting deal: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch deals & counts
$deals = $dealModel->getDealsByAgency($agencyId);

$contactsCount  = (int) ($db->fetchOne("SELECT COUNT(*) as c FROM contacts WHERE agency_id = :agency_id", ['agency_id' => $agencyId])['c'] ?? 0);
$companiesCount = (int) ($db->fetchOne("SELECT COUNT(*) as c FROM companies WHERE agency_id = :agency_id", ['agency_id' => $agencyId])['c'] ?? 0);
$usersCount     = (int) ($db->fetchOne("SELECT COUNT(*) as c FROM users WHERE agency_id = :agency_id", ['agency_id' => $agencyId])['c'] ?? 0);

$stages = [
    'lead'        => '📥 New Lead',
    'contacted'   => '📞 Contacted',
    'proposal'    => '📄 Proposal Sent',
    'negotiation' => '🤝 Negotiation',
    'won'         => '🎉 Won',
    'lost'        => '❌ Lost',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel | <?= Helper::e($currentUser['agency_name']) ?> - Leads Pilot</title>
  <style>
    :root {
      --bg-app: #f8f9fa;
      --bg-surface: #ffffff;
      --text-main: #212529;
      --text-muted: #6c757d;
      --border-color: #e9ecef;
      --primary-color: #0d6efd;
      --danger-color: #dc3545;
      --success-color: #198754;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg-app); color: var(--text-main); }

    .app-header { height: 56px; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; }
    .header-brand { font-weight: 700; color: var(--primary-color); font-size: 1.1rem; text-decoration: none; }
    .header-nav { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; }
    .nav-link { color: var(--text-muted); text-decoration: none; font-weight: 600; }
    .nav-link:hover, .nav-link.active { color: var(--primary-color); }

    .main-workspace { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }
    
    /* Admin Metric Cards */
    .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .metric-card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .metric-value { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); }
    .metric-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

    .alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.875rem; }
    .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .alert-error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

    .card-table { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left; }
    .data-table th, .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .data-table th { background: #fafafa; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }

    .btn-danger { background: var(--danger-color); color: #fff; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer; }
    .btn-danger:hover { background: #bb2d3b; }
  </style>
</head>
<body>

  <header class="app-header">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <a href="/public/index.php" class="header-brand">✈️ Leads Pilot</a>
    </div>

    <div class="header-nav">
      <span>🏢 <?= Helper::e($currentUser['agency_name']) ?></span>
      <a href="/public/index.php" class="nav-link">📊 Pipeline</a>
      <a href="/public/contacts.php" class="nav-link">👥 Contacts</a>
      <a href="/public/companies.php" class="nav-link">🏢 Companies</a>
      <a href="/public/admin.php" class="nav-link active">⚙️ Admin Panel</a>
      <a href="/public/logout.php" class="nav-link">Logout</a>
    </div>
  </header>

  <main class="main-workspace">
    <div style="margin-bottom: 1.5rem;">
      <h1>⚙️ Administrator Control Panel</h1>
      <p style="font-size: 0.875rem; color: var(--text-muted);">
        Manage workspace parameters, perform database cleanup, and review system logs.
      </p>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
        <?= Helper::e($message) ?>
      </div>
    <?php endif; ?>

    <!-- Overview Metrics -->
    <div class="metrics-grid">
      <div class="metric-card">
        <div class="metric-value"><?= count($deals) ?></div>
        <div class="metric-label">Active Deals</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= $contactsCount ?></div>
        <div class="metric-label">Contacts</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= $companiesCount ?></div>
        <div class="metric-label">Companies</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= $usersCount ?></div>
        <div class="metric-label">Team Users</div>
      </div>
    </div>

    <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Pipeline Deal Cleanup & Management</h2>

    <div class="card-table">
      <table class="data-table">
        <thead>
          <tr>
            <th>Deal Title</th>
            <th>Value</th>
            <th>Stage</th>
            <th>Created Date</th>
            <th style="text-align: right;">Admin Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($deals)): ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                No active deals in workspace.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($deals as $deal): ?>
              <tr>
                <td style="font-weight: 700;"><?= Helper::e($deal['title']) ?></td>
                <td style="font-weight: 600; color: var(--success-color);">$<?= number_format((float)$deal['value'], 2) ?></td>
                <td><?= Helper::e($stages[$deal['stage']] ?? $deal['stage']) ?></td>
                <td><?= Helper::formatDate($deal['created_at'] ?? null) ?></td>
                <td style="text-align: right;">
                  <form action="/public/admin.php" method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Permanently delete this deal record?');">
                    <?= Helper::csrfInput() ?>
                    <input type="hidden" name="action" value="delete_deal">
                    <input type="hidden" name="deal_id" value="<?= (int) $deal['id'] ?>">
                    <button type="submit" class="btn-danger">🗑️ Permanent Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>

</body>
</html>