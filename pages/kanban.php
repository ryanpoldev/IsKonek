<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$userName = $_SESSION['full_name'] ?? 'Student';
$firstName = explode(' ', $userName)[0];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="../">
  <title>Kanban Board - Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/kanban.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>
<body>
<div class="app-wrapper">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <?php include '../includes/sidebar.php'; ?>

  <div class="main-content">
    <?php include '../includes/navbar.php'; ?>

    <div class="page-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h2 class="hero-greeting mb-1">Project Board, <?= htmlspecialchars($firstName) ?>!</h2>
          <p class="hero-sub text-muted mb-0">Track your coursework tasks and move them as you progress.</p>
        </div>
      </div>

      <div class="kanban-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm" id="addTaskBtn" style="background:var(--accent-green);color:#fff;border-radius:10px;">
            <i class="bi bi-plus-lg me-1"></i>Add Task
          </button>
          <button class="btn btn-sm btn-outline-secondary" id="resetBoardBtn" style="border-radius:10px;border-color:var(--border-color);">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Clear Board
          </button>
        </div>
        <span class="small text-muted">Drag and drop cards between columns</span>
      </div>

      <div class="kanban-board" id="kanbanBoard">
        <section class="kanban-column" data-status="todo">
          <div class="kanban-col-head">
            <p class="kanban-col-title">To Do</p>
            <span class="kanban-count" id="count-todo">0</span>
          </div>
          <div class="kanban-dropzone" id="col-todo"></div>
        </section>

        <section class="kanban-column" data-status="progress">
          <div class="kanban-col-head">
            <p class="kanban-col-title">In Progress</p>
            <span class="kanban-count" id="count-progress">0</span>
          </div>
          <div class="kanban-dropzone" id="col-progress"></div>
        </section>

        <section class="kanban-column" data-status="review">
          <div class="kanban-col-head">
            <p class="kanban-col-title">For Review</p>
            <span class="kanban-count" id="count-review">0</span>
          </div>
          <div class="kanban-dropzone" id="col-review"></div>
        </section>

        <section class="kanban-column" data-status="done">
          <div class="kanban-col-head">
            <p class="kanban-col-title">Done</p>
            <span class="kanban-count" id="count-done">0</span>
          </div>
          <div class="kanban-dropzone" id="col-done"></div>
        </section>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;border:1px solid var(--border-color);">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" style="font-family:var(--font-heading);">Create Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="taskForm">
          <div class="mb-3">
            <label class="form-label small fw-medium">Task Title</label>
            <input type="text" class="form-control" id="taskTitle" required maxlength="90" placeholder="Example: Finalize network topology report">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Subject / Context</label>
            <input type="text" class="form-control" id="taskSubject" maxlength="50" placeholder="Example: ITEC 106">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Priority</label>
            <select class="form-select" id="taskPriority">
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Deadline</label>
            <input type="date" class="form-control" id="taskDueDate">
          </div>
          <button type="submit" class="btn w-100" style="background:var(--accent-green);color:#fff;border-radius:10px;">Save Task</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('show');
      if (overlay) overlay.classList.toggle('show');
    });
  }

  if (overlay) {
    overlay.addEventListener('click', function () {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
    });
  }

  const apiUrl = 'actions/kanban_tasks.php';
  const columns = {
    todo: document.getElementById('col-todo'),
    progress: document.getElementById('col-progress'),
    review: document.getElementById('col-review'),
    done: document.getElementById('col-done')
  };

  const counts = {
    todo: document.getElementById('count-todo'),
    progress: document.getElementById('count-progress'),
    review: document.getElementById('count-review'),
    done: document.getElementById('count-done')
  };

  let tasks = [];
  loadTasks();

  const taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
  document.getElementById('addTaskBtn').addEventListener('click', function () {
    document.getElementById('taskForm').reset();
    taskModal.show();
  });

  document.getElementById('taskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const title = document.getElementById('taskTitle').value.trim();
    const subject = document.getElementById('taskSubject').value.trim();
    const priority = document.getElementById('taskPriority').value;
    const dueDate = document.getElementById('taskDueDate').value;

    if (!title) return;

    request('create', {
      title: title,
      subject: subject || 'General',
      priority: priority,
      due_date: dueDate || null
    }).then(function (resp) {
      if (!resp.success) {
        alert(resp.message || 'Failed to create task.');
        return;
      }
      tasks = resp.tasks || [];
      renderAll();
      taskModal.hide();
    });
  });

  document.getElementById('resetBoardBtn').addEventListener('click', function () {
    request('reset').then(function (resp) {
      if (!resp.success) {
        alert(resp.message || 'Failed to reset tasks.');
        return;
      }
      tasks = resp.tasks || [];
      renderAll();
    });
  });

  Object.values(columns).forEach(function (zone) {
    zone.addEventListener('dragover', function (e) {
      e.preventDefault();
      zone.classList.add('drop-target');
    });

    zone.addEventListener('dragleave', function () {
      zone.classList.remove('drop-target');
    });

    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('drop-target');
      const taskId = e.dataTransfer.getData('text/plain');
      const status = zone.id.replace('col-', '');
      request('update_status', { id: taskId, status: status }).then(function (resp) {
        if (!resp.success) {
          alert(resp.message || 'Failed to move task.');
          return;
        }
        tasks = resp.tasks || [];
        renderAll();
      });
    });
  });

  function renderAll() {
    Object.keys(columns).forEach(function (status) {
      columns[status].innerHTML = '';
      const list = tasks.filter(function (t) { return t.status === status; });
      list.forEach(function (task) {
        columns[status].appendChild(renderTask(task));
      });
      counts[status].textContent = String(list.length);
    });
  }

  function renderTask(task) {
    const card = document.createElement('article');
    card.className = 'kanban-task';
    card.draggable = true;
    card.dataset.id = task.id;

    card.innerHTML = '' +
      '<div class="d-flex justify-content-between align-items-start gap-2">' +
        '<div class="flex-grow-1">' +
          '<p class="task-title mb-1"></p>' +
          '<div class="task-meta">' +
            '<span class="task-subject"></span>' +
            '<span class="task-tag"></span>' +
          '</div>' +
        '</div>' +
        '<div class="task-actions">' +
          '<button type="button" class="btn btn-sm delete-task" title="Delete">' +
            '<i class="bi bi-trash"></i>' +
          '</button>' +
        '</div>' +
      '</div>';

    card.querySelector('.task-title').textContent = task.title;
    card.querySelector('.task-subject').textContent = task.subject;

    const tag = card.querySelector('.task-tag');
    tag.textContent = task.priority;
    tag.classList.add('tag-' + task.priority);

    const dueDate = document.createElement('div');
    dueDate.className = 'task-due-date mt-2';
    dueDate.innerHTML = task.due_date
      ? '<i class="bi bi-calendar-event me-1"></i>Due ' + formatDate(task.due_date)
      : '<i class="bi bi-calendar-event me-1"></i>No deadline';
    card.querySelector('.flex-grow-1').appendChild(dueDate);

    card.querySelector('.delete-task').addEventListener('click', function () {
      request('delete', { id: task.id }).then(function (resp) {
        if (!resp.success) {
          alert(resp.message || 'Failed to delete task.');
          return;
        }
        tasks = resp.tasks || [];
        renderAll();
      });
    });

    card.addEventListener('dragstart', function (e) {
      card.classList.add('dragging');
      e.dataTransfer.setData('text/plain', String(task.id));
    });

    card.addEventListener('dragend', function () {
      card.classList.remove('dragging');
    });

    return card;
  }

  function loadTasks() {
    fetch(apiUrl)
      .then(function (r) {
        return r.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (err) {
            throw new Error(text || 'Failed to parse server response.');
          }
        });
      })
      .then(function (resp) {
        if (!resp.success) {
          alert(resp.message || 'Failed to load tasks.');
          return;
        }
        tasks = resp.tasks || [];
        renderAll();
      })
      .catch(function (err) {
        alert(err && err.message ? err.message : 'Failed to load tasks.');
      });
  }

  function request(action, payload) {
    return fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ action: action }, payload || {}))
    })
      .then(function (r) {
        return r.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (err) {
            return { success: false, message: text || 'Failed to parse server response.' };
          }
        });
      })
      .catch(function () {
        return { success: false, message: 'Network error.' };
      });
  }

  function formatDate(value) {
    const date = new Date(value + 'T00:00:00');
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  }
})();
</script>
</body>
</html>
