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
use LeadsPilot\Database;

$auth = new Auth();
$auth->requireAuth();

$agencyId    = $auth->getAgencyId();
$currentUser = $auth->getUserSession();
$db          = Database::getInstance();

$message     = '';
$messageType = 'success';

// Handle POST Requests (Create & Update Contact)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireWritePermission();
    Helper::validateCsrfOrDie();

    $action = $_POST['action'] ?? '';

    if ($action === 'create_contact') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $title     = trim($_POST['title'] ?? '');
        $companyId = !empty($_POST['company_id']) ? (int) $_POST['company_id'] : null;

        if (empty($firstName) && empty($lastName)) {
            $message = 'First or last name is required.';
            $messageType = 'error';
        } else {
            try {
                $sql = "INSERT INTO contacts (agency_id, company_id, first_name, last_name, email, phone, title)
                        VALUES (:agency_id, :company_id, :first_name, :last_name, :email, :phone, :title)";
                
                $db->query($sql, [
                    'agency_id'  => $agencyId,
                    'company_id' => $companyId,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'phone'      => $phone,
                    'title'      => $title,
                ]);

                $message = 'Contact created successfully.';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error creating contact: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update_contact') {
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $title     = trim($_POST['title'] ?? '');
        $companyId = !empty($_POST['company_id']) ? (int) $_POST['company_id'] : null;

        if ($contactId <= 0) {
            $message = 'Invalid contact ID.';
            $messageType = 'error';
        } elseif (empty($firstName) && empty($lastName)) {
            $message = 'First or last name is required.';
            $messageType = 'error';
        } else {
            try {
                $sql = "UPDATE contacts 
                        SET company_id = :company_id, 
                            first_name = :first_name, 
                            last_name  = :last_name, 
                            email      = :email, 
                            phone      = :phone, 
                            title      = :title
                        WHERE id = :id AND agency_id = :agency_id";

                $db->query($sql, [
                    'company_id' => $companyId,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'phone'      => $phone,
                    'title'      => $title,
                    'id'         => $contactId,
                    'agency_id'  => $agencyId,
                ]);

                $message = 'Contact updated successfully.';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error updating contact: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Fetch agency-scoped companies for selection dropdowns
$companies = [];
try {
    $companies = $db->fetchAll(
        "SELECT id, name FROM companies WHERE agency_id = :agency_id ORDER BY name ASC",
        ['agency_id' => $agencyId]
    );
} catch (\Exception $e) {
    // Companies table check safety
}

// Fetch agency-scoped contacts with Company JOIN
$contacts = [];
try {
    $contacts = $db->fetchAll(
        "SELECT c.id, c.company_id, c.first_name, c.last_name, c.email, c.phone, c.title, c.created_at,
                comp.name AS company_name
         FROM contacts c
         LEFT JOIN companies comp ON c.company_id = comp.id
         WHERE c.agency_id = :agency_id 
         ORDER BY c.created_at DESC",
        ['agency_id' => $agencyId]
    );
} catch (\Exception $e) {
    $message = 'Database query error: ' . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contacts | <?= Helper::e($currentUser['agency_name']) ?> - Leads Pilot</title>
  <style>
    :root {
      --bg-app: #f8f9fa;
      --bg-surface: #ffffff;
      --text-main: #212529;
      --text-muted: #6c757d;
      --border-color: #e9ecef;
      --primary-color: #0d6efd;
      --primary-hover: #0b5ed7;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg-app); color: var(--text-main); }

    .app-header { height: 56px; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; }
    .header-brand { font-weight: 700; color: var(--primary-color); font-size: 1.1rem; text-decoration: none; }
    .header-nav { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; }
    .nav-link { color: var(--text-muted); text-decoration: none; font-weight: 600; }
    .nav-link:hover, .nav-link.active { color: var(--primary-color); }

    .main-workspace { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }

    .btn { padding: 0.45rem 0.85rem; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-surface); font-weight: 600; cursor: pointer; font-size: 0.85rem; }
    .btn-primary { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
    .btn-primary:hover { background: var(--primary-hover); }

    .alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.875rem; }
    .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .alert-error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

    .card-table { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left; }
    .data-table th, .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .data-table th { background: #fafafa; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }

    .contact-edit-link { color: var(--primary-color); text-decoration: none; font-weight: 700; cursor: pointer; }
    .contact-edit-link:hover { text-decoration: underline; }

    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal-overlay.active { display: flex; }
    .modal-card { background: var(--bg-surface); border-radius: 8px; width: 100%; max-width: 500px; padding: 1.5rem; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted); }

    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem; color: var(--text-muted); }
    .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.875rem; }
  </style>
</head>
<body>

  <header class="app-header">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <a href="/public/index.php" class="header-brand">✈️ Leads Pilot</a>
      <a href="/public/index.php" class="nav-link" style="font-size: 0.8rem;">📋 Pipeline Table</a>
    </div>

    <div class="header-nav">
      <span>🏢 <?= Helper::e($currentUser['agency_name']) ?></span>
      <a href="/public/contacts.php" class="nav-link active">👥 Contacts</a>
      <a href="/public/companies.php" class="nav-link">🏢 Companies</a>
      <?php if ($currentUser['role'] === 'admin'): ?>
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
        <h1>Contact Directory</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">
          Total Contacts: <strong><?= count($contacts) ?></strong>
        </p>
      </div>

      <?php if (!$auth->isGuest()): ?>
        <button class="btn btn-primary" onclick="openCreateModal()">+ Add New Contact</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
        <?= Helper::e($message) ?>
      </div>
    <?php endif; ?>

    <div class="card-table">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name (Click to Edit)</th>
            <th>Company</th>
            <th>Job Title</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Added Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($contacts)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                No contacts found. Click <strong>+ Add New Contact</strong> to create one.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($contacts as $c): ?>
              <tr>
                <td>
                  <?php if (!$auth->isGuest()): ?>
                    <a class="contact-edit-link" 
                       onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)">
                      ✏️ <?= Helper::e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?>
                    </a>
                  <?php else: ?>
                    <strong><?= Helper::e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></strong>
                  <?php endif; ?>
                </td>
                <td><?= Helper::e($c['company_name'] ?? '—') ?></td>
                <td><?= Helper::e($c['title'] ?? '—') ?></td>
                <td>
                  <?php if (!empty($c['email'])): ?>
                    <a href="mailto:<?= Helper::e($c['email']) ?>" style="color: var(--primary-color); text-decoration: none;">
                      <?= Helper::e($c['email']) ?>
                    </a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td><?= Helper::e($c['phone'] ?? '—') ?></td>
                <td><?= Helper::formatDate($c['created_at'] ?? null) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- Add Contact Modal -->
  <?php if (!$auth->isGuest()): ?>
  <div class="modal-overlay" id="contactModal">
    <div class="modal-card">
      <div class="modal-header">
        <h2 style="font-size: 1.1rem; font-weight: 700;">Add New Contact</h2>
        <button class="modal-close" onclick="closeModal('contactModal')">&times;</button>
      </div>

      <form action="/public/contacts.php" method="POST">
        <?= Helper::csrfInput() ?>
        <input type="hidden" name="action" value="create_contact">

        <div style="display: flex; gap: 0.5rem;">
          <div class="form-group" style="flex: 1;">
            <label for="first_name">First Name *</label>
            <input type="text" id="first_name" name="first_name" required placeholder="Johnny">
          </div>
          <div class="form-group" style="flex: 1;">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" placeholder="Cash">
          </div>
        </div>

        <div class="form-group">
          <label for="company_id">Company Organization</label>
          <select id="company_id" name="company_id">
            <option value="">-- No Company --</option>
            <?php foreach ($companies as $comp): ?>
              <option value="<?= $comp['id'] ?>"><?= Helper::e($comp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="title">Job Title / Position</label>
          <input type="text" id="title" name="title" placeholder="e.g. Purchasing Manager">
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="johnny@example.com">
        </div>

        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input type="text" id="phone" name="phone" placeholder="(520) 555-1234">
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button type="button" class="btn" onclick="closeModal('contactModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Contact</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Contact Modal -->
  <div class="modal-overlay" id="editContactModal">
    <div class="modal-card">
      <div class="modal-header">
        <h2 style="font-size: 1.1rem; font-weight: 700;">Edit Contact</h2>
        <button class="modal-close" onclick="closeModal('editContactModal')">&times;</button>
      </div>

      <form action="/public/contacts.php" method="POST">
        <?= Helper::csrfInput() ?>
        <input type="hidden" name="action" value="update_contact">
        <input type="hidden" name="contact_id" id="edit_contact_id">

        <div style="display: flex; gap: 0.5rem;">
          <div class="form-group" style="flex: 1;">
            <label for="edit_first_name">First Name *</label>
            <input type="text" id="edit_first_name" name="first_name" required>
          </div>
          <div class="form-group" style="flex: 1;">
            <label for="edit_last_name">Last Name</label>
            <input type="text" id="edit_last_name" name="last_name">
          </div>
        </div>

        <div class="form-group">
          <label for="edit_company_id">Company Organization</label>
          <select id="edit_company_id" name="company_id">
            <option value="">-- No Company --</option>
            <?php foreach ($companies as $comp): ?>
              <option value="<?= $comp['id'] ?>"><?= Helper::e($comp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_title">Job Title / Position</label>
          <input type="text" id="edit_title" name="title">
        </div>

        <div class="form-group">
          <label for="edit_email">Email Address</label>
          <input type="email" id="edit_email" name="email">
        </div>

        <div class="form-group">
          <label for="edit_phone">Phone Number</label>
          <input type="text" id="edit_phone" name="phone">
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button type="button" class="btn" onclick="closeModal('editContactModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Contact</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openCreateModal() { document.getElementById('contactModal').classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    function openEditModal(data) {
      document.getElementById('edit_contact_id').value = data.id || '';
      document.getElementById('edit_first_name').value = data.first_name || '';
      document.getElementById('edit_last_name').value  = data.last_name || '';
      document.getElementById('edit_title').value      = data.title || '';
      document.getElementById('edit_email').value      = data.email || '';
      document.getElementById('edit_phone').value      = data.phone || '';
      document.getElementById('edit_company_id').value = data.company_id || '';

      document.getElementById('editContactModal').classList.add('active');
    }
  </script>
  <?php endif; ?>

</body>
</html>