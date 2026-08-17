<?php
// sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper function to check if user can view a module
function canView($module) {
    // Admin and Dev see everything in the sidebar
    if (isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], ['Administrator', 'Dev'])) {
        return true;
    }
    return hasPermission($module, 'view');
}

// Helper function to check if a section has any visible links
function hasVisibleLinks($links) {
    foreach ($links as $link) {
        if (canView($link)) {
            return true;
        }
    }
    return false;
}

// Helper function to check if user can access checklists
function canAccessChecklists() {
    return hasPermission('Checklist', 'view') || in_array($_SESSION['role_name'] ?? '', ['Administrator', 'Dev']);
}

// NOTE: canAccessSettings() and canAccessRoleManagement() are already defined in database.php
// DO NOT redeclare them here!
?>
<style>
:root {
    --green: #1f8b4c;
    --light-green: #d4edda;
    --sidebar-bg: #ffffff;
    --sidebar-text: #1e2a2f;
    --sidebar-hover: #f1f5f9;
    --sidebar-active: #1f8b4c;
    --sidebar-active-bg: #e6f5ea;
    --sidebar-border: #e5e7eb;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 270px;
    height: 100vh;
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    overflow-y: auto;
    transition: .35s;
    z-index: 999;
    box-shadow: 2px 0 12px rgba(0,0,0,0.06);
    border-right: 1px solid var(--sidebar-border);
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}
.sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
    background: var(--light-green);
    border-radius: 4px;
}

.logo {
    height: 75px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    border-bottom: 1px solid var(--sidebar-border);
    padding: 0 15px;
}

.logo img {
    height: 45px;
    width: auto;
    display: block;
}

.logo h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--sidebar-text);
    margin: 0;
    letter-spacing: -0.5px;
}

.menu {
    margin-top: 16px;
    padding-bottom: 80px;
}

/* Parent menu items with submenu */
.menu .has-submenu {
    position: relative;
}

.menu .has-submenu > a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    color: var(--sidebar-text);
    padding: 13px 24px;
    margin: 2px 12px;
    border-radius: 10px;
    transition: .2s;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
}

.menu .has-submenu > a i:first-child {
    width: 22px;
    font-size: 17px;
    text-align: center;
    color: #64748b;
    transition: .2s;
}

.menu .has-submenu > a .arrow {
    font-size: 12px;
    color: #94a3b8;
    transition: .3s;
    margin-left: auto;
    padding: 0 4px;
}

.menu .has-submenu > a .arrow.open {
    transform: rotate(90deg);
}

.menu .has-submenu > a:hover {
    background: var(--sidebar-hover);
    color: var(--sidebar-text);
}

.menu .has-submenu > a.active {
    background: var(--sidebar-active-bg);
    color: var(--sidebar-active);
    font-weight: 600;
}

.menu .has-submenu > a.active i:first-child {
    color: var(--sidebar-active);
}

/* Submenu */
.menu .submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease;
    margin-left: 8px;
}

.menu .submenu.open {
    max-height: 800px;
}

.menu .submenu a {
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    color: #64748b;
    padding: 10px 24px 10px 50px;
    margin: 1px 12px;
    border-radius: 8px;
    transition: .2s;
    font-size: 14px;
    font-weight: 400;
}

.menu .submenu a i {
    width: 18px;
    font-size: 14px;
    text-align: center;
    color: #94a3b8;
}

.menu .submenu a:hover {
    background: var(--sidebar-hover);
    color: var(--sidebar-text);
}

.menu .submenu a:hover i {
    color: var(--sidebar-text);
}

.menu .submenu a.active {
    background: var(--sidebar-active-bg);
    color: var(--sidebar-active);
    font-weight: 500;
}

.menu .submenu a.active i {
    color: var(--sidebar-active);
}

.menu .submenu .submenu-divider {
    border-top: 1px solid var(--sidebar-border);
    margin: 6px 24px 6px 50px;
    opacity: 0.5;
}

/* Regular menu items */
.menu a:not(.has-submenu > a) {
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    color: var(--sidebar-text);
    padding: 13px 24px;
    margin: 2px 12px;
    border-radius: 10px;
    transition: .2s;
    font-size: 15px;
    font-weight: 500;
}

.menu a:not(.has-submenu > a) i {
    width: 22px;
    font-size: 17px;
    text-align: center;
    color: #64748b;
    transition: .2s;
}

.menu a:not(.has-submenu > a):hover {
    background: var(--sidebar-hover);
    color: var(--sidebar-text);
}

.menu a:not(.has-submenu > a):hover i {
    color: var(--sidebar-text);
}

.menu a:not(.has-submenu > a).active {
    background: var(--sidebar-active-bg);
    color: var(--sidebar-active);
    font-weight: 600;
}

.menu a:not(.has-submenu > a).active i {
    color: var(--sidebar-active);
}

.menu-title {
    color: #94a3b8;
    padding: 20px 28px 8px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 600;
}

.bottom {
    position: absolute;
    bottom: 20px;
    left: 15px;
    right: 15px;
}

.logout {
    color: #dc2626 !important;
    font-weight: 600 !important;
}

.logout i {
    color: #dc2626 !important;
}

.logout:hover {
    background: #fef2f2 !important;
}

.overlay {
    display: none;
}

@media (max-width: 991px) {
    .sidebar {
        left: -270px;
    }
    .sidebar.show {
        left: 0;
    }
    .overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.35);
        opacity: 0;
        visibility: hidden;
        transition: .3s;
        z-index: 998;
    }
    .overlay.show {
        opacity: 1;
        visibility: visible;
    }
}

/* Small screen adjustments */
@media (max-width: 480px) {
    .menu .submenu a {
        padding: 8px 16px 8px 40px;
        font-size: 13px;
    }
    .menu .has-submenu > a {
        padding: 11px 18px;
        font-size: 14px;
    }
}
</style>

<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="logo">
        <img src="logo.png" alt="Enginove">
    </div>

    <div class="menu">
        <?php if (hasVisibleLinks(['Dashboard', 'Tender Management', 'Purchase Orders', 'Procurement'])): ?>
        <div class="menu-title">MAIN</div>
        <?php endif; ?>
        
        <?php if (canView('Dashboard')): ?>
        <a href="index.php" class="<?= ($currentPage == "index.php") ? "active" : ""; ?>">
            <i class="fas fa-house"></i> Dashboard
        </a>
        <?php endif; ?>
        
        <!-- Tenders - Expandable Submenu -->
        <?php if (canView('Tender Management')): ?>
        <div class="has-submenu">
            <a href="tenders.php" class="<?= (in_array($currentPage, ['tenders.php', 'tender_view.php', 'tender_edit.php', 'tender_checklist.php', 'tender_submit.php'])) ? "active" : ""; ?>" onclick="toggleSubmenu(event, 'tendersSubmenu')">
                <i class="fas fa-file-signature"></i> Tenders
                <i class="arrow <?= (in_array($currentPage, ['tenders.php', 'tender_view.php', 'tender_edit.php', 'tender_checklist.php', 'tender_submit.php']) || isset($_GET['tab'])) ? 'open' : ''; ?>" id="tendersArrow">▶</i>
            </a>

            <div class="submenu <?= (in_array($currentPage, ['tenders.php', 'tender_view.php', 'tender_edit.php', 'tender_checklist.php', 'tender_submit.php', 'tender_ongoing.php', 'tender_submitted.php', 'tender_uz_submitted.php', 'tender_supplier_reg.php', 'tender_site_visits.php', 'tender_checklist.php']) || isset($_GET['tab'])) ? 'open' : ''; ?>" id="tendersSubmenu">
    <a href="tender_ongoing.php" class="<?= ($currentPage == 'tender_ongoing.php') ? 'active' : ''; ?>">
        <i class="fas fa-clock"></i> Ongoing
    </a>
    <a href="tender_submitted.php" class="<?= ($currentPage == 'tender_submitted.php') ? 'active' : ''; ?>">
        <i class="fas fa-paper-plane"></i> Submitted
    </a>
    <a href="tender_uz_submitted.php" class="<?= ($currentPage == 'tender_uz_submitted.php') ? 'active' : ''; ?>">
        <i class="fas fa-university"></i> UZ Submitted
    </a>
    <a href="tender_supplier_reg.php" class="<?= ($currentPage == 'tender_supplier_reg.php') ? 'active' : ''; ?>">
        <i class="fas fa-user-plus"></i> Supplier Reg
    </a>
    <a href="tender_site_visits.php" class="<?= ($currentPage == 'tender_site_visits.php') ? 'active' : ''; ?>">
        <i class="fas fa-map-marker-alt"></i> Site Visits
    </a>
    <?php if (canAccessChecklists()): ?>
    <a href="tender_checklists.php" class="<?= ($currentPage == 'tender_checklists.php') ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-check"></i> Checklists
    </a>
    <?php endif; ?>
    <div class="submenu-divider"></div>
    <a href="new_tender.php">
    <i class="fas fa-plus-circle" style="color:var(--green);"></i> New Tender
</a>
</div>


        </div>
        <?php endif; ?>
        
        <?php if (canView('Purchase Orders')): ?>
        <a href="purchase_orders.php" class="<?= ($currentPage == "purchase_orders.php") ? "active" : ""; ?>">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
        </a>
        <?php endif; ?>
        
        <?php if (canView('Procurement')): ?>
        <a href="procurement.php" class="<?= ($currentPage == "procurement.php") ? "active" : ""; ?>">
            <i class="fas fa-boxes"></i> Procurement
        </a>
        <?php endif; ?>

        <?php if (hasVisibleLinks(['Suppliers', 'Artisans'])): ?>
        <div class="menu-title">RESOURCES</div>
        <?php endif; ?>
        
        <?php if (canView('Suppliers')): ?>
        <a href="suppliers.php" class="<?= ($currentPage == "suppliers.php") ? "active" : ""; ?>">
            <i class="fas fa-truck"></i> Suppliers
        </a>
        <?php endif; ?>
        
        <?php if (canView('Artisans')): ?>
        <a href="artisans.php" class="<?= ($currentPage == "artisans.php") ? "active" : ""; ?>">
            <i class="fas fa-screwdriver-wrench"></i> Artisans
        </a>
        <?php endif; ?>

        <?php if (hasVisibleLinks(['Expense Capture', 'Memos', 'Checklist'])): ?>
        <div class="menu-title">FINANCE & ADMIN</div>
        <?php endif; ?>
        
        <?php if (canView('Expense Capture')): ?>
        <a href="expenses.php" class="<?= ($currentPage == "expenses.php") ? "active" : ""; ?>">
            <i class="fas fa-coins"></i> Expense Capture
        </a>
        <?php endif; ?>
        
        <?php if (canView('Memos')): ?>
        <a href="memos.php" class="<?= ($currentPage == "memos.php") ? "active" : ""; ?>">
            <i class="fas fa-envelope"></i> Memos
        </a>
        <?php endif; ?>
        
        <?php if (canView('Checklist')): ?>
        <a href="checklists.php" class="<?= ($currentPage == "checklists.php") ? "active" : ""; ?>">
            <i class="fas fa-clipboard-check"></i> Checklists
        </a>
        <?php endif; ?>

        <?php if (hasVisibleLinks(['Quantity Survey', 'Engineering', 'Stores'])): ?>
        <div class="menu-title">DEPARTMENTS</div>
        <?php endif; ?>
        
        <?php if (canView('Quantity Survey')): ?>
        <a href="quantity_survey.php" class="<?= ($currentPage == "quantity_survey.php") ? "active" : ""; ?>">
            <i class="fas fa-ruler-combined"></i> Quantity Survey
        </a>
        <?php endif; ?>
        
        <?php if (canView('Engineering')): ?>
        <a href="engineering.php" class="<?= ($currentPage == "engineering.php") ? "active" : ""; ?>">
            <i class="fas fa-gears"></i> Engineering
        </a>
        <?php endif; ?>
        
        <?php if (canView('Stores')): ?>
        <a href="stores.php" class="<?= ($currentPage == "stores.php") ? "active" : ""; ?>">
            <i class="fas fa-warehouse"></i> Stores
        </a>
        <?php endif; ?>

        <?php if (hasVisibleLinks(['Todo', 'Reports'])): ?>
        <div class="menu-title">PRODUCTIVITY</div>
        <?php endif; ?>
        
        <?php if (canView('Todo')): ?>
        <a href="todos.php" class="<?= ($currentPage == "todos.php") ? "active" : ""; ?>">
            <i class="fas fa-list-check"></i> Todos
        </a>
        <?php endif; ?>
        
        <?php if (canView('Reports')): ?>
        <a href="reports.php" class="<?= ($currentPage == "reports.php") ? "active" : ""; ?>">
            <i class="fas fa-chart-line"></i> Reports
        </a>
        <?php endif; ?>

        <?php if (hasVisibleLinks(['Users']) || canAccessSettings() || canAccessRoleManagement()): ?>
        <div class="menu-title">SYSTEM</div>
        <?php endif; ?>
        
        <?php if (canView('Users')): ?>
        <a href="users.php" class="<?= ($currentPage == "users.php") ? "active" : ""; ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <?php endif; ?>
        
        <?php if (canAccessRoleManagement()): ?>
        <a href="settings.php?tab=roles" class="<?= ($currentPage == "settings.php" && isset($_GET['tab']) && $_GET['tab'] == 'roles') ? "active" : ""; ?>">
            <i class="fas fa-user-cog"></i> Role Management
        </a>
        <?php endif; ?>
        
        <?php if (canAccessSettings()): ?>
        <a href="settings.php" class="<?= ($currentPage == "settings.php" && (!isset($_GET['tab']) || $_GET['tab'] != 'roles')) ? "active" : ""; ?>">
            <i class="fas fa-gear"></i> Settings
        </a>
        <?php endif; ?>

        <!-- Profile link - visible to everyone -->
        <a href="profile.php" class="<?= ($currentPage == "profile.php") ? "active" : ""; ?>">
            <i class="fas fa-user"></i> My Profile
        </a>

        <a href="logout.php" class="logout">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>

</aside>

<script>
// Toggle submenu function
function toggleSubmenu(event, submenuId) {
    event.preventDefault();
    const submenu = document.getElementById(submenuId);
    const arrow = document.getElementById(submenuId.replace('Submenu', 'Arrow'));
    
    if (submenu) {
        submenu.classList.toggle('open');
        if (arrow) {
            arrow.classList.toggle('open');
        }
    }
}

// Keep submenu open if a subpage is active
document.addEventListener('DOMContentLoaded', function() {
    const activeSubmenu = document.querySelector('.submenu.open');
    if (activeSubmenu) {
        const parent = activeSubmenu.closest('.has-submenu');
        if (parent) {
            const arrow = parent.querySelector('.arrow');
            if (arrow) {
                arrow.classList.add('open');
            }
        }
    }
});

// Function to open tender modal (will be called from sidebar)
function openTenderModal() {
    // Try to find and open the tender modal from tenders.php
    const tenderModal = document.getElementById('tenderModal');
    if (tenderModal) {
        tenderModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    } else {
        // If modal doesn't exist on current page, redirect to tenders.php
        window.location.href = 'tenders.php';
    }
}

// Sidebar toggle functions
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");

function toggleSidebar() {
    sidebar.classList.toggle("show");
    overlay.classList.toggle("show");
}

overlay.onclick = function() {
    sidebar.classList.remove("show");
    overlay.classList.remove("show");
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        sidebar.classList.remove("show");
        overlay.classList.remove("show");
    }
});
</script>