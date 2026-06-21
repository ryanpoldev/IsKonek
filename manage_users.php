<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$is_admin = in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'sup_admin'], true);
if (!$is_admin) {
    header('Location: dashboard.php');
    exit();
}

// UPDATED: Added course/program, year, and section to the selection fields
$users_query = $conn->query("
    SELECT u.id, u.first_name, u.mid_name, u.last_name,
           CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name,
           u.email, u.student_id, u.role, u.section_id, u.created_at,
           s.program, s.year, s.section, s.name AS section_name
    FROM users u
    LEFT JOIN sections s ON u.section_id = s.id
    ORDER BY u.created_at DESC
");
$users = $users_query->fetch_all(MYSQLI_ASSOC);

// --- Managers panel data ---
// Fetch every section with its current manager (prefers the managers table; falls back to sections.manager_id).
$sections_mgr_query = $conn->query("
    SELECT s.id, s.name, s.program, s.year, s.section, s.school_year,
           COALESCE(m.user_id, s.manager_id) AS manager_id,
           CONCAT_WS(' ', mu.first_name, mu.mid_name, mu.last_name) AS manager_name,
           mu.email AS manager_email,
           mu.student_id AS manager_student_id,
           mu.role AS manager_role,
           m.created_at AS assigned_at
    FROM sections s
    LEFT JOIN managers m ON m.section_id = s.id
    LEFT JOIN users mu ON mu.id = COALESCE(m.user_id, s.manager_id)
    ORDER BY s.name
");
$sections_mgr = $sections_mgr_query ? $sections_mgr_query->fetch_all(MYSQLI_ASSOC) : [];

// Eligible managers list (everyone — admins can promote/demote freely).
$eligible_users = [];
$eu_q = $conn->query("
    SELECT u.id,
           CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name,
           u.email, u.student_id, u.role,
           s.name AS section_name
    FROM users u
    LEFT JOIN sections s ON s.id = u.section_id
    ORDER BY (u.role = 'sup_admin') DESC, (u.role = 'admin') DESC, u.first_name
");
$eligible_users = $eu_q ? $eu_q->fetch_all(MYSQLI_ASSOC) : [];

function avatarColor(string $name): string {
    $colors = ['#33b77a','#4a90d9','#d23c3c','#f5a623','#9b59b6','#1abc9c','#e67e22','#2980b9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr(end($parts), 0, 1));
    return $i;
}

$eligible_users_json = json_encode($eligible_users, JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/manage_users.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>
<body>
<div class="app-wrapper">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'includes/navbar.php'; ?>

    <div class="page-body">

      <div class="mb-4">
        <h2 class="hero-greeting mb-1">Manage Users 👥</h2>
        <p class="hero-sub text-muted">View, add, edit, or remove users from the system.</p>
      </div>

      <div class="users-panel">
        <div class="users-panel-header">
          <p class="users-panel-title">All Users (<?= count($users) ?>)</p>
          <button class="btn-new-user" onclick="openAddModal()">
            <i class="bi bi-plus-lg"></i> Add User
          </button>
        </div>

        <?php if (empty($users)): ?>
          <div class="users-empty">
            <i class="bi bi-people"></i>
            <p class="mb-1 fw-medium" style="font-size:15px;">No users yet</p>
          </div>
        <?php else: ?>
          <div class="user-table-header">
            <span>Name</span>
            <span>Email</span>
            <span>Student ID</span>
            <span>Role</span>
            <span>Class / Section</span> <span>Actions</span>
          </div>

          <?php foreach ($users as $u): ?>
            <?php $display_name = !empty(trim($u['full_name'])) ? $u['full_name'] : 'System User'; ?>
            <div class="user-row">
              <div class="user-info">
                <div class="user-avatar" style="background:<?= avatarColor($display_name) ?>;">
                  <?= initials($display_name) ?>
                </div>
                <div>
                  <div class="user-name"><?= htmlspecialchars($display_name) ?></div>
                </div>
              </div>
              <div class="user-email"><?= htmlspecialchars($u['email'] ?? '') ?></div>
              <div class="user-student-id"><?= htmlspecialchars($u['student_id'] ?? '') ?></div>
              <div>
                <?php $role_lower = strtolower($u['role'] ?? 'student'); ?>
                <span class="user-role-badge <?= $role_lower === 'admin' ? 'role-admin' : 'role-student' ?>">
                  <?= htmlspecialchars($u['role'] ?? 'Student') ?>
                </span>
              </div>
              <div class="user-section text-secondary fw-medium">
                <?php 
                  if (!empty($u['program'])) {
                      echo htmlspecialchars($u['program']) . ' ' . htmlspecialchars($u['year'] ?? '') . '-' . htmlspecialchars($u['section'] ?? '');
                  } else {
                      echo '<span class="text-muted small">—</span>';
                  }
                ?>
              </div>
              <div class="user-actions">
                <button class="btn-edit-user" title="Edit"
                        onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn-delete-user" title="Delete"
                        onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($display_name)) ?>')">
                  <i class="bi bi-trash3"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

    <style>
      .mgr-panel { background:white; border:1px solid var(--border-color); border-radius:20px; overflow:hidden; margin-top:18px; }
      .mgr-panel-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--border-color); }
      .mgr-panel-title { font-family:var(--font-heading); font-size:15px; font-weight:600; color:var(--text-primary); margin:0; }
      .mgr-row { display:grid; grid-template-columns: 1.4fr 1.6fr 1.4fr 220px; align-items:center; padding:12px 24px; border-bottom:1px solid var(--border-color); transition:background-color 0.15s ease; gap:12px; }
      .mgr-row:last-child { border-bottom:none; }
      .mgr-row:hover { background:#f9fffe; }
      .mgr-table-header { display:grid; grid-template-columns: 1.4fr 1.6fr 1.4fr 220px; align-items:center; padding:10px 24px; font-size:12px; font-weight:500; color:var(--text-muted); letter-spacing:0.3px; border-bottom:1px solid var(--border-color); background:#fafafa; }
      .mgr-section-name { font-family:var(--font-heading); font-weight:600; font-size:14px; color:var(--text-primary); }
      .mgr-section-meta { font-size:12px; color:var(--text-muted); margin-top:2px; }
      .mgr-person { display:flex; align-items:center; gap:10px; }
      .mgr-avatar { width:30px; height:30px; min-width:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:600; font-size:11px; font-family:var(--font-heading); }
      .mgr-person-name { font-size:13px; font-weight:500; color:var(--text-primary); }
      .mgr-person-email { font-size:11px; color:var(--text-muted); }
      .mgr-empty { color:var(--text-muted); font-style:italic; font-size:13px; }
      .mgr-actions { display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap; }
      .btn-mgr { background:none; border:1px solid var(--border-color); padding:5px 10px; border-radius:8px; font-size:12px; cursor:pointer; transition:all 0.15s; font-family:var(--font-heading); display:inline-flex; align-items:center; gap:4px; }
      .btn-mgr-assign { color:var(--accent-green); border-color:#b3e6cc; }
      .btn-mgr-assign:hover { background:#eaf6f0; }
      .btn-mgr-change { color:#4a90d9; border-color:#b6d4f3; }
      .btn-mgr-change:hover { background:#eaf3fc; }
      .btn-mgr-remove { color:var(--accent-red); border-color:#f3c2c2; }
      .btn-mgr-remove:hover { background:#fff0f0; }
    </style>

    <div class="mgr-panel">
      <div class="mgr-panel-header">
        <p class="mgr-panel-title">Section Managers (<?= count($sections_mgr) ?>)</p>
        <button class="btn-new-user" onclick="openManagerModal('add')">
          <i class="bi bi-person-plus"></i> Assign Manager
        </button>
      </div>

      <?php if (empty($sections_mgr)): ?>
        <div class="mgr-empty" style="padding:32px 24px; text-align:center;">
          <i class="bi bi-diagram-3" style="font-size:32px; opacity:0.4; display:block; margin-bottom:8px;"></i>
          No sections yet. Create a section first to assign a manager.
        </div>
      <?php else: ?>
        <div class="mgr-table-header">
          <span>Section</span>
          <span>Current Manager</span>
          <span>Contact</span>
          <span style="text-align:right;">Actions</span>
        </div>
        <?php foreach ($sections_mgr as $row):
          $sec_display = $row['name'] . ' · ' . ($row['program'] ?? '');
          if (!empty($row['year']) && !empty($row['section'])) $sec_display .= ' ' . $row['year'] . '-' . $row['section'];
          $has_mgr = !empty($row['manager_id']);
        ?>
          <div class="mgr-row">
            <div>
              <div class="mgr-section-name"><?= htmlspecialchars($row['name']) ?></div>
              <div class="mgr-section-meta">
                <?= htmlspecialchars($sec_display) ?>
                <?php if (!empty($row['school_year'])): ?>
                  · <?= htmlspecialchars($row['school_year']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <?php if ($has_mgr): ?>
                <?php $mgr_disp = $row['manager_name'] ?: 'User #' . (int)$row['manager_id']; ?>
                <div class="mgr-person">
                  <div class="mgr-avatar" style="background:<?= avatarColor($mgr_disp) ?>;">
                    <?= initials($mgr_disp) ?>
                  </div>
                  <div>
                    <div class="mgr-person-name"><?= htmlspecialchars($mgr_disp) ?></div>
                    <div class="mgr-person-email" style="font-size:11px;color:var(--text-muted);">
                      <?= htmlspecialchars($row['manager_role'] ?? '') ?>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <span class="mgr-empty">— No manager assigned —</span>
              <?php endif; ?>
            </div>
            <div class="mgr-person-email">
              <?php if ($has_mgr): ?>
                <div style="font-size:12px;"><?= htmlspecialchars($row['manager_email'] ?? '') ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($row['manager_student_id'] ?? '') ?></div>
              <?php else: ?>
                <span class="mgr-empty">—</span>
              <?php endif; ?>
            </div>
            <div class="mgr-actions">
              <?php if ($has_mgr): ?>
                <button class="btn-mgr btn-mgr-change" onclick="openManagerModal('change', <?= (int)$row['id'] ?>, <?= (int)$row['manager_id'] ?>)">
                  <i class="bi bi-arrow-left-right"></i> Change
                </button>
                <button class="btn-mgr btn-mgr-remove" onclick="confirmRemoveManager(<?= (int)$row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['manager_name'] ?? '')) ?>')">
                  <i class="bi bi-x-circle"></i> Remove
                </button>
              <?php else: ?>
                <button class="btn-mgr btn-mgr-assign" onclick="openManagerModal('add', <?= (int)$row['id'] ?>)">
                  <i class="bi bi-person-plus"></i> Assign
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Manager Assign/Change Modal -->
<div class="modal fade" id="managerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" id="managerModalTitle" style="font-family:var(--font-heading);">Assign Manager</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="managerError" class="alert-error mb-3" style="display:none;"></div>
        <form id="managerForm">
          <input type="hidden" name="section_id" id="mgr_field_section_id">

          <div class="mb-3">
            <label class="form-label small fw-medium">Section</label>
            <select name="section_id_select" id="mgr_field_section_select" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
              <option value="">— Select Section —</option>
              <?php foreach ($sections_mgr as $row): ?>
                <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3" id="currentMgrWrap" style="display:none;">
            <label class="form-label small fw-medium">Current Manager</label>
            <div id="currentMgrText" class="form-control" style="background:#fafafa;border-radius:10px;border-color:var(--border-color);font-size:13px;"></div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-medium">Manager (User)</label>
            <select name="user_id" id="mgr_field_user_id" class="form-select" required style="border-radius:10px;border-color:var(--border-color);">
              <option value="">— Select User —</option>
              <?php foreach ($eligible_users as $eu): ?>
                <option value="<?= (int)$eu['id'] ?>">
                  <?= htmlspecialchars($eu['full_name']) ?> · <?= htmlspecialchars($eu['role']) ?>
                  <?= !empty($eu['section_name']) ? ' · ' . htmlspecialchars($eu['section_name']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn-login w-100" id="managerSubmitBtn">Assign Manager</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Remove Manager Confirmation Modal -->
<div class="modal fade" id="removeManagerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-medium" style="font-family:var(--font-heading);">Remove Manager?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2 pb-1">
        <p class="text-muted small mb-0">
          Remove <strong id="removeMgrName">—</strong> as the manager of <strong id="removeMgrSection">—</strong>?
        </p>
      </div>
      <div class="modal-footer border-0 pt-2">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="confirmRemoveMgrBtn">Remove</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg"> <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" id="userModalTitle" style="font-family:var(--font-heading);">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="userError" class="alert-error mb-3" style="display:none;"></div>
        <form id="userForm">
          <input type="hidden" name="user_id" id="field_user_id">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-medium">First Name</label>
              <input type="text" name="first_name" id="field_first_name" class="form-control" required maxlength="120"
                     placeholder="e.g. Juan" style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-medium">Email</label>
              <input type="email" name="email" id="field_email" class="form-control" required maxlength="160"
                     placeholder="e.g. juan@iskolarngbayan.pup.edu.ph" style="border-radius:10px;border-color:var(--border-color);">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-medium">Student ID</label>
              <input type="text" name="student_id" id="field_student_id" class="form-control" required maxlength="30"
                     placeholder="e.g. 2024-00502-ST-0" style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-medium">System Role</label>
              <select name="role" id="field_role" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
                <option value="Student">Student</option>
                <option value="Admin">Admin</option>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-medium">Program / Course</label>
              <select name="course" id="field_course" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
                <option value="">— Select Program —</option>
                <option value="BSIT">BSIT</option>
                <option value="BSCS">BSCS</option>
                <option value="BSIS">BSIS</option>
                <option value="ACT">ACT</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-medium">Year Level</label>
              <input type="number" name="year" id="field_year" class="form-control" min="1" max="5" placeholder="e.g. 2"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-medium">Section</label>
              <input type="text" name="section" id="field_section" class="form-control" placeholder="e.g. 1"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
          </div>

          <div class="mb-4" id="passwordField">
            <label class="form-label small fw-medium">Password <span class="text-muted fw-normal" id="passwordHint">(required)</span></label>
            <input type="password" name="password" id="field_password" class="form-control" maxlength="255"
                   placeholder="Enter password" style="border-radius:10px;border-color:var(--border-color);">
          </div>

          <button type="submit" class="btn-login w-100" id="userSubmitBtn">Add User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-medium" style="font-family:var(--font-heading);">Delete User?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2 pb-1">
        <p class="text-muted small mb-0">This will permanently delete <strong id="deleteUserName"></strong> and all their data.</p>
      </div>
      <div class="modal-footer border-0 pt-2">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let userModalObj = null;
let deleteModalObj = null;

document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
  if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

  userModalObj = new bootstrap.Modal(document.getElementById('userModal'));
  deleteModalObj = new bootstrap.Modal(document.getElementById('deleteUserModal'));
});

function openAddModal() {
  document.getElementById('userModalTitle').textContent = 'Add User';
  document.getElementById('userSubmitBtn').textContent  = 'Add User';
  document.getElementById('userForm').reset();
  document.getElementById('field_user_id').value = '';
  document.getElementById('field_password').required = true;
  document.getElementById('passwordHint').textContent = '(required)';
  document.getElementById('userError').style.display = 'none';
  userModalObj.show();
}

function openEditModal(u) {
  document.getElementById('userModalTitle').textContent = 'Edit User';
  document.getElementById('userSubmitBtn').textContent  = 'Save Changes';
  document.getElementById('field_user_id').value    = u.id;
  document.getElementById('field_first_name').value  = u.full_name || '';
  document.getElementById('field_email').value      = u.email || '';
  document.getElementById('field_student_id').value = u.student_id || '';
  document.getElementById('field_role').value       = u.role || 'Student';
  
  // NEW: Populate academic values into edit modal view dynamically
  document.getElementById('field_course').value    = u.course || '';
  document.getElementById('field_year').value      = u.year || '';
  document.getElementById('field_section').value   = u.section || '';
  
  document.getElementById('field_password').value   = '';
  document.getElementById('field_password').required = false;
  document.getElementById('passwordHint').textContent = '(leave blank to keep current)';
  document.getElementById('userError').style.display = 'none';
  userModalObj.show();
}

document.getElementById('userForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  const isEdit = !!fd.get('user_id');
  fd.append('action', isEdit ? 'edit_user' : 'add_user');
  const errEl = document.getElementById('userError');
  errEl.style.display = 'none';

  fetch('actions/user_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        userModalObj.hide();
        window.location.reload();
      } else {
        errEl.textContent = data.message || 'Something went wrong.';
        errEl.style.display = 'block';
      }
    })
    .catch(() => {
      errEl.textContent = 'Network error. Please try again.';
      errEl.style.display = 'block';
    });
});

let pendingDeleteId = null;

function confirmDelete(id, name) {
  pendingDeleteId = id;
  document.getElementById('deleteUserName').textContent = name;
  deleteModalObj.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
  if (!pendingDeleteId) return;
  const fd = new FormData();
  fd.append('action', 'delete_user');
  fd.append('user_id', pendingDeleteId);

  fetch('actions/user_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      deleteModalObj.hide();
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to delete.');
      }
      pendingDeleteId = null;
    })
    .catch(() => {
      deleteModalObj.hide();
      alert('Network error.');
      pendingDeleteId = null;
    });
});

// ===== Manager management =====
const ELIGIBLE_USERS = <?= $eligible_users_json ?? '[]' ?>;
let managerModalObj = null;
let removeMgrModalObj = null;
let managerMode = 'add';      // 'add' | 'change'
let pendingRemoveSectionId = null;

document.addEventListener('DOMContentLoaded', function () {
  managerModalObj   = new bootstrap.Modal(document.getElementById('managerModal'));
  removeMgrModalObj = new bootstrap.Modal(document.getElementById('removeManagerModal'));
});

function sectionNameById(sectionId) {
  // The dropdown options carry the visible label, so we look it up directly.
  const sel = document.getElementById('mgr_field_section_select');
  const opt = sel ? sel.querySelector(`option[value="${sectionId}"]`) : null;
  return opt ? opt.textContent.trim() : ('Section #' + sectionId);
}

function userLabelById(userId) {
  const u = ELIGIBLE_USERS.find(x => String(x.id) === String(userId));
  return u ? u.full_name : ('User #' + userId);
}

function resetManagerForm() {
  document.getElementById('managerForm').reset();
  document.getElementById('mgr_field_section_id').value = '';
  document.getElementById('currentMgrWrap').style.display = 'none';
  document.getElementById('currentMgrText').textContent = '';
  document.getElementById('managerError').style.display = 'none';
}

function openManagerModal(mode, sectionId, currentUserId) {
  managerMode = mode || 'add';
  resetManagerForm();

  const titleEl = document.getElementById('managerModalTitle');
  const submitBtn = document.getElementById('managerSubmitBtn');

  if (managerMode === 'change') {
    titleEl.textContent = 'Change Manager';
    submitBtn.textContent = 'Change Manager';
    document.getElementById('mgr_field_section_id').value = sectionId || '';
    document.getElementById('mgr_field_section_select').value = sectionId || '';
    document.getElementById('mgr_field_section_select').disabled = true;
    document.getElementById('currentMgrWrap').style.display = '';
    document.getElementById('currentMgrText').textContent = userLabelById(currentUserId) + ' (current)';
  } else {
    titleEl.textContent = 'Assign Manager';
    submitBtn.textContent = 'Assign Manager';
    document.getElementById('mgr_field_section_select').disabled = false;
  }
  managerModalObj.show();
}

function submitManagerForm(e) {
  e.preventDefault();
  const form = document.getElementById('managerForm');
  const fd = new FormData(form);
  const errEl = document.getElementById('managerError');
  errEl.style.display = 'none';

  // The visible "section_id_select" mirrors section_id; prefer the hidden value.
  let sectionId = parseInt(fd.get('section_id') || '0', 10);
  if (!sectionId) {
    sectionId = parseInt(fd.get('section_id_select') || '0', 10);
    if (sectionId) fd.set('section_id', String(sectionId));
  }
  const userId = parseInt(fd.get('user_id') || '0', 10);

  if (!sectionId) { errEl.textContent = 'Please select a section.'; errEl.style.display = 'block'; return; }
  if (!userId)    { errEl.textContent = 'Please select a user.';    errEl.style.display = 'block'; return; }

  fd.set('action', managerMode === 'change' ? 'change_manager' : 'assign_manager');
  // No need to send section_id_select
  fd.delete('section_id_select');

  const submitBtn = document.getElementById('managerSubmitBtn');
  const origLabel = submitBtn.textContent;
  submitBtn.disabled = true;
  submitBtn.textContent = 'Working…';

  fetch('actions/manager_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        managerModalObj.hide();
        window.location.reload();
      } else {
        errEl.textContent = data.message || 'Something went wrong.';
        errEl.style.display = 'block';
      }
    })
    .catch(() => {
      errEl.textContent = 'Network error. Please try again.';
      errEl.style.display = 'block';
    })
    .finally(() => {
      submitBtn.disabled = false;
      submitBtn.textContent = origLabel;
    });
}
document.getElementById('managerForm').addEventListener('submit', submitManagerForm);

function confirmRemoveManager(sectionId, sectionName, managerName) {
  pendingRemoveSectionId = sectionId;
  document.getElementById('removeMgrName').textContent = managerName || '—';
  document.getElementById('removeMgrSection').textContent = sectionName || ('Section #' + sectionId);
  removeMgrModalObj.show();
}

document.getElementById('confirmRemoveMgrBtn').addEventListener('click', function () {
  if (!pendingRemoveSectionId) return;
  const fd = new FormData();
  fd.append('action', 'remove_manager');
  fd.append('section_id', pendingRemoveSectionId);

  const btn = document.getElementById('confirmRemoveMgrBtn');
  btn.disabled = true;
  const orig = btn.textContent;
  btn.textContent = 'Removing…';

  fetch('actions/manager_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        removeMgrModalObj.hide();
        window.location.reload();
      } else {
        alert(data.message || 'Failed to remove.');
      }
      pendingRemoveSectionId = null;
    })
    .catch(() => {
      alert('Network error.');
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = orig;
    });
});
</script>
</body>
</html>