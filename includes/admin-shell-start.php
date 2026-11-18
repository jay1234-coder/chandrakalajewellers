<?php
/**
 * Unified Admin App Shell — Opening Layout
 *
 * Desktop (>= 1024px): Fixed luxury Left Sidebar + Sticky Top Utility Bar + Focused Section Canvas
 * Mobile  (< 1024px) : Sticky Mobile Topbar + Slide-Over Navigation Drawer + Fixed 5-Tab Bottom App Bar
 * Embed   ($cjShellEmbed = true): Clean compact section strip inside Super Admin iframe (no duplicate sidebar)
 *
 * Expected optional variables from caller:
 *   $cjShellPage     (string) : overview | rates | add-item | inventory | catalogue | orders | reels | customers | notifications | follow-us | analytics
 *   $cjShellTitle    (string) : Active page heading
 *   $cjShellSubtitle (string) : Active page short description
 *   $cjShellEmbed    (bool)   : True when rendered inside Super Admin iframe
 *   $cjShellMaxWidth (string) : Optional max-width for inner content (default '1280px')
 */

$cjShellPage     = isset($cjShellPage) ? (string)$cjShellPage : 'overview';
$cjShellTitle    = isset($cjShellTitle) ? (string)$cjShellTitle : 'Admin Overview';
$cjShellSubtitle = isset($cjShellSubtitle) ? (string)$cjShellSubtitle : 'ChandraKala Jewellers Store Studio';
$cjShellEmbed    = !empty($cjShellEmbed);
$cjShellMaxWidth = isset($cjShellMaxWidth) ? (string)$cjShellMaxWidth : '1280px';

$cjShellRole     = (string)($_SESSION['role'] ?? 'admin');
$cjShellUser     = (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin');
$cjShellInitial  = strtoupper(substr(trim($cjShellUser) !== '' ? trim($cjShellUser) : 'A', 0, 1));
$cjShellLogout   = function_exists('cj_admin_home_href') ? cj_admin_home_href(null, true) : '/admin/index.php?logout';

$cjShellPendingOrders = 0;
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $poRes = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'reserved'");
        if ($poRes instanceof mysqli_result) {
            $cjShellPendingOrders = (int)($poRes->fetch_assoc()['c'] ?? 0);
            $poRes->free();
        }
    } catch (Throwable $e) {
        $cjShellPendingOrders = 0;
    }
}

$cjShellIstNow = function_exists('cj_format_ist') ? cj_format_ist(time()) : date('d M Y, h:i A') . ' IST';

$cjShellNavGroups = [
    [
        'label' => 'Dashboard',
        'items' => [
            [
                'id'    => 'overview',
                'label' => 'Overview',
                'href'  => '/admin/index.php?view=overview',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/></svg>',
            ],
        ],
    ],
    [
        'label' => 'Store & Inventory',
        'items' => [
            [
                'id'    => 'rates',
                'label' => 'Live Rates & Formula',
                'href'  => '/admin/index.php?view=rates',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>',
            ],
            [
                'id'    => 'add-item',
                'label' => 'Add Jewellery & OCR',
                'href'  => '/admin/index.php?view=add-item',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
            ],
            [
                'id'    => 'inventory',
                'label' => 'Stock Inventory',
                'href'  => '/admin/index.php?view=inventory',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
            ],
            [
                'id'    => 'catalogue',
                'label' => 'Catalogue Photos',
                'href'  => '/admin/delete_c.php',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
            ],
        ],
    ],
    [
        'label' => 'Sales & Engagement',
        'items' => [
            [
                'id'    => 'orders',
                'label' => 'Orders',
                'href'  => '/admin/orders.php',
                'badge' => $cjShellPendingOrders,
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
            ],
            [
                'id'    => 'reels',
                'label' => 'Video Reels Studio',
                'href'  => '/admin/reels.php',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="2.5" width="19" height="19" rx="5"/><line x1="2.5" y1="8.5" x2="21.5" y2="8.5"/><line x1="8.5" y1="2.5" x2="10.5" y2="8.5"/><line x1="15" y1="2.5" x2="17" y2="8.5"/><polygon points="10.2 12 15.8 15.2 10.2 18.4 10.2 12" fill="currentColor" stroke="none"/></svg>',
            ],
            [
                'id'    => 'customers',
                'label' => 'Customers & Staff',
                'href'  => '/admin/customers.php',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            ],
            [
                'id'    => 'notifications',
                'label' => 'Push Notifications',
                'href'  => '/admin/notifications.php',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
            ],
        ],
    ],
    [
        'label' => 'Growth & Insights',
        'items' => [
            [
                'id'    => 'follow-us',
                'label' => 'Follow Us & QR',
                'href'  => '/admin/index.php?view=follow-us',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3h-1"/><path d="M14 20h7"/></svg>',
            ],
            [
                'id'    => 'analytics',
                'label' => 'Visitor Analytics',
                'href'  => '/admin/index.php?view=analytics',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
            ],
        ],
    ],
];
?>
<style id="cj-admin-shell-css">
    :root {
        --cj-sh-sidebar-w: 264px;
        --cj-sh-bg: #faf9f6;
        --cj-sh-surface: #ffffff;
        --cj-sh-surface-alt: #f8f7f3;
        --cj-sh-border: #ece7da;
        --cj-sh-text: #111827;
        --cj-sh-muted: #6b7280;
        --cj-sh-gold: #b45309;
        --cj-sh-gold-soft: rgba(217, 119, 6, 0.11);
        --cj-sh-gold-ring: rgba(217, 119, 6, 0.25);
        --cj-sh-glass: rgba(255, 255, 255, 0.88);
    }
    html[data-theme="dark"] {
        --cj-sh-bg: #0c0e12;
        --cj-sh-surface: #12161f;
        --cj-sh-surface-alt: #181d28;
        --cj-sh-border: #252c3b;
        --cj-sh-text: #f3f4f6;
        --cj-sh-muted: #9ca3af;
        --cj-sh-gold: #f0d78c;
        --cj-sh-gold-soft: rgba(240, 215, 140, 0.14);
        --cj-sh-gold-ring: rgba(240, 215, 140, 0.34);
        --cj-sh-glass: rgba(18, 22, 31, 0.9);
    }

    body.cj-has-shell {
        background: var(--cj-sh-bg) !important;
        color: var(--cj-sh-text);
        overflow-x: hidden;
    }

    .cj-shell-layout {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* ── Desktop Fixed Left Sidebar ─────────────────────────────────── */
    .cj-shell-sidebar {
        display: none;
    }
    @media (min-width: 1024px) {
        .cj-shell-sidebar {
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--cj-sh-sidebar-w);
            background: var(--cj-sh-surface);
            border-right: 1px solid var(--cj-sh-border);
            z-index: 45;
            box-shadow: 2px 0 24px rgba(15, 23, 42, 0.04);
        }
        .cj-shell-workspace {
            margin-left: var(--cj-sh-sidebar-w);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1;
        }
    }

    .cj-shell-brand {
        padding: 18px 18px 16px;
        border-bottom: 1px solid var(--cj-sh-border);
        display: flex;
        align-items: center;
        gap: 11px;
        text-decoration: none;
    }
    .cj-shell-brand img {
        height: 38px;
        width: auto;
        object-fit: contain;
        flex-shrink: 0;
    }
    .cj-shell-brand-name {
        font-family: 'Playfair Display', Georgia, serif;
        font-weight: 700;
        font-size: 15px;
        line-height: 1.2;
        color: var(--cj-sh-text);
    }
    .cj-shell-brand-tag {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--cj-sh-gold);
        margin-top: 2px;
    }

    .cj-shell-nav-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 14px 12px 18px;
        scrollbar-width: thin;
    }
    .cj-shell-group + .cj-shell-group {
        margin-top: 16px;
    }
    .cj-shell-group-title {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.11em;
        text-transform: uppercase;
        color: var(--cj-sh-muted);
        padding: 0 10px 6px;
    }
    .cj-shell-nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 11px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        color: var(--cj-sh-muted);
        text-decoration: none;
        transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease;
        border: 1px solid transparent;
        margin-bottom: 2px;
    }
    .cj-shell-nav-item:hover {
        background: var(--cj-sh-surface-alt);
        color: var(--cj-sh-text);
    }
    .cj-shell-nav-item.is-active {
        background: var(--cj-sh-gold-soft);
        color: var(--cj-sh-gold);
        border-color: var(--cj-sh-gold-ring);
        font-weight: 700;
    }
    .cj-shell-nav-icon {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .cj-shell-nav-icon svg {
        width: 18px;
        height: 18px;
    }
    .cj-shell-badge {
        margin-left: auto;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        background: #dc2626;
        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .cj-shell-sidebar-foot {
        padding: 12px;
        border-top: 1px solid var(--cj-sh-border);
        background: var(--cj-sh-surface-alt);
    }
    .cj-shell-foot-links {
        display: flex;
        gap: 6px;
        margin-bottom: 10px;
    }
    .cj-shell-foot-link {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 7px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        border: 1px solid var(--cj-sh-border);
        background: var(--cj-sh-surface);
        color: var(--cj-sh-text);
        transition: border-color 0.15s ease, color 0.15s ease;
    }
    .cj-shell-foot-link:hover {
        border-color: var(--cj-sh-gold);
        color: var(--cj-sh-gold);
    }
    .cj-shell-user-row {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 6px 4px 2px;
    }
    .cj-shell-avatar {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, #f59e0b, #b45309);
        color: #ffffff;
        font-weight: 800;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .cj-shell-user-meta {
        min-width: 0;
        flex: 1;
    }
    .cj-shell-user-name {
        font-size: 12px;
        font-weight: 700;
        color: var(--cj-sh-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .cj-shell-user-role {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--cj-sh-muted);
    }
    .cj-shell-logout-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: 1px solid rgba(239, 68, 68, 0.25);
        background: rgba(239, 68, 68, 0.08);
        color: #ef4444;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        flex-shrink: 0;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .cj-shell-logout-icon:hover {
        background: #ef4444;
        color: #ffffff;
    }

    /* ── Desktop Top Utility Bar ────────────────────────────────────── */
    .cj-shell-topbar {
        display: none;
    }
    @media (min-width: 1024px) {
        .cj-shell-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 35;
            min-height: 64px;
            padding: 10px 28px;
            background: var(--cj-sh-glass);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--cj-sh-border);
        }
    }
    .cj-shell-page-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 19px;
        font-weight: 700;
        color: var(--cj-sh-text);
        line-height: 1.2;
        margin: 0;
    }
    .cj-shell-page-sub {
        font-size: 11px;
        font-weight: 500;
        color: var(--cj-sh-muted);
        margin: 2px 0 0;
    }
    .cj-shell-top-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .cj-shell-clock-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: var(--cj-sh-gold-soft);
        border: 1px solid var(--cj-sh-gold-ring);
        color: var(--cj-sh-gold);
        white-space: nowrap;
    }
    .cj-shell-quick-cta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 13px;
        border-radius: 11px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        white-space: nowrap;
        transition: transform 0.15s ease, filter 0.15s ease;
    }
    .cj-shell-quick-cta:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
    }

    /* ── Mobile Top Navbar (< 1024px) ───────────────────────────────── */
    .cj-shell-mob-top {
        position: sticky;
        top: 0;
        z-index: 42;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        height: 60px;
        padding: 0 12px;
        padding-top: env(safe-area-inset-top, 0px);
        background: var(--cj-sh-glass);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--cj-sh-border);
    }
    @media (min-width: 1024px) {
        .cj-shell-mob-top { display: none; }
    }
    .cj-shell-mob-left {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
        flex: 1;
    }
    .cj-shell-icon-btn {
        width: 38px;
        height: 38px;
        border-radius: 11px;
        border: 1px solid var(--cj-sh-border);
        background: var(--cj-sh-surface);
        color: var(--cj-sh-text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        position: relative;
        text-decoration: none;
    }
    .cj-shell-icon-btn svg {
        width: 19px;
        height: 19px;
    }
    .cj-shell-mob-brand {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .cj-shell-mob-brand img {
        height: 28px;
        width: auto;
        flex-shrink: 0;
    }
    .cj-shell-mob-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 15px;
        font-weight: 700;
        color: var(--cj-sh-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.15;
    }
    .cj-shell-mob-sub {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--cj-sh-gold);
    }
    .cj-shell-mob-right {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-shrink: 0;
    }
    .cj-shell-dot-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #dc2626;
        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--cj-sh-surface);
    }

    /* ── Mobile Slide-Over Drawer ───────────────────────────────────── */
    .cj-shell-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(5, 8, 15, 0.58);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 60;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.24s ease;
    }
    .cj-shell-drawer-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }
    .cj-shell-drawer {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: min(84vw, 310px);
        background: var(--cj-sh-surface);
        border-right: 1px solid var(--cj-sh-border);
        z-index: 65;
        display: flex;
        flex-direction: column;
        transform: translateX(-104%);
        transition: transform 0.26s cubic-bezier(0.22, 1, 0.36, 1);
        box-shadow: 12px 0 38px rgba(0, 0, 0, 0.28);
    }
    .cj-shell-drawer.is-open {
        transform: translateX(0);
    }
    @media (min-width: 1024px) {
        .cj-shell-drawer, .cj-shell-drawer-backdrop { display: none !important; }
    }
    .cj-shell-drawer-head {
        padding: 16px 14px 14px;
        border-bottom: 1px solid var(--cj-sh-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    /* ── Main Content Container ─────────────────────────────────────── */
    .cj-shell-main {
        width: 100%;
        max-width: <?= htmlspecialchars($cjShellMaxWidth) ?>;
        margin: 0 auto;
        padding: 16px 14px calc(86px + env(safe-area-inset-bottom, 0px));
        flex: 1;
        min-width: 0;
    }
    @media (min-width: 640px) {
        .cj-shell-main {
            padding: 20px 22px calc(90px + env(safe-area-inset-bottom, 0px));
        }
    }
    @media (min-width: 1024px) {
        .cj-shell-main {
            padding: 24px 28px 36px;
        }
    }

    /* ── Mobile Fixed Bottom App Navbar (< 1024px) ──────────────────── */
    .cj-shell-mob-bottom {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 44;
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        background: var(--cj-sh-glass);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border-top: 1px solid var(--cj-sh-border);
        padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
        box-shadow: 0 -6px 24px rgba(15, 23, 42, 0.08);
    }
    @media (min-width: 1024px) {
        .cj-shell-mob-bottom { display: none; }
    }
    .cj-shell-tab {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        padding: 5px 2px;
        border-radius: 12px;
        color: var(--cj-sh-muted);
        text-decoration: none;
        font-size: 10px;
        font-weight: 700;
        background: transparent;
        border: 0;
        cursor: pointer;
        position: relative;
        font-family: inherit;
        transition: color 0.15s ease, background 0.15s ease;
    }
    .cj-shell-tab svg {
        width: 20px;
        height: 20px;
    }
    .cj-shell-tab.is-active {
        color: var(--cj-sh-gold);
        background: var(--cj-sh-gold-soft);
    }
    .cj-shell-tab-badge {
        position: absolute;
        top: 1px;
        right: calc(50% - 18px);
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 999px;
        background: #dc2626;
        color: #ffffff;
        font-size: 9px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid var(--cj-sh-surface);
    }

    /* ── Embed Mode Strip (inside Super Admin iframe) ───────────────── */
    .cj-shell-embed-bar {
        position: sticky;
        top: 0;
        z-index: 40;
        background: var(--cj-sh-surface);
        border-bottom: 1px solid var(--cj-sh-border);
        padding: 8px 14px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .cj-shell-embed-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .cj-shell-embed-tab {
        padding: 5px 10px;
        border-radius: 9px;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        color: var(--cj-sh-muted);
        border: 1px solid var(--cj-sh-border);
        background: var(--cj-sh-surface-alt);
    }
    .cj-shell-embed-tab.is-active {
        background: var(--cj-sh-gold-soft);
        color: var(--cj-sh-gold);
        border-color: var(--cj-sh-gold-ring);
    }

    /* Hide legacy horizontal jump bar if anything still outputs it */
    .admin-mobile-jump { display: none !important; }
</style>

<?php if ($cjShellEmbed): ?>
    <div class="cj-shell-embed-bar">
        <div class="cj-shell-embed-tabs">
            <?php
            $embedSectionPages = [
                'overview'  => ['Overview', '/admin/index.php?embed=1&view=overview'],
                'rates'     => ['Rates & Formula', '/admin/index.php?embed=1&view=rates'],
                'add-item'  => ['Add Item & OCR', '/admin/index.php?embed=1&view=add-item'],
                'inventory' => ['Stock List', '/admin/index.php?embed=1&view=inventory'],
                'follow-us' => ['Follow Us & QR', '/admin/index.php?embed=1&view=follow-us'],
                'analytics' => ['Analytics', '/admin/index.php?embed=1&view=analytics'],
            ];
            if (isset($embedSectionPages[$cjShellPage])):
                foreach ($embedSectionPages as $epKey => $epMeta): ?>
                    <a href="<?= htmlspecialchars($epMeta[1]) ?>" class="cj-shell-embed-tab <?= $cjShellPage === $epKey ? 'is-active' : '' ?>">
                        <?= htmlspecialchars($epMeta[0]) ?>
                    </a>
                <?php endforeach;
            else: ?>
                <span style="font-size:12px;font-weight:700;color:var(--cj-sh-gold);"><?= htmlspecialchars($cjShellTitle) ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <?php include __DIR__ . '/admin-theme-toggle.php'; ?>
        </div>
    </div>
    <main class="cj-shell-main admin-main" style="padding-bottom:28px;">
<?php else: ?>
<script>document.body.classList.add('cj-has-shell');</script>
<div class="cj-shell-layout">
    <!-- Desktop Fixed Left Sidebar -->
    <aside class="cj-shell-sidebar" aria-label="Admin navigation">
        <a href="/admin/index.php?view=overview" class="cj-shell-brand">
            <img src="/images/logo.png" alt="ChandraKala Jewellers">
            <div style="min-width:0;">
                <div class="cj-shell-brand-name">ChandraKala</div>
                <div class="cj-shell-brand-tag">Admin Studio</div>
            </div>
        </a>

        <nav class="cj-shell-nav-scroll">
            <?php foreach ($cjShellNavGroups as $group): ?>
                <div class="cj-shell-group">
                    <div class="cj-shell-group-title"><?= htmlspecialchars($group['label']) ?></div>
                    <?php foreach ($group['items'] as $item):
                        $isActive = ($cjShellPage === $item['id']);
                        $badgeVal = (int)($item['badge'] ?? 0);
                    ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="cj-shell-nav-item <?= $isActive ? 'is-active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <span class="cj-shell-nav-icon"><?= $item['icon'] ?></span>
                            <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($item['label']) ?></span>
                            <?php if ($badgeVal > 0): ?>
                                <span class="cj-shell-badge"><?= $badgeVal ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="cj-shell-sidebar-foot">
            <div class="cj-shell-foot-links">
                <?php if ($cjShellRole === 'superadmin'): ?>
                    <a href="/admin/superadmin.php" class="cj-shell-foot-link" title="Super Admin Control Center">
                        <span>Control Center</span>
                    </a>
                <?php endif; ?>
                <a href="/" target="_blank" rel="noopener" class="cj-shell-foot-link" title="Open live website">
                    <span>Live Site</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
            </div>
            <div class="cj-shell-user-row">
                <div class="cj-shell-avatar"><?= htmlspecialchars($cjShellInitial) ?></div>
                <div class="cj-shell-user-meta">
                    <div class="cj-shell-user-name"><?= htmlspecialchars($cjShellUser) ?></div>
                    <div class="cj-shell-user-role"><?= htmlspecialchars($cjShellRole) ?></div>
                </div>
                <a href="<?= htmlspecialchars($cjShellLogout) ?>" class="cj-shell-logout-icon" title="Log Out" aria-label="Log Out">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Mobile Slide-Over Navigation Drawer -->
    <div id="cjShellBackdrop" class="cj-shell-drawer-backdrop" aria-hidden="true"></div>
    <aside id="cjShellDrawer" class="cj-shell-drawer" aria-label="Mobile admin menu" aria-hidden="true">
        <div class="cj-shell-drawer-head">
            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <img src="/images/logo.png" alt="ChandraKala" style="height:34px;width:auto;">
                <div style="min-width:0;">
                    <div class="cj-shell-brand-name">ChandraKala</div>
                    <div class="cj-shell-brand-tag">Admin Studio</div>
                </div>
            </div>
            <button type="button" id="cjShellDrawerClose" class="cj-shell-icon-btn" aria-label="Close navigation menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div style="padding:10px 12px 6px;border-bottom:1px solid var(--cj-sh-border);display:flex;flex-direction:column;gap:8px;">
            <div class="cj-theme-seg admin-theme-seg" role="group" aria-label="Colour theme" style="width:100%;">
                <button type="button" data-theme-opt="light" aria-pressed="false">Light</button>
                <button type="button" data-theme-opt="dark" aria-pressed="false">Dark</button>
                <button type="button" data-theme-opt="system" aria-pressed="false">Auto</button>
            </div>
            <div class="cj-shell-clock-pill" style="justify-content:center;font-size:10.5px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span data-cj-shell-clock><?= htmlspecialchars($cjShellIstNow) ?></span>
            </div>
        </div>

        <nav class="cj-shell-nav-scroll">
            <?php foreach ($cjShellNavGroups as $group): ?>
                <div class="cj-shell-group">
                    <div class="cj-shell-group-title"><?= htmlspecialchars($group['label']) ?></div>
                    <?php foreach ($group['items'] as $item):
                        $isActive = ($cjShellPage === $item['id']);
                        $badgeVal = (int)($item['badge'] ?? 0);
                    ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="cj-shell-nav-item <?= $isActive ? 'is-active' : '' ?>">
                            <span class="cj-shell-nav-icon"><?= $item['icon'] ?></span>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                            <?php if ($badgeVal > 0): ?>
                                <span class="cj-shell-badge"><?= $badgeVal ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="cj-shell-sidebar-foot">
            <div class="cj-shell-foot-links">
                <?php if ($cjShellRole === 'superadmin'): ?>
                    <a href="/admin/superadmin.php" class="cj-shell-foot-link">Control Center</a>
                <?php endif; ?>
                <a href="/" target="_blank" rel="noopener" class="cj-shell-foot-link">Live Site &nearr;</a>
            </div>
            <div class="cj-shell-user-row">
                <div class="cj-shell-avatar"><?= htmlspecialchars($cjShellInitial) ?></div>
                <div class="cj-shell-user-meta">
                    <div class="cj-shell-user-name"><?= htmlspecialchars($cjShellUser) ?></div>
                    <div class="cj-shell-user-role"><?= htmlspecialchars($cjShellRole) ?></div>
                </div>
                <a href="<?= htmlspecialchars($cjShellLogout) ?>" class="cj-shell-logout-icon" title="Log Out" aria-label="Log Out">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Right Workspace (Desktop Topbar + Mobile Topbar + Page Canvas) -->
    <div class="cj-shell-workspace">
        <!-- Mobile Top Navbar -->
        <header class="cj-shell-mob-top">
            <div class="cj-shell-mob-left">
                <button type="button" id="cjShellDrawerOpen" class="cj-shell-icon-btn" aria-label="Open navigation menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                </button>
                <div class="cj-shell-mob-brand">
                    <img src="/images/logo.png" alt="ChandraKala">
                    <div style="min-width:0;">
                        <div class="cj-shell-mob-title"><?= htmlspecialchars($cjShellTitle) ?></div>
                        <div class="cj-shell-mob-sub">ChandraKala Admin</div>
                    </div>
                </div>
            </div>
            <div class="cj-shell-mob-right">
                <button type="button" id="cjShellMobThemeBtn" class="cj-shell-icon-btn" title="Switch Light / Dark theme" aria-label="Switch colour theme">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                </button>
                <a href="/admin/orders.php" class="cj-shell-icon-btn" aria-label="View orders">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <?php if ($cjShellPendingOrders > 0): ?>
                        <span class="cj-shell-dot-badge"><?= $cjShellPendingOrders ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- Desktop Top Utility Bar -->
        <header class="cj-shell-topbar">
            <div style="min-width:0;">
                <h1 class="cj-shell-page-title"><?= htmlspecialchars($cjShellTitle) ?></h1>
                <p class="cj-shell-page-sub"><?= htmlspecialchars($cjShellSubtitle) ?></p>
            </div>
            <div class="cj-shell-top-actions">
                <span class="cj-shell-clock-pill">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span data-cj-shell-clock><?= htmlspecialchars($cjShellIstNow) ?></span>
                </span>
                <?php include __DIR__ . '/admin-theme-toggle.php'; ?>
                <?php if ($cjShellPage !== 'add-item'): ?>
                    <a href="/admin/index.php?view=add-item" class="cj-shell-quick-cta">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Add Item</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Page Content Canvas -->
        <main class="cj-shell-main admin-main">
<?php endif; ?>
