<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id  = $_SESSION['user_id'];
$role     = strtolower($_SESSION['role'] ?? 'student');
$is_admin = in_array($role, ['admin', 'sup_admin']);

/* ── Handle POST: Add Event ── */
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    $title       = trim($_POST['title']      ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = $_POST['event_date']  ?? '';
    $event_time  = $_POST['event_time']  ?? '';
    $category    = $_POST['category']    ?? 'event';

    $allowed_categories = ['event', 'deadline', 'exam', 'activity', 'holiday'];
    if (!in_array($category, $allowed_categories)) $category = 'event';

    if ($title === '' || $event_date === '') {
        $error_msg = 'Title and date are required.';
    } else {
        // Verify user exists in database
        $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        if (!$user_check) {
            $error_msg = 'Database error. Please refresh the page.';
        } else {
            $user_check->bind_param("i", $user_id);
            $user_check->execute();
            $user_result = $user_check->get_result();
            
            if ($user_result->num_rows === 0) {
                $error_msg = 'User session error. Please log out and log in again.';
                $user_check->close();
            } else {
                $user_check->close();
                $event_time_val = ($event_time !== '') ? $event_time : null;
                
                // Check if category column exists
                $col_check = $conn->query("SHOW COLUMNS FROM events LIKE 'category'");
                $has_category = ($col_check && $col_check->num_rows > 0);
                
                if ($has_category) {
                    $stmt = $conn->prepare("
                        INSERT INTO events (title, description, event_date, event_time, category, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    if ($stmt) {
                        $stmt->bind_param("sssssi", $title, $description, $event_date, $event_time_val, $category, $user_id);
                        if ($stmt->execute()) {
                            $success_msg = 'Event added successfully!';
                        } else {
                            $error_msg = 'Failed to save event: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error_msg = 'Database error: ' . $conn->error;
                    }
                } else {
                    // Fallback: insert without category column
                    $stmt = $conn->prepare("
                        INSERT INTO events (title, description, event_date, event_time, created_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    if ($stmt) {
                        $stmt->bind_param("ssssi", $title, $description, $event_date, $event_time_val, $user_id);
                        if ($stmt->execute()) {
                            $success_msg = 'Event added successfully!';
                        } else {
                            $error_msg = 'Failed to save event: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error_msg = 'Database error: ' . $conn->error;
                    }
                }
            }
        }
    }
}

/* ── Handle POST: Delete Event ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    $del_id = (int)($_POST['event_id'] ?? 0);
    if ($del_id > 0) {
        // Only admin or creator can delete
        $del_stmt = $conn->prepare("DELETE FROM events WHERE id = ? AND (created_by = ? OR ? = 1)");
        $is_admin_int = $is_admin ? 1 : 0;
        $del_stmt->bind_param("iii", $del_id, $user_id, $is_admin_int);
        if ($del_stmt->execute()) {
            $success_msg = 'Event deleted.';
        } else {
            $error_msg = 'Failed to delete event.';
        }
        $del_stmt->close();
    }
}

/* ── Calendar month navigation ── */
$now   = new DateTime();
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)$now->format('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)$now->format('n');

// Clamp month
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$prev_month = $month - 1; $prev_year = $year;
if ($prev_month < 1)  { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$month_label    = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$first_day      = (int)date('w', mktime(0, 0, 0, $month, 1, $year)); // 0=Sun
$days_in_month  = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
$days_prev      = (int)date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));

/* ── Fetch events for visible range ── */
// We need up to 6 weeks, so grab prev month tail + current + next month head
$range_start = date('Y-m-d', mktime(0, 0, 0, $prev_month, $days_prev - $first_day + 1, $prev_year));
$range_end   = date('Y-m-d', mktime(0, 0, 0, $next_month, 14, $next_year));

// Check if category column exists
$has_category_col = false;
$col_check = $conn->query("SHOW COLUMNS FROM events LIKE 'category'");
if ($col_check && $col_check->num_rows > 0) $has_category_col = true;

// Build query based on whether category column exists
if ($has_category_col) {
    $ev_stmt = $conn->prepare("
        SELECT e.id, e.title, e.description, e.event_date, e.event_time, e.category, e.created_by,
               COALESCE(u.first_name, 'Unknown') AS first_name,
               COALESCE(u.last_name, 'User') AS last_name
        FROM events e
        LEFT JOIN users u ON u.id = e.created_by
        WHERE e.event_date BETWEEN ? AND ?
        ORDER BY e.event_date, e.event_time
    ");
} else {
    $ev_stmt = $conn->prepare("
        SELECT e.id, e.title, e.description, e.event_date, e.event_time, 'event' AS category, e.created_by,
               COALESCE(u.first_name, 'Unknown') AS first_name,
               COALESCE(u.last_name, 'User') AS last_name
        FROM events e
        LEFT JOIN users u ON u.id = e.created_by
        WHERE e.event_date BETWEEN ? AND ?
        ORDER BY e.event_date, e.event_time
    ");
}

if ($ev_stmt) {
    $ev_stmt->bind_param("ss", $range_start, $range_end);
    $ev_stmt->execute();
    $all_events = $ev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ev_stmt->close();
} else {
    $all_events = [];
}

// Index events by date
$events_by_date = [];
foreach ($all_events as $ev) {
    $events_by_date[$ev['event_date']][] = $ev;
}

/* ── Upcoming events (next 30 days from today) ── */
$today_str  = $now->format('Y-m-d');
$future_end = date('Y-m-d', strtotime('+30 days'));
$upcoming   = array_filter($all_events, fn($e) => $e['event_date'] >= $today_str && $e['event_date'] <= $future_end);
$upcoming   = array_slice(array_values($upcoming), 0, 8);

/* ── Category config ── */
$cat_config = [
    'event'    => ['label' => 'Event',    'chip' => 'chip-event',    'dot' => '#2563eb', 'icon' => 'bi-calendar-event'],
    'deadline' => ['label' => 'Deadline', 'chip' => 'chip-deadline', 'dot' => '#ef4444', 'icon' => 'bi-flag-fill'],
    'exam'     => ['label' => 'Exam',     'chip' => 'chip-exam',     'dot' => '#7c3aed', 'icon' => 'bi-pencil-fill'],
    'activity' => ['label' => 'Activity', 'chip' => 'chip-activity', 'dot' => '#16a34a', 'icon' => 'bi-lightning-fill'],
    'holiday'  => ['label' => 'Holiday',  'chip' => 'chip-holiday',  'dot' => '#d97706', 'icon' => 'bi-sun-fill'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link href="assets/css/calendar.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>

<body>
  <div class="app-wrapper">

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main -->
    <div class="main-content">

      <!-- Navbar -->
      <?php include 'includes/navbar.php'; ?>

      <!-- Page body -->
      <div class="page-body">

        <!-- Page header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
          <div>
            <h2 class="hero-greeting mb-1">Calendar</h2>
            <p class="hero-sub text-muted mb-0">Get updated on future events!</p>
          </div>
          <button class="btn-add-deadline d-flex align-items-center gap-2"
                  data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="bi bi-plus-lg"></i>
            Add Event
          </button>
        </div>

        <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 px-3 small mb-3" role="alert">
          <i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($success_msg) ?>
          <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small mb-3" role="alert">
          <i class="bi bi-exclamation-circle-fill me-1"></i><?= htmlspecialchars($error_msg) ?>
          <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ── Calendar + Sidebar layout ── -->
        <div class="row g-3 align-items-start">

          <!-- Calendar column -->
          <div class="col-12 col-lg-8">

            <!-- Month navigation -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <div class="cal-nav">
                <a href="?m=<?= $prev_month ?>&y=<?= $prev_year ?>" class="nav-btn text-decoration-none">
                  <i class="bi bi-chevron-left" style="font-size:.75rem;"></i>
                </a>
                <span class="month-label"><?= $month_label ?></span>
                <a href="?m=<?= $next_month ?>&y=<?= $next_year ?>" class="nav-btn text-decoration-none">
                  <i class="bi bi-chevron-right" style="font-size:.75rem;"></i>
                </a>
                <a href="calendar.php" class="today-btn text-decoration-none">Today</a>
              </div>

              <!-- Legend (hidden on xs) -->
              <div class="legend d-none d-sm-flex">
                <?php foreach ($cat_config as $key => $cfg): ?>
                <div class="legend-item">
                  <span class="legend-dot" style="background:<?= $cfg['dot'] ?>;"></span>
                  <?= $cfg['label'] ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Calendar grid -->
            <div class="cal-grid">
              <!-- Day-of-week headers -->
              <div class="cal-day-headers">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                <div class="cal-day-header"><?= $d ?></div>
                <?php endforeach; ?>
              </div>

              <!-- Cells -->
              <div class="cal-days">
                <?php
                $today_ymd = $now->format('Y-m-d');
                $cell_count = 0;

                // Leading cells from previous month
                for ($i = $first_day - 1; $i >= 0; $i--) {
                    $d   = $days_prev - $i;
                    $ymd = sprintf('%04d-%02d-%02d', $prev_year, $prev_month, $d);
                    echo '<div class="cal-cell other-month">';
                    echo   '<div class="day-num">' . $d . '</div>';
                    $this_day_evs = $events_by_date[$ymd] ?? [];
                    foreach (array_slice($this_day_evs, 0, 2) as $ev) {
                        $chip = $cat_config[$ev['category'] ?? 'event']['chip'] ?? 'chip-event';
                        echo '<div class="event-chip ' . $chip . ' d-none d-md-flex"
                                   onclick="showEventDetail(' . htmlspecialchars(json_encode($ev), ENT_QUOTES) . ')">'
                             . htmlspecialchars($ev['title']) . '</div>';
                    }
                    echo '</div>';
                    $cell_count++;
                }

                // Current month cells
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $ymd      = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $is_today = ($ymd === $today_ymd);
                    $day_evs  = $events_by_date[$ymd] ?? [];
                    $extra    = max(0, count($day_evs) - 2);

                    echo '<div class="cal-cell ' . ($is_today ? 'today' : '') . '">';
                    echo   '<div class="day-num">' . $d . '</div>';
                    foreach (array_slice($day_evs, 0, 2) as $ev) {
                        $chip = $cat_config[$ev['category'] ?? 'event']['chip'] ?? 'chip-event';
                        echo '<div class="event-chip ' . $chip . '"
                                   onclick="showEventDetail(' . htmlspecialchars(json_encode($ev), ENT_QUOTES) . ')">'
                             . htmlspecialchars($ev['title']) . '</div>';
                    }
                    if ($extra > 0) {
                        echo '<div class="event-chip" style="background:#f1f5f9;color:#64748b;">+'
                             . $extra . ' more</div>';
                    }
                    echo '</div>';
                    $cell_count++;
                }

                // Trailing cells from next month
                $trailing = 42 - $cell_count;
                for ($d = 1; $d <= $trailing; $d++) {
                    $ymd = sprintf('%04d-%02d-%02d', $next_year, $next_month, $d);
                    echo '<div class="cal-cell other-month">';
                    echo   '<div class="day-num">' . $d . '</div>';
                    $this_day_evs = $events_by_date[$ymd] ?? [];
                    foreach (array_slice($this_day_evs, 0, 2) as $ev) {
                        $chip = $cat_config[$ev['category'] ?? 'event']['chip'] ?? 'chip-event';
                        echo '<div class="event-chip ' . $chip . ' d-none d-md-flex"
                                   onclick="showEventDetail(' . htmlspecialchars(json_encode($ev), ENT_QUOTES) . ')">'
                             . htmlspecialchars($ev['title']) . '</div>';
                    }
                    echo '</div>';
                }
                ?>
              </div><!-- /.cal-days -->
            </div><!-- /.cal-grid -->

            <!-- Mobile legend -->
            <div class="legend d-flex d-sm-none flex-wrap mt-3">
              <?php foreach ($cat_config as $key => $cfg): ?>
              <div class="legend-item">
                <span class="legend-dot" style="background:<?= $cfg['dot'] ?>;"></span>
                <?= $cfg['label'] ?>
              </div>
              <?php endforeach; ?>
            </div>

          </div><!-- /col calendar -->

          <!-- Sidebar column: Upcoming events -->
          <div class="col-12 col-lg-4">
            <div class="upcoming-card">
              <div class="upcoming-title">
                <i class="bi bi-clock-history" style="color:#2563eb;"></i>
                Upcoming Events
              </div>

              <?php if (empty($upcoming)): ?>
              <p class="text-muted small mb-0">No upcoming events in the next 30 days.</p>
              <?php else: ?>
              <?php foreach ($upcoming as $ev):
                    $cat   = $ev['category'] ?? 'event';
                    $cfg   = $cat_config[$cat] ?? $cat_config['event'];
                    $d_fmt = date('M j', strtotime($ev['event_date']));
                    $t_fmt = $ev['event_time'] ? date('g:i A', strtotime($ev['event_time'])) : '';
              ?>
              <div class="upcoming-item" style="cursor:pointer;"
                   onclick="showEventDetail(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)">
                <span class="upcoming-dot" style="background:<?= $cfg['dot'] ?>;"></span>
                <div class="flex-grow-1">
                  <div class="upcoming-label"><?= htmlspecialchars($ev['title']) ?></div>
                  <div class="upcoming-sub"><?= $cfg['label'] ?><?= $t_fmt ? ' · ' . $t_fmt : '' ?></div>
                </div>
                <span class="upcoming-date"><?= $d_fmt ?></span>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div><!-- /col sidebar -->

        </div><!-- /.row -->

      </div><!-- /.page-body -->
    </div><!-- /.main-content -->
  </div><!-- /.app-wrapper -->

  <!-- ════════════════════════════════════
       Modal: Add Event
  ════════════════════════════════════ -->
  <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-sm rounded-3">
        <div class="modal-header event-modal-header py-3">
          <h6 class="modal-title fw-semibold mb-0" id="addEventModalLabel">
            <i class="bi bi-calendar-plus me-2"></i>Add New Event
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="calendar.php?m=<?= $month ?>&y=<?= $year ?>">
          <input type="hidden" name="action" value="add_event">
          <div class="modal-body p-4">

            <!-- Title -->
            <div class="mb-3">
              <label class="form-label small fw-semibold">Event Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control form-control-sm"
                     placeholder="e.g. Final Exam – CS101" required maxlength="255">
            </div>

            <!-- Date & Time row -->
            <div class="row g-2 mb-3">
              <div class="col-7">
                <label class="form-label small fw-semibold">Date <span class="text-danger">*</span></label>
                <input type="date" name="event_date" class="form-control form-control-sm"
                       min="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-5">
                <label class="form-label small fw-semibold">Time <span class="text-muted fw-normal">(opt.)</span></label>
                <input type="time" name="event_time" class="form-control form-control-sm">
              </div>
            </div>

            <!-- Category -->
            <div class="mb-3">
              <label class="form-label small fw-semibold">Category</label>
              <div class="filter-tabs">
                <?php foreach ($cat_config as $key => $cfg): ?>
                <label class="filter-tab <?= $key === 'event' ? 'active' : '' ?>"
                       id="tab-<?= $key ?>"
                       onclick="selectCategory('<?= $key ?>')">
                  <i class="bi <?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
                </label>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="category" id="categoryInput" value="event">
            </div>

            <!-- Description -->
            <div class="mb-1">
              <label class="form-label small fw-semibold">Description <span class="text-muted fw-normal">(opt.)</span></label>
              <textarea name="description" class="form-control form-control-sm"
                        rows="3" placeholder="Brief description of the event…" maxlength="1000"></textarea>
            </div>

          </div>
          <div class="modal-footer border-0 pt-0 px-4 pb-4 gap-2">
            <button type="button" class="btn btn-sm btn-light px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold">
              <i class="bi bi-check-lg me-1"></i>Save Event
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════
       Modal: Event Detail
  ════════════════════════════════════ -->
  <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content border-0 shadow-sm rounded-3">
        <div class="modal-header event-modal-header py-3" id="detailModalHeader">
          <div>
            <span class="modal-tag" id="detailTag"></span>
            <h6 class="modal-title fw-semibold mb-0" id="detailTitle"></h6>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 py-3">
          <p class="small mb-2" id="detailDateLine" style="color:#475569;"></p>
          <p class="small mb-0 text-muted" id="detailDesc"></p>
          <p class="small mt-2 mb-0" id="detailCreator" style="color:#94a3b8;font-size:.7rem;"></p>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-3" id="detailFooter">
          <form method="POST" action="calendar.php?m=<?= $month ?>&y=<?= $year ?>" id="deleteEventForm">
            <input type="hidden" name="action" value="delete_event">
            <input type="hidden" name="event_id" id="deleteEventId" value="">
            <button type="submit" class="btn btn-sm btn-outline-danger" id="modalDeleteBtn"
                    onclick="return confirm('Delete this event?')">
              <i class="bi bi-trash me-1"></i>Delete
            </button>
          </form>
          <button type="button" class="btn btn-sm btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  const CAT_CONFIG = <?= json_encode($cat_config) ?>;
  const CURRENT_USER_ID = <?= (int)$user_id ?>;
  const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;

  /* ── Category tabs in Add Event modal ── */
  function selectCategory(key) {
    document.getElementById('categoryInput').value = key;
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + key).classList.add('active');
  }

  /* ── Show event detail modal ── */
  function showEventDetail(ev) {
    const cfg   = CAT_CONFIG[ev.category] || CAT_CONFIG['event'];
    const modal = new bootstrap.Modal(document.getElementById('eventDetailModal'));

    document.getElementById('detailTag').textContent    = cfg.label;
    document.getElementById('detailTag').style.background = getTagBg(ev.category);
    document.getElementById('detailTag').style.color      = getTagColor(ev.category);
    document.getElementById('detailTitle').textContent  = ev.title;

    // Date line
    const d    = new Date(ev.event_date + 'T00:00:00');
    const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    let dateLine = d.toLocaleDateString('en-US', opts);
    if (ev.event_time) {
      const [hh, mm] = ev.event_time.split(':');
      const t = new Date(); t.setHours(hh, mm);
      dateLine += ' · ' + t.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }
    document.getElementById('detailDateLine').textContent = dateLine;
    document.getElementById('detailDesc').textContent     = ev.description || '';
    document.getElementById('detailCreator').textContent  =
      'Added by ' + ev.first_name + ' ' + ev.last_name;

    // Delete button: show if admin or creator
    const deleteBtn = document.getElementById('modalDeleteBtn');
    if (IS_ADMIN || parseInt(ev.created_by) === CURRENT_USER_ID) {
      deleteBtn.style.display = 'inline-flex';
      document.getElementById('deleteEventId').value = ev.id;
    } else {
      deleteBtn.style.display = 'none';
    }

    modal.show();
  }

  function getTagBg(cat) {
    const m = {event:'#eff6ff',deadline:'#fee2e2',exam:'#ede9fe',activity:'#dcfce7',holiday:'#fef3c7'};
    return m[cat] || '#eff6ff';
  }
  function getTagColor(cat) {
    const m = {event:'#2563eb',deadline:'#ef4444',exam:'#7c3aed',activity:'#16a34a',holiday:'#d97706'};
    return m[cat] || '#2563eb';
  }

  /* ── Sidebar overlay (mobile) ── */
  const overlay = document.getElementById('sidebarOverlay');
  if (overlay) {
    overlay.addEventListener('click', () => {
      document.getElementById('sidebar').classList.remove('show');
      overlay.classList.remove('show');     
    });
  }
  </script>
</body>
</html>