<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get enrolled subjects for filter
$sq = $conn->prepare("
    SELECT s.id, s.code, s.name, s.color
    FROM subjects s
    JOIN enrollments e ON e.subject_id = s.id
    WHERE e.user_id = ?
    ORDER BY s.code
");
$sq->bind_param("i", $user_id);
$sq->execute();
$enrolled_subjects = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>File Repository – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/repository.css" rel="stylesheet">
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
        <h2 class="hero-greeting mb-1">File Repo 📂</h2>
        <p class="hero-sub text-muted">Upload and download coursework files by subject.</p>
      </div>

      <div class="repo-layout">

        <aside class="repo-categories">
          <p class="categories-label">FILTER BY SUBJECT</p>
          <button class="category-item active" data-subject="0">
            <span class="d-flex align-items-center">
              <span class="category-dot" style="background:#33b77a;"></span> All Files
            </span>
          </button>
          <?php foreach ($enrolled_subjects as $subj): ?>
          <button class="category-item" data-subject="<?= $subj['id'] ?>">
            <span class="d-flex align-items-center gap-2">
              <span class="category-dot" style="background:<?= htmlspecialchars($subj['color']) ?>;"></span>
              <?= htmlspecialchars($subj['code']) ?>
            </span>
          </button>
          <?php endforeach; ?>
        </aside>

        <div class="repo-panel">
          <div class="repo-panel-header">
            <p class="repo-panel-title" id="panelTitle">All Files</p>
            <button class="btn-upload" id="uploadBtn">
              <i class="bi bi-upload"></i> Upload File
            </button>
          </div>

          <!-- Drop zone -->
          <div id="dropzone">
            <i class="bi bi-cloud-upload"></i>
            <p class="mb-1 fw-medium" style="font-size:14px;color:var(--text-secondary);">Click or drag & drop files here</p>
            <p class="mb-0" style="font-size:12px;color:var(--text-muted);">PDF, DOCX, PPTX, ZIP, PNG — Max 10MB</p>
            <input type="file" id="fileInput" multiple style="display:none;">
            <div class="upload-progress mt-3 mx-auto" id="uploadProgress" style="max-width:260px;">
              <div class="upload-progress-bar" id="uploadProgressBar"></div>
            </div>
            <div id="uploadStatus" class="mt-2" style="font-size:12px;color:var(--text-muted);"></div>
          </div>

          <!-- Upload subject select (hidden, shown when no subject filter) -->
          <div id="subjectSelectWrap" style="display:none;padding:0 24px 12px;">
            <label class="form-label small fw-medium">Upload to subject:</label>
            <select id="uploadSubjectSel" class="form-select form-select-sm" style="border-radius:10px;max-width:280px;">
              <option value="">— Select subject —</option>
              <?php foreach ($enrolled_subjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code'] . ' – ' . $s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- File table -->
          <div class="file-table-header">
            <span>File</span>
            <span>Uploaded By</span>
            <span>Size</span>
            <span>Date</span>
            <span>Actions</span>
          </div>
          <div id="fileList">
            <div class="repo-empty" id="emptyState">
              <i class="bi bi-folder2-open"></i>
              <p class="mb-1 fw-medium" style="font-size:15px;">No files yet</p>
              <p class="text-muted" style="font-size:13px;">Upload the first file for this subject.</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CURRENT_USER_ID = <?= $user_id ?>;
let activeSubject = 0;

// Sidebar toggle
const toggleBtn = document.getElementById('sidebarToggle');
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebarOverlay');
if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

// ── File icon by extension ────────────────────────────────────
function fileIcon(name) {
  const ext = (name.split('.').pop() || '').toLowerCase();
  const map = { pdf:'bi-file-earmark-pdf text-danger', docx:'bi-file-earmark-word text-primary', doc:'bi-file-earmark-word text-primary',
    pptx:'bi-file-earmark-ppt text-warning', ppt:'bi-file-earmark-ppt text-warning', xlsx:'bi-file-earmark-excel text-success',
    xls:'bi-file-earmark-excel text-success', zip:'bi-file-earmark-zip text-secondary', png:'bi-file-earmark-image text-info',
    jpg:'bi-file-earmark-image text-info', jpeg:'bi-file-earmark-image text-info', gif:'bi-file-earmark-image text-info',
    txt:'bi-file-earmark-text', mp4:'bi-file-earmark-play text-purple', mp3:'bi-file-earmark-music' };
  return map[ext] || 'bi-file-earmark';
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1048576).toFixed(2) + ' MB';
}

function formatDate(str) {
  const d = new Date(str);
  return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Load files ────────────────────────────────────────────────
function loadFiles(subjectId) {
  fetch(`actions/file_actions.php?action=get_files&subject_id=${subjectId}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      renderFiles(data.files);
    });
}

function renderFiles(files) {
  const list = document.getElementById('fileList');
  const empty = document.getElementById('emptyState');
  if (!files || files.length === 0) {
    list.innerHTML = '';
    list.appendChild(empty);
    empty.style.display = '';
    return;
  }
  empty.style.display = 'none';
  list.innerHTML = '';
  files.forEach(f => list.appendChild(buildRow(f)));
}

function buildRow(f) {
  const row = document.createElement('div');
  row.className = 'file-row';
  row.id = 'file-' + f.id;
  row.innerHTML = `
    <div class="d-flex align-items-center">
      <i class="bi ${escHtml(fileIcon(f.original_name))} file-icon"></i>
      <div>
        <div class="file-name">${escHtml(f.original_name)}</div>
        ${f.subject_code ? `<span class="badge rounded-pill" style="font-size:9px;background:${escHtml(f.subject_color)};color:white;">${escHtml(f.subject_code)}</span>` : ''}
      </div>
    </div>
    <span class="text-muted" style="font-size:12px;">${escHtml(f.uploaded_by)}</span>
    <span class="text-muted" style="font-size:12px;">${formatSize(f.file_size)}</span>
    <span class="text-muted" style="font-size:12px;">${formatDate(f.uploaded_at)}</span>
    <div class="d-flex gap-2 align-items-center">
      <a href="${escHtml(f.download_url)}" class="btn-dl" download>
        <i class="bi bi-download me-1"></i>Download
      </a>
      ${f.is_mine ? `<button class="btn-del delete-file-btn" data-id="${f.id}" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
    </div>`;
  row.querySelector('.delete-file-btn')?.addEventListener('click', function () {
    if (!confirm('Delete this file?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('file_id', this.dataset.id);
    fetch('actions/file_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => { if (d.success) { row.remove(); checkEmpty(); } });
  });
  return row;
}

function checkEmpty() {
  const list = document.getElementById('fileList');
  if (list.querySelectorAll('.file-row').length === 0) {
    const empty = document.getElementById('emptyState');
    list.innerHTML = '';
    list.appendChild(empty);
    empty.style.display = '';
  }
}

// ── Subject filter ────────────────────────────────────────────
document.querySelectorAll('.category-item').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.category-item').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    activeSubject = parseInt(this.dataset.subject);
    const label = this.textContent.trim();
    document.getElementById('panelTitle').textContent = activeSubject === 0 ? 'All Files' : label + ' Files';
    // Show subject selector only when "All" is active
    document.getElementById('subjectSelectWrap').style.display = activeSubject === 0 ? 'block' : 'none';
    document.getElementById('uploadSubjectSel').value = activeSubject || '';
    loadFiles(activeSubject);
  });
});

// ── Upload ────────────────────────────────────────────────────
const dropzone  = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const uploadBtn = document.getElementById('uploadBtn');

uploadBtn.addEventListener('click', () => fileInput.click());
dropzone.addEventListener('click', e => { if (e.target === dropzone || dropzone.contains(e.target)) fileInput.click(); });
fileInput.addEventListener('change', e => uploadFiles(e.target.files));

['dragenter','dragover'].forEach(ev => dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.add('drag-over'); }));
['dragleave','drop'].forEach(ev => dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.remove('drag-over'); }));
dropzone.addEventListener('drop', e => uploadFiles(e.dataTransfer.files));

function uploadFiles(files) {
  Array.from(files).forEach(file => uploadSingle(file));
  fileInput.value = '';
}

function uploadSingle(file) {
  let subjectId = activeSubject;
  if (!subjectId) {
    const sel = document.getElementById('uploadSubjectSel');
    subjectId = parseInt(sel.value) || 0;
    if (!subjectId) {
      document.getElementById('subjectSelectWrap').style.display = 'block';
      document.getElementById('uploadStatus').textContent = 'Please select a subject before uploading.';
      document.getElementById('uploadStatus').style.color = '#dc3545';
      return;
    }
  }

  const fd = new FormData();
  fd.append('action', 'upload');
  fd.append('subject_id', subjectId);
  fd.append('file', file);

  const progress = document.getElementById('uploadProgress');
  const bar      = document.getElementById('uploadProgressBar');
  const status   = document.getElementById('uploadStatus');
  progress.style.display = 'block';
  bar.style.width = '30%';
  status.textContent = 'Uploading ' + file.name + '…';
  status.style.color = 'var(--text-muted)';

  fetch('actions/file_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      bar.style.width = '100%';
      if (data.success) {
        status.textContent = file.name + ' uploaded!';
        status.style.color = 'var(--accent-green)';
        // Add to list if in same subject view
        const f = data.file;
        f.is_mine = true;
        if (activeSubject === 0 || activeSubject === subjectId) {
          const empty = document.getElementById('emptyState');
          empty.style.display = 'none';
          document.getElementById('fileList').prepend(buildRow(f));
        }
      } else {
        status.textContent = data.message || 'Upload failed.';
        status.style.color = '#dc3545';
      }
      setTimeout(() => { progress.style.display = 'none'; bar.style.width = '0'; }, 2000);
    })
    .catch(() => {
      status.textContent = 'Network error. Try again.';
      status.style.color = '#dc3545';
      progress.style.display = 'none';
    });
}

// Initial load
loadFiles(0);
</script>
</body>
</html>
