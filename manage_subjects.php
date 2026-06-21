<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

// Bulk AJAX handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_section_bulk') {
    header('Content-Type: application/json');
    $subj_ids = json_decode($_POST['subject_ids'] ?? '[]');
    $sec_id  = (int)($_POST['section_id'] ?? 0);

    if (!empty($subj_ids) && $sec_id > 0) {
        $ins_ss = $conn->prepare("INSERT IGNORE INTO section_subjects (section_id, subject_id) VALUES (?, ?)");
        
        $u_stmt = $conn->prepare("SELECT id FROM users WHERE section_id = ?");
        $u_stmt->bind_param("i", $sec_id);
        $u_stmt->execute();
        $users = $u_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $u_stmt->close();

        $en_stmt = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, subject_id) VALUES (?, ?)");

        foreach ($subj_ids as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) {
                $ins_ss->bind_param("ii", $sec_id, $sid);
                $ins_ss->execute();
                if (!empty($users)) {
                    foreach ($users as $u) {
                        $en_stmt->bind_param("ii", $u['id'], $sid);
                        $en_stmt->execute();
                    }
                }
            }
        }
        if(isset($ins_ss)) $ins_ss->close();
        if(isset($en_stmt)) $en_stmt->close();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    exit();
}

$subjects_query = $conn->query("SELECT * FROM subjects ORDER BY code");
$subjects = $subjects_query->fetch_all(MYSQLI_ASSOC);

$uid = $_SESSION['user_id'];
$sec_stmt = $conn->prepare("
    SELECT u.section_id, s.name 
    FROM users u 
    LEFT JOIN sections s ON u.section_id = s.id 
    WHERE u.id = ?
");
$sec_stmt->bind_param("i", $uid);
$sec_stmt->execute();
$my_sec = $sec_stmt->get_result()->fetch_assoc();
$sec_stmt->close();

$my_section_id = $my_sec['section_id'] ?? null;
$my_section_name = $my_sec['name'] ?? '';

$color_options = [
    '#33b77a', '#4a90d9', '#d23c3c', '#f5a623',
    '#9b59b6', '#1abc9c', '#e67e22', '#2980b9'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Subjects – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/manage_subjects.css" rel="stylesheet">
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
        <h2 class="hero-greeting mb-1">Manage Subjects 📚</h2>
        <p class="hero-sub text-muted">Add, edit, or remove subjects and their assigned professors.</p>
      </div>

      <?php if ($my_section_id): ?>
      <div class="mb-4 p-4" style="background: white; border: 1px solid var(--border-color); border-radius: 20px;">
        <h6 class="fw-semibold mb-3" style="font-family: var(--font-heading);">
          <i class="bi bi-people-fill text-muted me-2"></i>Enroll Section: <?= htmlspecialchars($my_section_name) ?>
        </h6>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <p class="text-muted small mb-0 me-2">Select subjects from the list below and click:</p>
          <button class="btn text-white" style="background: var(--accent-green); border-radius: 10px; font-weight: 500;" onclick="enrollSelected(<?= $my_section_id ?>)">
            <i class="bi bi-box-arrow-in-right me-1"></i> Enroll Selected
          </button>
        </div>
        <div id="enrollStatus" class="mt-2 small" style="display:none;"></div>
      </div>
      <?php endif; ?>

      <div class="subjects-panel">
        <div class="subjects-panel-header">
          <p class="subjects-panel-title">All Subjects (<?= count($subjects) ?>)</p>
          <button class="btn-new-subject" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="openAddModal()">
            <i class="bi bi-plus-lg"></i> Add Subject
          </button>
        </div>

        <?php if (empty($subjects)): ?>
          <div class="subjects-empty">
            <i class="bi bi-journal-bookmark"></i>
            <p class="mb-1 fw-medium" style="font-size:15px;">No subjects yet</p>
            <p style="font-size:13px;">Add your first subject to get started.</p>
          </div>
        <?php else: ?>
          <div class="subject-table-header">
            <span><input class="form-check-input" type="checkbox" id="selectAllSubj" onchange="toggleAllSubj(this)"></span>
            <span>Code</span>
            <span>Subject Name</span>
            <span>Professor</span>
            <span style="text-align:center;">Units</span>
            <span></span>
          </div>

          <?php foreach ($subjects as $subj): ?>
            <div class="subject-row">
              <div><input class="form-check-input subj-cb" type="checkbox" value="<?= $subj['id'] ?>"></div>
              <div class="subject-code">
                <span class="subject-color-dot" style="background:<?= htmlspecialchars($subj['color']) ?>;"></span>
                <?= htmlspecialchars($subj['code']) ?>
              </div>
              <div class="subject-name"><?= htmlspecialchars($subj['name']) ?></div>
              <div class="subject-instructor"><?= htmlspecialchars($subj['instructor'] ?? '—') ?></div>
              <div class="subject-units" style="text-align:center;"><?= (int)$subj['units'] ?></div>
              <div class="subject-actions">
                <button class="btn-edit-subj" title="Edit" onclick='openEditModal(<?= json_encode($subj) ?>)'><i class="bi bi-pencil"></i></button>
                <button class="btn-delete-subj" title="Delete" onclick="confirmDelete(<?= $subj['id'] ?>, '<?= htmlspecialchars(addslashes($subj['code'])) ?>')"><i class="bi bi-trash3"></i></button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" id="subjectModalTitle" style="font-family:var(--font-heading);">Add Subject</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="subjectError" class="alert-error mb-3" style="display:none;"></div>
        <form id="subjectForm">
          <input type="hidden" name="subject_id" id="field_subject_id">
          <input type="hidden" name="color" id="field_color" value="#33b77a">
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-medium">Subject Code</label>
              <input type="text" name="code" id="field_code" class="form-control" required maxlength="20" placeholder="e.g. COMP 009" style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-3">
              <label class="form-label small fw-medium">Units</label>
              <input type="number" name="units" id="field_units" class="form-control" required min="1" max="9" value="3" style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-3">
              <label class="form-label small fw-medium">Color</label>
              <div class="color-picker mt-1" id="colorPicker">
                <?php foreach ($color_options as $c): ?>
                  <span class="color-swatch <?= $c === '#33b77a' ? 'selected' : '' ?>" style="background:<?= $c ?>;" data-color="<?= $c ?>" onclick="selectColor('<?= $c ?>', this)"></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Subject Name</label>
            <input type="text" name="name" id="field_name" class="form-control" required maxlength="160" placeholder="e.g. Object Oriented Programming" style="border-radius:10px;border-color:var(--border-color);">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Professor / Faculty Name</label>
            <input type="text" name="instructor" id="field_instructor" class="form-control" maxlength="120" placeholder="e.g. VELASCO, MARIENEL N." style="border-radius:10px;border-color:var(--border-color);">
          </div>
          <div class="mb-4">
            <label class="form-label small fw-medium">Semester</label>
            <input type="text" name="semester" id="field_semester" class="form-control" maxlength="40" placeholder="e.g. 2nd Sem, A.Y. 2024-25" style="border-radius:10px;border-color:var(--border-color);">
          </div>
          <button type="submit" class="btn-login" id="subjectSubmitBtn">Add Subject</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteSubjectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-medium" style="font-family:var(--font-heading);">Delete Subject?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2 pb-1">
        <p class="text-muted small mb-0">Deleting <strong id="deleteSubjectCode"></strong> will also remove all its enrollments, announcements, and forum posts.</p>
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

function toggleAllSubj(el) {
  document.querySelectorAll('.subj-cb').forEach(cb => cb.checked = el.checked);
}

function enrollSelected(secId) {
  const checked = Array.from(document.querySelectorAll('.subj-cb:checked')).map(cb => cb.value);
  const stat = document.getElementById('enrollStatus');

  if (checked.length === 0) {
    stat.style.display = 'block';
    stat.className = 'mt-2 small text-danger fw-medium';
    stat.textContent = 'Select at least one subject from the list below.';
    return;
  }

  if (!confirm(`Enroll section in ${checked.length} subject(s)?`)) return;

  const fd = new FormData();
  fd.append('action', 'enroll_section_bulk');
  fd.append('subject_ids', JSON.stringify(checked));
  fd.append('section_id', secId);

  fetch('manage_subjects.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      stat.style.display = 'block';
      if (data.success) {
        stat.className = 'mt-2 small text-success fw-medium';
        stat.textContent = `${checked.length} subjects enrolled successfully!`;
        document.querySelectorAll('.subj-cb').forEach(cb => cb.checked = false);
        document.getElementById('selectAllSubj').checked = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        stat.className = 'mt-2 small text-danger fw-medium';
        stat.textContent = data.message || 'Error enroll.';
      }
    });
}

function selectColor(color, el) {
  document.getElementById('field_color').value = color;
  document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
  el.classList.add('selected');
}

function openAddModal() {
  document.getElementById('subjectModalTitle').textContent = 'Add Subject';
  document.getElementById('subjectSubmitBtn').textContent  = 'Add Subject';
  document.getElementById('subjectForm').reset();
  document.getElementById('field_subject_id').value = '';
  document.getElementById('field_color').value = '#33b77a';
  document.getElementById('field_units').value = '3';
  document.querySelectorAll('.color-swatch').forEach(s => {
    s.classList.toggle('selected', s.dataset.color === '#33b77a');
  });
  document.getElementById('subjectError').style.display = 'none';
}

function openEditModal(subj) {
  document.getElementById('subjectModalTitle').textContent = 'Edit Subject';
  document.getElementById('subjectSubmitBtn').textContent  = 'Save Changes';
  document.getElementById('field_subject_id').value  = subj.id;
  document.getElementById('field_code').value        = subj.code;
  document.getElementById('field_name').value        = subj.name;
  document.getElementById('field_instructor').value  = subj.instructor ?? '';
  document.getElementById('field_units').value       = subj.units;
  document.getElementById('field_semester').value    = subj.semester ?? '';
  document.getElementById('field_color').value       = subj.color;
  document.querySelectorAll('.color-swatch').forEach(s => {
    s.classList.toggle('selected', s.dataset.color === subj.color);
  });
  document.getElementById('subjectError').style.display = 'none';
  new bootstrap.Modal(document.getElementById('subjectModal')).show();
}

document.getElementById('subjectForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  const isEdit = !!fd.get('subject_id');
  fd.append('action', isEdit ? 'edit_subject' : 'add_subject');
  const errEl = document.getElementById('subjectError');
  errEl.style.display = 'none';

  fetch('actions/subject_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        errEl.textContent = data.message || 'Something went wrong.';
        errEl.style.display = 'block';
      }
    });
});

let pendingDeleteId = null;
const deleteModal = new bootstrap.Modal(document.getElementById('deleteSubjectModal'));

function confirmDelete(id, code) {
  pendingDeleteId = id;
  document.getElementById('deleteSubjectCode').textContent = code;
  deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
  if (!pendingDeleteId) return;
  const fd = new FormData();
  fd.append('action', 'delete_subject');
  fd.append('subject_id', pendingDeleteId);

  fetch('actions/subject_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      deleteModal.hide();
      if (data.success) window.location.reload();
      else alert(data.message || 'Failed to delete.');
      pendingDeleteId = null;
    });
});
</script>
</body>
</html>