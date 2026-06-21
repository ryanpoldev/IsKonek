<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$is_admin = strtolower($_SESSION['role'] ?? '') === 'admin';
if (!$is_admin) {
    header('Location: dashboard.php');
    exit();
}

$sections_query = $conn->query("
    SELECT s.*, u.first_name, u.last_name,
           CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS manager_name
    FROM sections s
    LEFT JOIN users u ON u.id = s.manager_id
    ORDER BY s.name
");
$sections = $sections_query->fetch_all(MYSQLI_ASSOC);

$subjects_query = $conn->query("SELECT id, code, name FROM subjects ORDER BY code");
$all_subjects = $subjects_query->fetch_all(MYSQLI_ASSOC);

$users_query = $conn->query("SELECT id, CONCAT_WS(' ', first_name, mid_name, last_name) AS full_name, role FROM users ORDER BY first_name");
$all_users = $users_query->fetch_all(MYSQLI_ASSOC);

$section_subjects = [];
$ss_query = $conn->query("SELECT section_id, subject_id FROM section_subjects");
while ($row = $ss_query->fetch_assoc()) {
    $section_subjects[$row['section_id']][] = (int)$row['subject_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Sections – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
  <style>
    .sections-panel { background:white; border:1px solid var(--border-color); border-radius:20px; overflow:hidden; }
    .sections-panel-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--border-color); }
    .sections-panel-title { font-family:var(--font-heading); font-size:15px; font-weight:600; color:var(--text-primary); margin:0; }
    .btn-new-section { background-color:var(--accent-green); color:white; border:none; border-radius:10px; padding:8px 18px; font-size:14px; font-weight:500; font-family:var(--font-heading); transition:background-color 0.15s; display:flex; align-items:center; gap:6px; }
    .btn-new-section:hover { background-color:#28a06a; color:white; }
    .section-row { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid var(--border-color); transition:background-color 0.15s; gap:16px; }
    .section-row:last-child { border-bottom:none; }
    .section-row:hover { background-color:#f9fffe; }
    .section-info { flex:1; min-width:0; }
    .section-name { font-family:var(--font-heading); font-weight:600; font-size:14px; color:var(--text-primary); margin-bottom:4px; }
    .section-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .section-manager-badge { display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#856404; background:#fff3cd; padding:2px 8px; border-radius:20px; font-weight:500; }
    .section-subjects-list { display:flex; flex-wrap:wrap; gap:5px; margin-top:6px; }
    .subject-tag { font-size:11px; padding:2px 8px; background:#f0faf5; color:var(--accent-green); border-radius:20px; font-weight:500; border:1px solid #b3e6cc; }
    .section-actions { display:flex; gap:6px; }
    .btn-edit-sec, .btn-delete-sec { background:none; border:none; padding:5px 8px; border-radius:8px; font-size:13px; cursor:pointer; transition:background 0.15s, color 0.15s; }
    .btn-edit-sec { color:var(--text-muted); }
    .btn-edit-sec:hover { background:#eaf6f0; color:var(--accent-green); }
    .btn-delete-sec { color:var(--text-muted); }
    .btn-delete-sec:hover { background:#fff0f0; color:var(--accent-red); }
    .sections-empty { padding:60px 24px; text-align:center; color:var(--text-muted); }
    .sections-empty i { font-size:40px; margin-bottom:12px; display:block; opacity:0.4; }
    .modal-content { border-radius:20px !important; border:1px solid var(--border-color) !important; }
    .form-control:focus, .form-select:focus, .form-check-input:focus { border-color:var(--accent-green); box-shadow:0 0 0 3px rgba(51,183,122,0.15); }
    .form-check-input:checked { background-color:var(--accent-green); border-color:var(--accent-green); }
    .subjects-checklist { max-height:200px; overflow-y:auto; border:1px solid var(--border-color); border-radius:10px; padding:8px 12px; }
    .subjects-checklist .form-check { padding:5px 0; border-bottom:1px solid #f0f0f0; }
    .subjects-checklist .form-check:last-child { border-bottom:none; }
  </style>
</head>
<body>
<div class="app-wrapper">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/navbar.php'; ?>
    <div class="page-body">
      <div class="mb-4">
        <h2 class="hero-greeting mb-1">Manage Sections 🏫</h2>
        <p class="hero-sub text-muted">Create sections, assign managers, and set subjects for auto-enrollment.</p>
      </div>

      <div class="sections-panel">
        <div class="sections-panel-header">
          <p class="sections-panel-title">All Sections (<?= count($sections) ?>)</p>
          <button class="btn-new-section" data-bs-toggle="modal" data-bs-target="#sectionModal" onclick="openAddModal()">
            <i class="bi bi-plus-lg"></i> Add Section
          </button>
        </div>

        <?php if (empty($sections)): ?>
          <div class="sections-empty">
            <i class="bi bi-diagram-3"></i>
            <p class="mb-1 fw-medium" style="font-size:15px;">No sections yet</p>
          </div>
        <?php else: ?>
          <?php foreach ($sections as $sec): ?>
            <?php $sec_subjects = $section_subjects[$sec['id']] ?? []; ?>
            <div class="section-row">
              <div class="section-info">
                <div class="section-name">
                  <?= htmlspecialchars($sec['name']) ?>
                  <span class="text-muted fw-normal" style="font-size:12px;">
                    (<?= htmlspecialchars($sec['program']) ?> <?= $sec['year'] ?>-<?= $sec['section'] ?> · <?= htmlspecialchars($sec['school_year']) ?>)
                  </span>
                </div>
                <div class="section-meta">
                  <?php if ($sec['manager_name']): ?>
                    <span class="section-manager-badge"><i class="bi bi-person-check"></i><?= htmlspecialchars($sec['manager_name']) ?></span>
                  <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">No manager — first enrollee becomes manager</span>
                  <?php endif; ?>
                </div>
                <div class="section-subjects-list">
                  <?php if (empty($sec_subjects)): ?>
                    <span style="font-size:12px;color:var(--text-muted);">No subjects assigned</span>
                  <?php else: ?>
                    <?php foreach ($all_subjects as $subj): ?>
                      <?php if (in_array((int)$subj['id'], $sec_subjects)): ?>
                        <span class="subject-tag"><?= htmlspecialchars($subj['code']) ?></span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="section-actions">
                <button class="btn-edit-sec" onclick='openEditModal(<?= json_encode($sec) ?>, <?= json_encode($sec_subjects) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn-delete-sec" onclick="confirmDelete(<?= $sec['id'] ?>, '<?= htmlspecialchars(addslashes($sec['name'])) ?>')">
                  <i class="bi bi-trash3"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="sectionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" id="sectionModalTitle" style="font-family:var(--font-heading);">Add Section</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="sectionError" class="alert-error mb-3" style="display:none;"></div>
        <form id="sectionForm">
          <input type="hidden" name="section_id" id="field_section_id">

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-medium">Section Name</label>
              <input type="text" name="name" id="field_name" class="form-control" required maxlength="40"
                     placeholder="e.g. BSIT 2-1" style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-6">
              <label class="form-label small fw-medium">Program</label>
              <select name="program" id="field_program" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
                <option value="BSIT">BSIT</option>
                <option value="BSCS">BSCS</option>
                <option value="BSIS">BSIS</option>
                <option value="ACT">ACT</option>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-4">
              <label class="form-label small fw-medium">Year</label>
              <input type="number" name="year" id="field_year" class="form-control" min="1" max="5" value="2"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-4">
              <label class="form-label small fw-medium">Section No.</label>
              <input type="number" name="section" id="field_section" class="form-control" min="1" value="1"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-4">
              <label class="form-label small fw-medium">School Year</label>
              <input type="text" name="school_year" id="field_school_year" class="form-control" maxlength="20"
                     placeholder="e.g. 2024-2025" style="border-radius:10px;border-color:var(--border-color);">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-medium">Classroom Manager <span class="text-muted fw-normal">(optional)</span></label>
            <select name="manager_id" id="field_manager_id" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
              <option value="">— None (first enrollee becomes manager) —</option>
              <?php foreach ($all_users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-medium">Subjects</label>
            <div class="subjects-checklist">
              <?php foreach ($all_subjects as $subj): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="subjects[]"
                         value="<?= $subj['id'] ?>" id="subj_<?= $subj['id'] ?>">
                  <label class="form-check-label small" for="subj_<?= $subj['id'] ?>">
                    <strong><?= htmlspecialchars($subj['code']) ?></strong> — <?= htmlspecialchars($subj['name']) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <button type="submit" class="btn-login" id="sectionSubmitBtn">Add Section</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-medium" style="font-family:var(--font-heading);">Delete Section?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2 pb-1">
        <p class="text-muted small mb-0">Deleting <strong id="deleteSectionName"></strong> will not remove existing enrollments.</p>
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
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
  if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });
});

function openAddModal() {
  document.getElementById('sectionModalTitle').textContent = 'Add Section';
  document.getElementById('sectionSubmitBtn').textContent  = 'Add Section';
  document.getElementById('sectionForm').reset();
  document.getElementById('field_section_id').value = '';
  document.getElementById('sectionError').style.display = 'none';
  document.querySelectorAll('input[name="subjects[]"]').forEach(cb => cb.checked = false);
}

function openEditModal(sec, subjectIds) {
  document.getElementById('sectionModalTitle').textContent = 'Edit Section';
  document.getElementById('sectionSubmitBtn').textContent  = 'Save Changes';
  document.getElementById('field_section_id').value   = sec.id;
  document.getElementById('field_name').value          = sec.name;
  document.getElementById('field_program').value       = sec.program;
  document.getElementById('field_year').value          = sec.year;
  document.getElementById('field_section').value       = sec.section;
  document.getElementById('field_school_year').value   = sec.school_year;
  document.getElementById('field_manager_id').value    = sec.manager_id ?? '';
  document.getElementById('sectionError').style.display = 'none';
  document.querySelectorAll('input[name="subjects[]"]').forEach(cb => {
    cb.checked = subjectIds.includes(parseInt(cb.value));
  });
  new bootstrap.Modal(document.getElementById('sectionModal')).show();
}

document.getElementById('sectionForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action', fd.get('section_id') ? 'edit_section' : 'add_section');
  const errEl = document.getElementById('sectionError');
  errEl.style.display = 'none';
  fetch('actions/section_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) window.location.reload();
      else { errEl.textContent = data.message || 'Something went wrong.'; errEl.style.display = 'block'; }
    })
    .catch(() => { errEl.textContent = 'Network error.'; errEl.style.display = 'block'; });
});

let pendingDeleteId = null;
const deleteModal = new bootstrap.Modal(document.getElementById('deleteSectionModal'));

function confirmDelete(id, name) {
  pendingDeleteId = id;
  document.getElementById('deleteSectionName').textContent = name;
  deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
  if (!pendingDeleteId) return;
  const fd = new FormData();
  fd.append('action', 'delete_section');
  fd.append('section_id', pendingDeleteId);
  fetch('actions/section_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      deleteModal.hide();
      if (data.success) window.location.reload();
      else alert(data.message || 'Failed to delete.');
      pendingDeleteId = null;
    })
    .catch(() => { deleteModal.hide(); alert('Network error.'); pendingDeleteId = null; });
});
</script>
</body>
</html>