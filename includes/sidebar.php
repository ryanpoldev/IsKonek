<?php
$current_page = basename($_SERVER['PHP_SELF']);

?>
<div class="sidebar d-flex flex-column" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo px-3 pt-3 pb-2">
        <div class="d-flex align-items-center gap-2">
            <div class="logo-icon d-flex align-items-center justify-content-center">
                <span class="fw-bold fs-5">IK</span>
            </div>
            <span class="logo-text fw-medium fs-5">Iskonek</span>
        </div>
        <hr class="sidebar-divider mt-3 mb-0">
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav flex-grow-1 px-2 pt-2">
        <!-- MAIN Section -->
        <p class="nav-section-label">MAIN</p>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php"
                    class="nav-link sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2-fill me-2"></i>
                    Subject Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="announcements.php"
                    class="nav-link sidebar-link <?= $current_page === 'announcements.php' ? 'active' : '' ?>">
                    <i class="bi bi-megaphone-fill me-2"></i>
                    Announcements
                    <span class="badge bg-danger rounded-pill ms-auto"></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="forums.php"
                    class="nav-link sidebar-link <?= $current_page === 'forums.php' ? 'active' : '' ?>">
                    <i class="bi bi-chat-dots-fill me-2"></i>
                    Forums
                    <span class="badge bg-danger rounded-pill ms-auto"></span>
                </a>
            </li>
        </ul>

        <!-- PROJECT Section -->
        <p class="nav-section-label mt-3">PROJECT</p>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="pages/kanban.php"
                    class="nav-link sidebar-link <?= $current_page === 'kanban.php' ? 'active' : '' ?>">
                    <i class="bi bi-kanban-fill me-2"></i>
                    Kanban Board
                    <span class="badge bg-danger rounded-pill ms-auto"></span>
                </a>

            </li>
            <li class="nav-item">
                <a href="calendar.php"
                    class="nav-link sidebar-link <?= $current_page === 'calendar.php' ? 'active' : '' ?>">
                    <i class="bi bi-calendar3 me-2"></i>
                    Campus Calendar
                </a>
            </li>
            <li class="nav-item">
                <a href="repository.php"
                    class="nav-link sidebar-link <?= $current_page === 'repository.php' ? 'active' : '' ?>">
                    <i class="bi bi-folder-fill me-2"></i>
                    File Repository
                </a>
            </li>
        </ul>

        <!-- ADMIN Section -->
        <p class="nav-section-label mt-3">ADMIN</p>
        <ul class="nav flex-column">
            <?php if (strtolower($_SESSION['role'] ?? '') === 'admin' || 'sup_admin'): ?>

                <li class="nav-item">
                    <a href="manage_subjects.php"
                        class="nav-link sidebar-link <?= $current_page === 'manage_subjects.php' ? 'active' : '' ?>">
                        <i class="bi bi-journal-bookmark-fill me-2"></i>
                        Manage Subjects
                    </a>
                </li>

                
                <li class="nav-item">
                    <a href="members.php"
                        class="nav-link sidebar-link <?= $current_page === 'members.php' ? 'active' : '' ?>">
                        <i class="bi bi-people-fill me-2"></i>
                        Member Dictionary
                    </a>
                </li>
            <?php endif; ?>
            <?php if (strtolower($_SESSION['role'] ?? '') === 'sup_admin'): ?>
                <li class="nav-item">
                    <a href="manage_sections.php"
                        class="nav-link sidebar-link <?= $current_page === 'manage_sections.php' ? 'active' : '' ?>">
                        <i class="bi bi-diagram-3-fill me-2"></i>
                        Manage Sections
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_users.php"
                        class="nav-link sidebar-link <?= $current_page === 'manage_users.php' ? 'active' : '' ?>">
                        <i class="bi bi-person-gear me-2"></i>
                        Manage Users
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="settings.php"
                    class="nav-link sidebar-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                    <i class="bi bi-gear-fill me-2"></i>
                    Settings
                </a>
            </li>
        </ul>
    </nav>

    <!-- Account -->
    <div class="sidebar-account px-3 py-3">
        <hr class="sidebar-divider mb-2">
        <div class="d-flex align-items-center gap-2">
            <div class="avatar-circle d-flex align-items-center justify-content-center overflow-hidden">
                <?php if (!empty($_SESSION['avatar'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar"
                        style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <i class="bi bi-person-fill fs-5"></i>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <p class="mb-0 fw-medium text-truncate small" style="font-size:14px;">
                    <?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : '' ?>
                </p>
                <div class="d-flex gap-1 flex-row">
                    <p class="mb-0 text-muted" style="font-size:12px;">
                        <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : '' ?>
                    </p>

                    <p class="mb-0 text-muted" style="font-size:12px;">
                        <?= isset($_SESSION['section_clean']) ? htmlspecialchars($_SESSION['section_clean']) : '' ?>
                    </p>
                </div>
            </div>
            <a href="logout.php" class="text-muted logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</div>