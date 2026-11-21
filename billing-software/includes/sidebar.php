<?php
/**
 * includes/sidebar.php
 * * Shared luxury sidebar configuration with dynamic brand logo mapping.
 */

// Determine depth-aware base path automatically so links always resolve correctly
$current_file = $_SERVER['SCRIPT_FILENAME'];
$root         = realpath(__DIR__ . '/..');
$rel          = str_replace('\\', '/', substr($current_file, strlen($root)));
$depth        = substr_count(trim($rel, '/'), '/');
$base         = $depth > 0 ? '../' : '';

$active_page  = $active_page ?? '';

$uri = $_SERVER['REQUEST_URI'] ?? '';
$is_cj_subpath = (strpos($uri, '/CJ/') === 0);
$store_admin_url = $is_cj_subpath ? '/CJ/admin/index.php' : '/admin/index.php';
$store_site_url  = $is_cj_subpath ? '/CJ/index1.php' : '/';

$nav = [
    ['key' => 'dashboard',      'icon' => 'fa-chart-line',    'label' => 'Dashboard',          'href' => $base . 'dashboard.php'],
    ['key' => 'sale_bill',      'icon' => 'fa-file-invoice',  'label' => 'Sale Bill',          'href' => $base . 'billing/sale_bill.php'],
    ['key' => 'bill_history',   'icon' => 'fa-history',       'label' => 'Bill History',       'href' => $base . 'billing/bill_history.php'],
    ['key' => 'labour_bill',    'icon' => 'fa-file',          'label' => 'Labour Bill',        'href' => $base . 'billing/labour_bill.php'],
    ['key' => 'customers',      'icon' => 'fa-users',         'label' => 'Customers',          'href' => $base . 'customers/customers.php'],
    ['key' => 'reports',        'icon' => 'fa-chart-bar',     'label' => 'Reports',            'href' => $base . 'reports/daily_report.php'],
    ['key' => 'generate_qr',    'icon' => 'fa-qrcode',        'label' => 'Chandrakala QR',     'href' => $base . 'generate_qr.php'],
    ['key' => 'generate_qr_1',  'icon' => 'fa-qrcode',        'label' => 'Hasmukh Hiralal QR', 'href' => $base . 'generate_qr_1.php'],
    ['key' => 'print_qr',       'icon' => 'fa-print',         'label' => 'Print QR Codes',     'href' => $base . 'print_qr.php'],
    ['key' => 'settings',       'icon' => 'fa-cogs',          'label' => 'System Settings',    'href' => $base . 'settings.php'],
    ['key' => 'store_admin',    'icon' => 'fa-shield-halved', 'label' => 'Store Admin Panel',  'href' => $store_admin_url, 'target' => '_blank'],
    ['key' => 'store_website',  'icon' => 'fa-store',         'label' => 'ChandraKala Showroom','href' => $store_site_url,  'target' => '_blank'],
];
?>

<style>
    aside.sidebar {
        width: 280px;
        min-width: 280px;
        background: #0f1115;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(212, 175, 55, 0.15);
        box-shadow: 4px 0 25px rgba(0, 0, 0, 0.3);
        z-index: 100;
        box-sizing: border-box;
    }

    aside.sidebar .logo {
        padding: 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        background: linear-gradient(135deg, rgba(150, 0, 24, 0.15) 0%, transparent 100%);
    }

    aside.sidebar .logo h2 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* Modern constraints for the brand logo image alignment */
    aside.sidebar .logo-img {
        width: 32px;
        height: 32px;
        object-fit: contain;
        border-radius: 4px;
        border: 1px solid rgba(212, 175, 55, 0.3);
        background: #fff;
        padding: 2px;
    }

    aside.sidebar ul {
        list-style: none;
        padding: 16px 12px;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    aside.sidebar ul li {
        width: 100%;
    }

    aside.sidebar ul li a {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        color: #94a3b8;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        border-radius: 8px;
        position: relative;
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Slick Background Reveal Animation Layer */
    aside.sidebar ul li a::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, rgba(150, 0, 24, 0.2) 0%, transparent 100%);
        opacity: 0;
        transform: translateX(-15px);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1;
    }

    aside.sidebar ul li a i {
        font-size: 16px;
        width: 20px;
        text-align: center;
        transition: transform 0.25s ease, color 0.25s ease;
        z-index: 2;
    }

    /* Hover States Micro-interactions */
    aside.sidebar ul li a:hover {
        color: #f8fafc;
    }

    aside.sidebar ul li a:hover::before {
        opacity: 1;
        transform: translateX(0);
    }

    aside.sidebar ul li a:hover i {
        transform: scale(1.15);
        color: #d4af37;
    }

    /* Active Page Route Glow Matrix */
    aside.sidebar ul li a.active {
        color: #f8fafc;
        background: linear-gradient(90deg, #960018 0%, rgba(150, 0, 24, 0.4) 100%);
        box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.1), 0 4px 12px rgba(150, 0, 24, 0.25);
    }

    aside.sidebar ul li a.active i {
        color: #d4af37;
    }

    /* Active Left Border Notch Indicator */
    aside.sidebar ul li a.active::after {
        content: '';
        position: absolute;
        left: 0;
        top: 25%;
        height: 50%;
        width: 4px;
        background: #d4af37;
        border-radius: 0 4px 4px 0;
        box-shadow: 0 0 8px #d4af37;
    }

    /* Hardware Print Isolation Safety Guard */
    @media print {
        aside.sidebar {
            display: none !important;
        }
    }
</style>

<aside class="sidebar">
    <div class="logo">
        <h2>
            <img src="<?= $base; ?>assets/logo.png" alt="Chandrakala Logo" class="logo-img">
            <span>Chandrakala Jewellers</span>
        </h2>
    </div>
    <ul>
        <?php foreach ($nav as $item): 
            $targetAttr = !empty($item['target']) ? ' target="' . htmlspecialchars($item['target']) . '" rel="noopener"' : '';
        ?>
            <li>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="<?= $active_page === $item['key'] ? 'active' : '' ?>"<?= $targetAttr ?>>
                    <i class="fas <?= $item['icon'] ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>