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
$auth->requireAuth();

$agencyId    = $auth->getAgencyId();
$currentUser = $auth->getUserSession();
$dealModel   = new Deal();
$db          = Database::getInstance();

$message     = '';
$messageType = 'success';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireWritePermission();
    Helper::validateCsrfOrDie();

    $action = $_POST['action'] ?? '';

    if ($action === 'create_deal') {
        try {
            $dealModel->createDeal($agencyId, [
                'title'      => $_POST['title'] ?? '',
                'value'      => (float) ($_POST['value'] ?? 0.0),
                'stage'      => $_POST['stage'] ?? 'lead',
                'notes'      => $_POST['notes'] ?? '',
                'contact_id' => !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null,
                'company_id' => !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null,
            ]);
            $message     = 'Lead created successfully.';
            $messageType = 'success';
        } catch (\Exception $e) {
            $message     = $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'update_stage') {
        $dealId   = (int) ($_POST['deal_id'] ?? 0);
        $newStage = $_POST['stage'] ?? 'lead';
        try {
            $dealModel->updateStage($agencyId, $dealId, $newStage);
            $message     = 'Deal stage updated successfully.';
            $messageType = 'success';
        } catch (\Exception $e) {
            $message     = 'Error updating stage: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'update_deal_details') {
        $dealId    = (int) ($_POST['deal_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $value     = (float) ($_POST['value'] ?? 0.0);
        $stage     = $_POST['stage'] ?? 'lead';
        $notes     = trim($_POST['notes'] ?? '');
        $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        $companyId = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;

        try {
            $sql = "UPDATE deals 
                    SET title = :title, value = :value, stage = :stage, notes = :notes,
                        contact_id = :contact_id, company_id = :company_id
                    WHERE id = :id AND agency_id = :agency_id";
            
            $db->query($sql, [
                'title'      => $title,
                'value'      => $value,
                'stage'      => $stage,
                'notes'      => $notes,
                'contact_id' => $contactId,
                'company_id' => $companyId,
                'id'         => $dealId,
                'agency_id'  => $agencyId,
            ]);

            $message     = 'Deal details updated successfully.';
            $messageType = 'success';
        } catch (\Exception $e) {
            $message     = 'Error updating deal details: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Check display order toggle
$isReversed = isset($_GET['order']) && $_GET['order'] === 'desc';

// Stage definitions
$stages = [
    'lead'        => ['label' => '📥 New Lead', 'color' => '#6c757d'],
    'contacted'   => ['label' => '📞 Contacted', 'color' => '#0d6efd'],
    'proposal'    => ['label' => '📄 Proposal Sent', 'color' => '#0dcaf0'],
    'negotiation' => ['label' => '🤝 Negotiation', 'color' => '#ffc107'],
    'won'         => ['label' => '🎉 Won', 'color' => '#198754'],
    'lost'        => ['label' => '❌ Lost', 'color' => '#dc3545'],
];

if ($isReversed) {
    $stages = array_reverse($stages, true);
}

// Fetch deals with JOIN on Contacts and Companies
$rawDeals = $db->fetchAll(
    "SELECT d.id, d.title, d.value, d.stage, d.notes, d.created_at, d.contact_id, d.company_id,
            TRIM(CONCAT(IFNULL(c.first_name, ''), ' ', IFNULL(c.last_name, ''))) AS contact_name,
            comp.name AS company_name
     FROM deals d
     LEFT JOIN contacts c ON d.contact_id = c.id
     LEFT JOIN companies comp ON d.company_id = comp.id
     WHERE d.agency_id = :agency_id
     ORDER BY d.created_at DESC",
    ['agency_id' => $agencyId]
);

// Group deals by stage
$groupedDeals = [];
foreach (array_keys($stages) as $stg) {
    $groupedDeals[$stg] = [];
}
foreach ($rawDeals as $d) {
    $stg = $d['stage'] ?? 'lead';
    if (isset($groupedDeals[$stg])) {
        $groupedDeals[$stg][] = $d;
    } else {
        $groupedDeals['lead'][] = $d;
    }
}

// Fetch lists for modal dropdowns
$contacts = $db->fetchAll("SELECT id, first_name, last_name FROM contacts WHERE agency_id = :agency_id ORDER BY first_name ASC", ['agency_id' => $agencyId]);
$companies = $db->fetchAll("SELECT id, name FROM companies WHERE agency_id = :agency_id ORDER BY name ASC", ['agency_id' => $agencyId]);

$totalValue = array_reduce($rawDeals, fn($sum, $d) => $sum + (float)$d['value'], 0.0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pipeline | <?= Helper::e($currentUser['agency_name']) ?> - Leads Pilot</title>
  <style>
    :root {
      --bg-app: #f8f9fa;
      --bg-surface: #ffffff;
      --text-main: #212529;
      --text-muted: #6c757d;
      --border-color: #e9ecef;
      --primary-color: #0d6efd;
      --primary-hover: #0b5ed7;
      --success-color: #198754;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg-app); color: var(--text-main); }

    .app-header { height: 56px; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; }
    .header-brand { font-weight: 700; color: var(--primary-color); font-size: 1.1rem; text-decoration: none; }
    .header-nav { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; }
    .nav-link { color: var(--text-muted); text-decoration: none; font-weight: 600; }
    .nav-link:hover { color: var(--primary-color); }

    .main-workspace { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }

    .btn { padding: 0.45rem 0.85rem; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-surface); font-weight: 600; cursor: pointer; font-size: 0.85rem; text-decoration: none; color: var(--text-main); display: inline-flex; align-items: center; gap: 0.3rem; }
    .btn-primary { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }

    .alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.875rem; }
    .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .alert-error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

    /* Grouped Stage Sections */
    .stage-group { margin-bottom: 2rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .stage-group-header { padding: 0.75rem 1rem; background: #f1f3f5; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .stage-title { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
    .badge-count { background: #e9ecef; padding: 0.15rem 0.5rem; border-radius: 12px; font-size: 0.75rem; color: var(--text-muted); }

    .data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left; }
    .data-table th, .data-table td { padding: 0.65rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .data-table th { background: #fafafa; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }

    .deal-title-link { color: var(--primary-color); text-decoration: none; font-weight: 700; cursor: pointer; }
    .deal-title-link:hover { text-decoration: underline; }

    .stage-select { padding: 0.3rem; font-size: 0.8rem; border: 1px solid var(--border-color); border-radius: 4px; background: #fff; }

    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal-overlay.active { display: flex; }
    .modal-card { background: var(--bg-surface); border-radius: 8px; width: 100%; max-width: 500px; padding: 1.5rem; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted); }

    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem; color: var(--text-muted); }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.875rem; }
  </style>
</head>
<body>

  <header class="app-header">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <a href="/public/index.php" class="header-brand">✈️ Leads Pilot</a>
    </div>

    <div class="header-nav">
      <span>🏢 <?= Helper::e($currentUser['agency_name']) ?></span>
      <a href="/public/contacts.php" class="nav-link">👥 Contacts</a>
      <a href="/public/companies.php" class="nav-link">🏢 Companies</a>
      <?php if ($currentUser['role'] === 'admin'): ?>
        <a href="/public/admin.php" class="nav-link">⚙ Users / Admin</a>
      <?php endif; ?>
      <a href="/public/logout.php" class="nav-link">Logout</a>
    </div>
  </header>

  <main class="main-workspace">
    <div class="toolbar">
      <div>
        <h1>Sales Pipeline Table</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">
          Total Pipeline Value: <strong style="color: var(--success-color);">$<?= number_format($totalValue, 2) ?></strong> (<?= count($rawDeals) ?> deals)
        </p>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <a href="/public/index.php?order=<?= $isReversed ? 'asc' : 'desc' ?>" class="btn">
          🔄 Reverse Display Order <?= $isReversed ? '(Desc)' : '(Asc)' ?>
        </a>

        <?php if (!$auth->isGuest()): ?>
          <button class="btn btn-primary" onclick="openAddModal()">+ Add New Lead</button>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
        <?= Helper::e($message) ?>
      </div>
    <?php endif; ?>

    <!-- GROUPED STAGE SECTIONS -->
    <?php foreach ($stages as $stageKey => $stageMeta): ?>
      <?php 
        $dealsInStage = $groupedDeals[$stageKey] ?? [];
        $stageSubtotal = array_reduce($dealsInStage, fn($s, $d) => $s + (float)$d['value'], 0.0);
      ?>
      <section class="stage-group">
        <div class="stage-group-header">
          <div class="stage-title">
            <span><?= $stageMeta['label'] ?></span>
            <span class="badge-count"><?= count($dealsInStage) ?> Deals</span>
          </div>
          <div style="font-weight: 700; color: var(--success-color);">
            $<?= number_format($stageSubtotal, 2) ?>
          </div>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Lead / Deal Name</th>
              <th>Company</th>
              <th>Contact</th>
              <th>Value ($)</th>
              <th style="text-align: right;">Move Stage</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($dealsInStage)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1rem;">
                  No deals in this stage.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($dealsInStage as $deal): ?>
                <tr>
                  <td>
                    <a class="deal-title-link" onclick="openEditDealModal(<?= htmlspecialchars(json_encode($deal), ENT_QUOTES, 'UTF-8') ?>)">
                      ✏️ <?= Helper::e($deal['title']) ?>
                    </a>
                  </td>
                  <td><?= Helper::e($deal['company_name'] ?? '—') ?></td>
                  <td><?= Helper::e($deal['contact_name'] ?? '—') ?></td>
                  <td style="font-weight: 600; color: var(--success-color);">$<?= number_format((float)$deal['value'], 2) ?></td>
                  <td style="text-align: right;">
                    <?php if (!$auth->isGuest()): ?>
                      <form action="/public/index.php<?= $isReversed ? '?order=desc' : '' ?>" method="POST" style="display: inline-flex; gap: 0.4rem; align-items: center; margin: 0;">
                        <?= Helper::csrfInput() ?>
                        <input type="hidden" name="action" value="update_stage">
                        <input type="hidden" name="deal_id" value="<?= (int) $deal['id'] ?>">

                        <select name="stage" class="stage-select">
                          <?php foreach ($stages as $sk => $sm): ?>
                            <option value="<?= $sk ?>" <?= $sk === $deal['stage'] ? 'selected' : '' ?>>
                              <?= Helper::e($sm['label']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    <?php endforeach; ?>

  </main>

  <!-- Add Lead Modal -->
  <?php if (!$auth->isGuest()): ?>
  <div class="modal-overlay" id="leadModal">
    <div class="modal-card">
      <div class="modal-header">
        <h2 style="font-size: 1.1rem; font-weight: 700;">Add New Lead / Deal</h2>
        <button class="modal-close" onclick="closeModal('leadModal')">&times;</button>
      </div>

      <form action="/public/index.php<?= $isReversed ? '?order=desc' : '' ?>" method="POST">
        <?= Helper::csrfInput() ?>
        <input type="hidden" name="action" value="create_deal">

        <div class="form-group">
          <label for="title">Deal / Lead Title *</label>
          <input type="text" id="title" name="title" required placeholder="e.g. Roof Repair Service">
        </div>

        <div class="form-group">
          <label for="company_id">Associated Company</label>
          <select id="company_id" name="company_id">
            <option value="">-- None --</option>
            <?php foreach ($companies as $comp): ?>
              <option value="<?= $comp['id'] ?>"><?= Helper::e($comp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="contact_id">Associated Contact</label>
          <select id="contact_id" name="contact_id">
            <option value="">-- None --</option>
            <?php foreach ($contacts as $c): ?>
              <option value="<?= $c['id'] ?>"><?= Helper::e(trim($c['first_name'] . ' ' . $c['last_name'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="value">Estimated Value ($)</label>
          <input type="number" id="value" name="value" step="0.01" placeholder="1500.00">
        </div>

        <div class="form-group">
          <label for="stage">Initial Stage</label>
          <select id="stage" name="stage">
            <?php foreach ($stages as $sk => $sm): ?>
              <option value="<?= $sk ?>"><?= Helper::e($sm['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="notes">Notes & Overview</label>
          <textarea id="notes" name="notes" rows="3" placeholder="Inquiry notes..."></textarea>
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button type="button" class="btn" onclick="closeModal('leadModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Lead</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit / View Deal Modal -->
  <div class="modal-overlay" id="editDealModal">
    <div class="modal-card">
      <div class="modal-header">
        <h2 style="font-size: 1.1rem; font-weight: 700;">Edit Deal Details & Notes</h2>
        <button class="modal-close" onclick="closeModal('editDealModal')">&times;</button>
      </div>

      <form action="/public/index.php<?= $isReversed ? '?order=desc' : '' ?>" method="POST">
        <?= Helper::csrfInput() ?>
        <input type="hidden" name="action" value="update_deal_details">
        <input type="hidden" name="deal_id" id="edit_deal_id">

        <div class="form-group">
          <label for="edit_deal_title">Deal / Lead Title *</label>
          <input type="text" id="edit_deal_title" name="title" required>
        </div>

        <div class="form-group">
          <label for="edit_company_id">Associated Company</label>
          <select id="edit_company_id" name="company_id">
            <option value="">-- None --</option>
            <?php foreach ($companies as $comp): ?>
              <option value="<?= $comp['id'] ?>"><?= Helper::e($comp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_contact_id">Associated Contact</label>
          <select id="edit_contact_id" name="contact_id">
            <option value="">-- None --</option>
            <?php foreach ($contacts as $c): ?>
              <option value="<?= $c['id'] ?>"><?= Helper::e(trim($c['first_name'] . ' ' . $c['last_name'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_deal_value">Estimated Value ($)</label>
          <input type="number" id="edit_deal_value" name="value" step="0.01">
        </div>

        <div class="form-group">
          <label for="edit_deal_stage">Current Stage</label>
          <select id="edit_deal_stage" name="stage">
            <?php foreach ($stages as $sk => $sm): ?>
              <option value="<?= $sk ?>"><?= Helper::e($sm['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_deal_notes">Notes & Client Inquiry Details</label>
          <textarea id="edit_deal_notes" name="notes" rows="4"></textarea>
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button type="button" class="btn" onclick="closeModal('editDealModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Lead Details</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openAddModal() { document.getElementById('leadModal').classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    function openEditDealModal(deal) {
      document.getElementById('edit_deal_id').value     = deal.id || '';
      document.getElementById('edit_deal_title').value  = deal.title || '';
      document.getElementById('edit_deal_value').value  = deal.value || '0.00';
      document.getElementById('edit_deal_stage').value  = deal.stage || 'lead';
      document.getElementById('edit_deal_notes').value  = deal.notes || '';
      document.getElementById('edit_company_id').value = deal.company_id || '';
      document.getElementById('edit_contact_id').value = deal.contact_id || '';

      document.getElementById('editDealModal').classList.add('active');
    }
  </script>
  <?php endif; ?>

</body>
</html>