<?php
// header.php - Enginove Central Management System
if (!isset($_SESSION)) {
    session_start();
}

// Get current user data if logged in
$user_name = $_SESSION['username'] ?? 'Guest';
$user_role = $_SESSION['role_name'] ?? 'User';
$user_first = $_SESSION['first_name'] ?? '';
$user_last = $_SESSION['last_name'] ?? '';
$display_name = $user_first && $user_last ? "$user_first $user_last" : $user_name;

// Get notification count (including checklist review notifications)
$notif_count = 0;
$notifications = [];
if (isset($_SESSION['user_id'])) {
    try {
        global $pdo;
        if (isset($pdo)) {
            // Get unread notifications count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $notif_count = $stmt->fetchColumn();
            
            // Get recent notifications
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC LIMIT 10
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $notif_count = 0;
        $notifications = [];
    }
}

// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    } catch (Exception $e) {
        // ignore
    }
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // ignore
    }
}

// Get user role for display
$role_display = '';
switch ($user_role) {
    case 'Administrator':
        $role_display = 'ADM';
        break;
    case 'Dev':
        $role_display = 'DEV';
        break;
    case 'Tender Manager':
        $role_display = 'TM';
        break;
    case 'Quantity Surveyor':
        $role_display = 'QS';
        break;
    case 'Engineer':
        $role_display = 'ENG';
        break;
    case 'Stores':
        $role_display = 'STR';
        break;
    case 'IT':
        $role_display = 'IT';
        break;
    default:
        $role_display = strtoupper(substr($user_role, 0, 3));
        break;
}
?>

<!-- Enginove Topbar – no hamburger on large screens -->
<style>
  /* === TOPBAR – clean, construction theme === */
  .topbar {
    position: sticky;
    top: 0;
    height: 75px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    border-bottom: 1px solid #e5e7eb;
    z-index: 100;
    box-shadow: 0 2px 15px rgba(0, 0, 0, .04);
  }
  .top-left {
    display: flex;
    align-items: center;
    gap: 20px;
  }
  /* Hamburger hidden on large screens by default */
  .menu-toggle {
    display: none;
    width: 45px;
    height: 45px;
    border: none;
    background: var(--green, #1f8b4c);
    color: white;
    border-radius: 10px;
    cursor: pointer;
    font-size: 18px;
    transition: .25s;
    align-items: center;
    justify-content: center;
  }
  .menu-toggle:hover {
    background: #0f6a36;
  }
  .search-box {
    position: relative;
  }
  .search-box input {
    width: 320px;
    height: 45px;
    border: 1px solid #d1d5db;
    border-radius: 30px;
    padding-left: 45px;
    padding-right: 20px;
    outline: none;
    font-size: 14px;
    transition: .25s;
    font-family: inherit;
  }
  .search-box input:focus {
    border-color: var(--green, #1f8b4c);
    box-shadow: 0 0 0 3px rgba(31, 139, 76, 0.12);
  }
  .search-box i {
    position: absolute;
    left: 18px;
    top: 15px;
    color: #64748b;
  }
  .top-right {
    display: flex;
    align-items: center;
    gap: 20px;
  }
  .icon-btn {
    position: relative;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #f8fafc;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    transition: .25s;
    border: none;
    text-decoration: none;
  }
  .icon-btn:hover {
    background: #e6f5ea;
  }
  .icon-btn i {
    font-size: 18px;
    color: #1e2a2f;
  }
  .badge {
    position: absolute;
    top: 5px;
    right: 4px;
    min-width: 18px;
    height: 18px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 11px;
    font-weight: bold;
  }
  .notification-dropdown {
    position: absolute;
    right: 0;
    top: 55px;
    width: 380px;
    max-height: 400px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, .15);
    display: none;
    overflow: hidden;
    border: 1px solid #e2f0e6;
    z-index: 1000;
  }
  .notification-dropdown.show {
    display: block;
  }
  .notification-dropdown .header {
    padding: 14px 18px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
  }
  .notification-dropdown .header h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
  }
  .notification-dropdown .header a {
    font-size: 12px;
    color: var(--green);
    text-decoration: none;
  }
  .notification-dropdown .header a:hover {
    text-decoration: underline;
  }
  .notification-list {
    max-height: 320px;
    overflow-y: auto;
  }
  .notification-item {
    padding: 12px 18px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: .2s;
    text-decoration: none;
    display: block;
  }
  .notification-item:hover {
    background: #f8fafc;
  }
  .notification-item.unread {
    background: #f0fdf4;
    border-left: 3px solid var(--green);
  }
  .notification-item .title {
    font-weight: 600;
    font-size: 13px;
    color: var(--dark);
  }
  .notification-item .message {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .notification-item .time {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 4px;
  }
  .notification-item .type-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 600;
    margin-top: 4px;
  }
  .type-badge.review { background: #dbeafe; color: #1d4ed8; }
  .type-badge.submitted { background: #fef3c7; color: #b45309; }
  .type-badge.approved { background: #d4edda; color: #0f5a2e; }
  .type-badge.rejected { background: #fee2e2; color: #b91c1c; }
  
  .no-notifications {
    padding: 30px 20px;
    text-align: center;
    color: #94a3b8;
  }
  .no-notifications i {
    font-size: 32px;
    display: block;
    margin-bottom: 8px;
  }
  
  .profile {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    position: relative;
  }
  .profile img {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: 2px solid var(--green, #1f8b4c);
    object-fit: cover;
  }
  .profile-name {
    line-height: 1.2;
  }
  .profile-name h4 {
    font-size: 15px;
    font-weight: 600;
    color: #1e2a2f;
    margin: 0;
  }
  .profile-name span {
    font-size: 12px;
    color: #64748b;
  }
  .profile-name span .role-badge {
    background: var(--green, #1f8b4c);
    color: white;
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 10px;
    margin-left: 6px;
  }
  .dropdown {
    position: absolute;
    right: 0;
    top: 65px;
    width: 220px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
    display: none;
    overflow: hidden;
    border: 1px solid #e2f0e6;
    z-index: 1000;
  }
  .dropdown a {
    display: block;
    padding: 14px 18px;
    text-decoration: none;
    color: #1e2a2f;
    transition: .2s;
    font-size: 14px;
  }
  .dropdown a i {
    width: 20px;
    margin-right: 10px;
    color: #64748b;
  }
  .dropdown a:hover {
    background: #f4f9f6;
    color: var(--green, #1f8b4c);
  }
  .dropdown .divider {
    border-top: 1px solid #e5e7eb;
    margin: 4px 0;
  }
  .dropdown.show {
    display: block;
  }

  /* === MEDIA QUERIES === */
  @media (max-width: 991px) {
    .search-box {
      display: none;
    }
    .menu-toggle {
      display: flex !important;
    }
    .notification-dropdown {
      width: 320px;
      right: -20px;
    }
  }

  @media (max-width: 600px) {
    .profile-name {
      display: none;
    }
    .topbar {
      padding: 0 15px;
    }
    .top-right {
      gap: 10px;
    }
    .icon-btn {
      width: 40px;
      height: 40px;
    }
    .icon-btn i {
      font-size: 16px;
    }
    .profile img {
      width: 38px;
      height: 38px;
    }
    .dropdown {
      right: -10px;
      width: 200px;
    }
    .notification-dropdown {
      width: 290px;
      right: -30px;
    }
  }

  @media (max-width: 400px) {
    .top-right {
      gap: 6px;
    }
    .icon-btn {
      width: 36px;
      height: 36px;
    }
    .icon-btn i {
      font-size: 14px;
    }
    .badge {
      min-width: 14px;
      height: 14px;
      font-size: 9px;
      top: 3px;
      right: 2px;
    }
    .profile img {
      width: 32px;
      height: 32px;
    }
    .notification-dropdown {
      width: 260px;
      right: -40px;
    }
  }
</style>

<header class="topbar">
  <div class="top-left">
    <!-- Hamburger – hidden on large screens, visible on tablet/mobile -->
    <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
      <i class="fa-solid fa-bars"></i>
    </button>
    <div class="search-box">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="globalSearch" placeholder="Search tenders, suppliers, users..." onkeyup="globalSearch(this.value)">
    </div>
  </div>
  <div class="top-right">
    <!-- Notifications -->
    <div class="icon-btn" onclick="toggleNotifications(event)" title="Notifications" style="position:relative;">
      <i class="fa-regular fa-bell"></i>
      <?php if ($notif_count > 0): ?>
        <span class="badge"><?= $notif_count ?></span>
      <?php endif; ?>
      <div class="notification-dropdown" id="notificationDropdown">
        <div class="header">
          <h4><i class="fa-regular fa-bell"></i> Notifications</h4>
          <?php if ($notif_count > 0): ?>
            <a href="?mark_all_read=1">Mark all read</a>
          <?php endif; ?>
        </div>
        <div class="notification-list">
          <?php if (empty($notifications)): ?>
            <div class="no-notifications">
              <i class="fa-regular fa-bell-slash"></i>
              <p>No notifications</p>
            </div>
          <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
              <a href="<?= htmlspecialchars($notif['link'] ?? '#') ?>" 
                 class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>"
                 onclick="markNotificationRead(<?= $notif['id'] ?>)">
                <div class="title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="message"><?= nl2br(htmlspecialchars(substr($notif['message'], 0, 100))) ?></div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                  <span class="type-badge <?= strtolower(str_replace(' ', '', $notif['type'])) ?>">
                    <?= ucwords(str_replace('_', ' ', $notif['type'])) ?>
                  </span>
                  <span class="time"><?= date('d M Y H:i', strtotime($notif['created_at'])) ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <a href="todos.php" class="icon-btn" title="Tasks">
      <i class="fa-regular fa-envelope"></i>
    </a>
    <button class="icon-btn" onclick="toggleTheme()" title="Toggle theme">
      <i class="fa-solid fa-moon"></i>
    </button>
    <div class="profile" onclick="toggleProfileMenu()">
      <img src="https://ui-avatars.com/api/?name=<?= urlencode($display_name) ?>&background=1f8b4c&color=fff&size=46" alt="Profile">
      <div class="profile-name">
        <h4><?= htmlspecialchars($display_name) ?></h4>
        <span>
          <?= htmlspecialchars($user_role) ?>
          <span class="role-badge"><?= $role_display ?></span>
        </span>
      </div>
      <i class="fa-solid fa-angle-down"></i>
      <div class="dropdown" id="profileMenu">
        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
        <a href="change_password.php"><i class="fa-solid fa-key"></i> Change Password</a>
        <div class="divider"></div>
        <a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
        <div class="divider"></div>
        <a href="logout.php" style="color:#dc2626;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<script>
  // Toggle notification dropdown
  function toggleNotifications(event) {
    event.stopPropagation();
    const dropdown = document.getElementById("notificationDropdown");
    dropdown.classList.toggle("show");
  }
  
  // Mark notification as read
  function markNotificationRead(id) {
    // AJAX call to mark as read
    fetch('?mark_read=1&id=' + id, { method: 'GET' })
      .then(() => {
        // Reload page to update badge count
        location.reload();
      });
    return true;
  }

  // Toggle profile dropdown
  function toggleProfileMenu() {
    event.stopPropagation();
    document.getElementById("profileMenu").classList.toggle("show");
  }

  // Close dropdowns when clicking outside
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.profile')) {
      let menu = document.getElementById("profileMenu");
      if (menu) {
        menu.classList.remove("show");
      }
    }
    if (!e.target.closest('.icon-btn')) {
      let notif = document.getElementById("notificationDropdown");
      if (notif) {
        notif.classList.remove("show");
      }
    }
  });

  // Close dropdowns on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      let menu = document.getElementById("profileMenu");
      if (menu) {
        menu.classList.remove("show");
      }
      let notif = document.getElementById("notificationDropdown");
      if (notif) {
        notif.classList.remove("show");
      }
    }
  });

  // Global search function
  function globalSearch(query) {
    if (query.length > 2) {
      console.log('Searching for:', query);
    }
  }

  // Theme toggle
  function toggleTheme() {
    document.body.classList.toggle('dark-mode');
    const icon = document.querySelector('.icon-btn .fa-moon, .icon-btn .fa-sun');
    if (icon) {
      icon.classList.toggle('fa-moon');
      icon.classList.toggle('fa-sun');
    }
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
  }

  // Load theme preference
  document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('darkMode') === 'true') {
      document.body.classList.add('dark-mode');
      const icon = document.querySelector('.icon-btn .fa-moon');
      if (icon) {
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
      }
    }
  });

  // Handle sidebar toggle
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) {
      sidebar.classList.toggle('show');
    }
    if (overlay) {
      overlay.classList.toggle('show');
    }
  }

  // Close sidebar when clicking overlay
  document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('overlay');
    if (overlay) {
      overlay.addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
          sidebar.classList.remove('show');
        }
        overlay.classList.remove('show');
      });
    }
  });
</script>

<!-- Dark mode styles -->
<style>
  body.dark-mode {
    background: #1a1a2e;
  }
  body.dark-mode .topbar {
    background: #16213e;
    border-bottom-color: #2a3a5e;
  }
  body.dark-mode .topbar .profile-name h4,
  body.dark-mode .topbar .profile-name span {
    color: #e2e8f0;
  }
  body.dark-mode .topbar .icon-btn {
    background: #1e2a4a;
  }
  body.dark-mode .topbar .icon-btn i {
    color: #94a3b8;
  }
  body.dark-mode .topbar .icon-btn:hover {
    background: #2a3a5e;
  }
  body.dark-mode .topbar .search-box input {
    background: #1e2a4a;
    border-color: #2a3a5e;
    color: #e2e8f0;
  }
  body.dark-mode .topbar .search-box input::placeholder {
    color: #64748b;
  }
  body.dark-mode .topbar .dropdown {
    background: #16213e;
    border-color: #2a3a5e;
  }
  body.dark-mode .topbar .dropdown a {
    color: #e2e8f0;
  }
  body.dark-mode .topbar .dropdown a:hover {
    background: #1e2a4a;
  }
  body.dark-mode .topbar .dropdown .divider {
    border-color: #2a3a5e;
  }
  body.dark-mode .topbar .notification-dropdown {
    background: #16213e;
    border-color: #2a3a5e;
  }
  body.dark-mode .topbar .notification-dropdown .header {
    background: #1a2744;
    border-color: #2a3a5e;
  }
  body.dark-mode .topbar .notification-dropdown .header h4 {
    color: #e2e8f0;
  }
  body.dark-mode .topbar .notification-item {
    border-color: #2a3a5e;
  }
  body.dark-mode .topbar .notification-item:hover {
    background: #1a2744;
  }
  body.dark-mode .topbar .notification-item .title {
    color: #e2e8f0;
  }
  body.dark-mode .topbar .notification-item .message {
    color: #94a3b8;
  }
  body.dark-mode .topbar .notification-item.unread {
    background: #1a2a3a;
    border-left-color: var(--green);
  }
  body.dark-mode .topbar .no-notifications {
    color: #94a3b8;
  }
</style>