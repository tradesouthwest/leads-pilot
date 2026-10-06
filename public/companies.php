<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helper.php';
require_once __DIR__ . '/../src/Company.php';

use LeadsPilot\Auth;
use LeadsPilot\Helper;
use LeadsPilot\Company;

$auth = new Auth();

// Enforce login & retrieve agency workspace context
$auth->requireAuth('/public/login.php');
$agencyId = $auth->getAgencyId();
$user     = $auth->getUserSession();

$companyModel = new Company();

$message     = '';
$messageType = 'success';

// Handle Form POST Submissions (Create / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helper::validateCsrfOrDie();

    $action = $_POST['action'] ?? '';

    if ($action === 'create_company') {
        try {
            $companyModel->createCompany($agencyId, [
                'name'    => $_POST['name'] ?? '',
                'website' => $_POST['website'] ?? '',
                'phone'   => $_POST['phone'] ?? '',
                'address' => $_POST['address'] ?? '',
            ]);
            $message     = 'Company account successfully created.';
            $messageType = 'success';
        } catch (\Exception $e) {
            $message     = $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'update_company') {
        $companyId = (int) ($_POST['company_id'] ?? 0);
        try {
            if ($companyId <= 0) {
                throw new \InvalidArgumentException("Invalid company ID.");
            }
            $companyModel->updateCompany($agencyId, $companyId, [
                'name'    => $_POST['name'] ?? '',
                'website' => $_POST['website'] ?? '',
                'phone'   => $_POST['phone'] ?? '',
                'address' => $_POST['address'] ?? '',
            ]);
            $message     = 'Company account updated successfully.';
            $messageType = 'success';
        } catch (\Exception $e) {
            $message     = $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'delete_company') {
        $companyId = (int) ($_POST['company_id'] ?? 0);
        if ($companyId > 0 && $companyModel->deleteCompany($agencyId, $companyId)) {
            $message     = 'Company account deleted successfully.';
            $messageType = 'success';
        } else {
            $message     = 'Failed to delete company account.';
            $messageType = 'error';
        }
    }
}

// Retrieve companies with joined contact & deal metrics
$companies = [];
try {
    $companies = $companyModel->getCompaniesByAgency($agencyId);
} catch (\Exception $e) {
    $message     = 'Database query error: ' . $e->getMessage();
    $messageType = 'error';
}

// Handle Search / Filter Query
$searchQuery = trim($_GET['q'] ?? '');
if (!empty($searchQuery)) {
    $companies = array_filter($companies, function ($comp) use ($searchQuery) {
        $name    = strtolower($comp['name']);
        $website = strtolower($comp['website'] ?? '');
        $q       = strtolower($searchQuery);
        return strpos($name, $q) !== false || strpos($website, $q) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Companies | <?= Helper::e($user['agency_name']) ?> - Leads Pilot</title>
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
      text-decoration: none;
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
    .nav-link:hover, .nav-link.active { color: var(--primary-color); }

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

    .btn-primary {
      background-color: var(--primary-color);
      color: #fff;
      border-color: var(--primary-color);
    }
    .btn-primary:hover { background-color: #0b5ed7; }

    .search-input {
      padding: 0.45rem 0.75rem;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 0.875rem;
      width: 260px;
    }

    .card-table {
      background-color: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 6px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left; }
    .data-table th, .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .data-table th { background: #fafafa; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover { background-color: #f8f9fa; }

    .company-edit-link { color: var(--primary-color); text-decoration: none; font-weight: 700; cursor: pointer; }
    .company-edit-link:hover { text-decoration: underline; }

    .badge-count {
      background-color: #e9ecef;
      padding: 0.15rem 0.5rem;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--text-muted);
    }

    .btn-delete { border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 0.85rem; }
    .btn-delete:hover { color: var(--danger-color); }

    .form-card {
      background-color: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 6px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      display: none;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .form-group label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      margin-bottom: 0.3rem;
      color: var(--text-muted);
    }

    .form-group input, .form-group textarea {
      width: 100%;
      padding: 0.45rem 0.65rem;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 0.875rem;
      font-family: inherit;
    }

    /* Modal Overlay Styling */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal-overlay.active { display: flex; }
    .modal-card { background: var(--bg-surface); border-radius: 8px; width: 100%; max-width: 500px; padding: 1.5rem; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted); }

    @media (max-width: 768px) {
      .data-table thead { display: none; }
      .data-table, .data-table tr, .data-table td { display: block; width: 100%; }
      .data-table tr { padding: 0.75rem; border-bottom: 1px solid var(--border-color); }
      .data-table td { padding: 0.25rem 0; border: none; }
    }
  </style>
</head>
<body>

  <div class="app-container">
    <header class="app-header">
      <a href="/public/index.php" class="header-brand">
        <span>✈️</span> Leads Pilot
      </a>
      <div class="header-user">
        <span class="badge-agency">🏢 <?= Helper::e($user['agency_name']) ?></span>
        <a href="/public/index.php" class="nav-link">📊 Pipeline</a>
        <a href="/public/contacts.php" class="nav-link">📇 Contacts</a>
        <a href="/public/companies.php" class="nav-link active">🏢 Companies</a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="/public/users.php" class="nav-link">⚙ Users</a>
        <?php endif; ?>
        <?php if ($currentUser['role'] === 'admin'): ?>
        <a href="/public/admin.php" class="nav-link">⚙️ Admin Panel</a>
        <?php endif; ?>
        <a href="/public/logout.php" class="nav-link">Logout</a>
      </div>
    </header>

    <main class="main-workspace">
      <div class="toolbar">
        <div>
          <h1>🏢 Companies Directory</h1>
          <p style="font-size: 0.875rem; color: var(--text-muted);">
            Manage client accounts, target companies, and parent organization details.
          </p>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <form action="/public/companies.php" method="GET" style="display: inline-flex; gap: 0.3rem;">
            <input type="text" name="q" value="<?= Helper::e($searchQuery) ?>" placeholder="Search company, website..." class="search-input">
            <button type="submit" class="btn">Filter</button>
            <?php if (!empty($searchQuery)): ?>
              <a href="/public/companies.php" class="btn">Clear</a>
            <?php endif; ?>
          </form>

          <button type="button" class="btn btn-primary" onclick="toggleForm()">+ Add Company</button>
        </div>
      </div>

      <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
          <?= Helper::e($message) ?>
        </div>
      <?php endif; ?>

      <!-- Collapsible Add Company Form -->
      <div id="companyFormCard" class="form-card">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Create New Company Account</h2>
        <form action="/public/companies.php" method="POST">
          <?= Helper::csrfInput() ?>
          <input type="hidden" name="action" value="create_company">

          <div class="form-grid">
            <div class="form-group">
              <label>Company Name *</label>
              <input type="text" name="name" required placeholder="Acme Corporation">
            </div>
            <div class="form-group">
              <label>Website URL</label>
              <input type="url" name="website" placeholder="https://acme.com">
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" name="phone" placeholder="(555) 000-0000">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
              <label>Address / Location</label>
              <textarea name="address" rows="2" placeholder="123 Corporate Blvd, Suite 400..."></textarea>
            </div>
          </div>

          <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button type="button" class="btn" onclick="toggleForm()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Company</button>
          </div>
        </form>
      </div>

      <!-- Companies Data Table -->
      <div class="card-table">
        <?php if (empty($companies)): ?>
          <p style="padding: 2rem; color: var(--text-muted); text-align: center; font-size: 0.9rem;">
            <?= !empty($searchQuery) ? 'No companies match your search criteria.' : 'No company accounts found. Click "+ Add Company" to get started.' ?>
          </p>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Company Name (Click to Edit)</th>
                <th>Website</th>
                <th>Phone</th>
                <th>Contacts</th>
                <th>Active Deals</th>
                <th>Created Date</th>
                <th style="text-align: right;">Options</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($companies as $comp): ?>
                <tr>
                  <td>
                    <a class="company-edit-link" onclick="openEditModal(<?= htmlspecialchars(json_encode($comp), ENT_QUOTES, 'UTF-8') ?>)">
                      ✏️ <?= Helper::e($comp['name']) ?>
                    </a>
                  </td>
                  <td>
                    <?php if (!empty($comp['website'])): ?>
                      <a href="<?= Helper::e($comp['website']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); text-decoration: none;">
                        <?= Helper::e(preg_replace('/^https?:\/\//', '', $comp['website'])) ?>
                      </a>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td><?= Helper::e($comp['phone'] ?? '—') ?></td>
                  <td><span class="badge-count"><?= (int) ($comp['contact_count'] ?? 0) ?> contacts</span></td>
                  <td><span class="badge-count"><?= (int) ($comp['deal_count'] ?? 0) ?> deals</span></td>
                  <td><?= Helper::formatDate($comp['created_at'] ?? null) ?></td>
                  <td style="text-align: right;">
                    <form action="/public/companies.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this company? Assigned contacts will be unlinked.');">
                      <?= Helper::csrfInput() ?>
                      <input type="hidden" name="action" value="delete_company">
                      <input type="hidden" name="company_id" value="<?= $comp['id'] ?>">
                      <button type="submit" class="btn-delete" title="Delete Company">🗑️</button>
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

  <!-- Edit Company Modal -->
  <div class="modal-overlay" id="editCompanyModal">
    <div class="modal-card">
      <div class="modal-header">
        <h2 style="font-size: 1.1rem; font-weight: 700;">Edit Company Account</h2>
        <button class="modal-close" onclick="closeEditModal()">&times;</button>
      </div>

      <form action="/public/companies.php" method="POST">
        <?= Helper::csrfInput() ?>
        <input type="hidden" name="action" value="update_company">
        <input type="hidden" name="company_id" id="edit_company_id">

        <div class="form-group">
          <label for="edit_name">Company Name *</label>
          <input type="text" id="edit_name" name="name" required>
        </div>

        <div class="form-group">
          <label for="edit_website">Website URL</label>
          <input type="url" id="edit_website" name="website">
        </div>

        <div class="form-group">
          <label for="edit_phone">Phone Number</label>
          <input type="text" id="edit_phone" name="phone">
        </div>

        <div class="form-group">
          <label for="edit_address">Address / Location</label>
          <textarea id="edit_address" name="address" rows="2"></textarea>
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Company</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function toggleForm() {
      const card = document.getElementById('companyFormCard');
      card.style.display = (card.style.display === 'block') ? 'none' : 'block';
    }

    function openEditModal(data) {
      document.getElementById('edit_company_id').value = data.id || '';
      document.getElementById('edit_name').value       = data.name || '';
      document.getElementById('edit_website').value    = data.website || '';
      document.getElementById('edit_phone').value      = data.phone || '';
      document.getElementById('edit_address').value    = data.address || '';

      document.getElementById('editCompanyModal').classList.add('active');
    }

    function closeEditModal() {
      document.getElementById('editCompanyModal').classList.remove('active');
    }
  </script>

</body>
</html>