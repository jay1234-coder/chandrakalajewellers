<?php
/**
 * ChandraKala Jewellers — User & Customer Management Portal
 * Allows administrators and superadmins to manage registered shopper accounts
 * and administrative staff credentials.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/customers.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/activity-log.php';

cj_auth_bootstrap($conn);
cj_ensure_customers_schema($conn);
cj_admin_noindex_headers();
cj_require_role(['admin', 'superadmin'], 'admin');

$currentRole = (string)($_SESSION['role'] ?? 'admin');
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$embed = !empty($_GET['embed']) || !empty($_POST['embed']);
$tab = (string)($_GET['tab'] ?? 'customers');
if (!in_array($tab, ['customers', 'staff'], true)) {
    $tab = 'customers';
}

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        die('CSRF token validation failed.');
    }

    $action = (string)($_POST['action'] ?? '');

    // 1. Reset Customer Password
    if ($action === 'reset_customer_password') {
        $cid = (int)($_POST['customer_id'] ?? 0);
        $newPass = trim((string)($_POST['new_password'] ?? ''));
        if ($cid <= 0 || strlen($newPass) < 6) {
            $message = 'Password must be at least 6 characters.';
            $messageType = 'error';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $hash, $cid);
                $stmt->execute();
                $stmt->close();
                cj_log_activity($conn, 'customer_password_reset', 'Reset password for Customer #' . $cid);
                $message = 'Customer password updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to prepare password update statement.';
                $messageType = 'error';
            }
        }
    }

    // 2. Edit Customer Details
    elseif ($action === 'edit_customer') {
        $cid = (int)($_POST['customer_id'] ?? 0);
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $phone = cj_normalize_customer_phone((string)($_POST['phone'] ?? ''));
        $email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 160);

        if ($cid <= 0 || $name === '' || strlen($phone) !== 10) {
            $message = 'Customer name and valid 10-digit mobile number are required.';
            $messageType = 'error';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please provide a valid email address or leave it blank.';
            $messageType = 'error';
        } else {
            // Check for phone collision with another customer
            $check = $conn->prepare('SELECT id FROM customers WHERE phone = ? AND id != ? LIMIT 1');
            $check->bind_param('si', $phone, $cid);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                $message = 'Another customer already has this mobile number.';
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare('UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('sssi', $name, $phone, $email, $cid);
                    $stmt->execute();
                    $stmt->close();
                    cj_log_activity($conn, 'customer_edit', "Updated details for Customer #$cid ($name, $phone)");
                    $message = 'Customer profile updated.';
                    $messageType = 'success';
                }
            }
            $check->close();
        }
    }

    // 3. Delete Customer Account
    elseif ($action === 'delete_customer') {
        $cid = (int)($_POST['customer_id'] ?? 0);
        if ($cid > 0) {
            $stmt = $conn->prepare('DELETE FROM customers WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $cid);
                $stmt->execute();
                $stmt->close();
                cj_log_activity($conn, 'customer_delete', 'Deleted Customer #' . $cid);
                $message = 'Customer account deleted.';
                $messageType = 'success';
            }
        }
    }

    // 4. Staff Management: Add New Admin (SuperAdmin only)
    elseif ($action === 'add_admin') {
        if ($currentRole !== 'superadmin') {
            $message = 'Only Superadmin can add staff members.';
            $messageType = 'error';
        } else {
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $role = (string)($_POST['role'] ?? 'admin');
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || strlen($password) < 6) {
                $message = 'Username and password (min 6 characters) are required.';
                $messageType = 'error';
            } elseif (!in_array($role, ['admin', 'superadmin'], true)) {
                $message = 'Invalid role specified.';
                $messageType = 'error';
            } else {
                $check = $conn->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
                $check->bind_param('s', $username);
                $check->execute();
                if ($check->get_result()->fetch_assoc()) {
                    $message = "Username '$username' already exists.";
                    $messageType = 'error';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('INSERT INTO admin_users (username, display_name, role, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())');
                    if ($stmt) {
                        $stmt->bind_param('ssss', $username, $displayName, $role, $hash);
                        $stmt->execute();
                        $stmt->close();
                        cj_log_activity($conn, 'admin_create', "Created $role account: $username");
                        $message = "Admin user '$username' created successfully.";
                        $messageType = 'success';
                    }
                }
                $check->close();
            }
        }
    }

    // 5. Staff Management: Edit Admin / Reset Password
    elseif ($action === 'edit_admin') {
        if ($currentRole !== 'superadmin') {
            $message = 'Only Superadmin can modify staff accounts.';
            $messageType = 'error';
        } else {
            $aid = (int)($_POST['admin_id'] ?? 0);
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $role = (string)($_POST['role'] ?? 'admin');
            $newPass = trim((string)($_POST['new_password'] ?? ''));

            if ($aid <= 0 || !in_array($role, ['admin', 'superadmin'], true)) {
                $message = 'Invalid staff user ID or role.';
                $messageType = 'error';
            } else {
                if ($newPass !== '') {
                    if (strlen($newPass) < 6) {
                        $message = 'Password must be at least 6 characters.';
                        $messageType = 'error';
                    } else {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare('UPDATE admin_users SET display_name = ?, role = ?, password_hash = ? WHERE id = ?');
                        $stmt->bind_param('sssi', $displayName, $role, $hash, $aid);
                        $stmt->execute();
                        $stmt->close();
                        cj_log_activity($conn, 'admin_update', "Updated admin #$aid with new password and role $role");
                        $message = 'Staff account and password updated.';
                        $messageType = 'success';
                    }
                } else {
                    $stmt = $conn->prepare('UPDATE admin_users SET display_name = ?, role = ? WHERE id = ?');
                    $stmt->bind_param('ssi', $displayName, $role, $aid);
                    $stmt->execute();
                    $stmt->close();
                    cj_log_activity($conn, 'admin_update', "Updated admin #$aid role to $role");
                    $message = 'Staff account details updated.';
                    $messageType = 'success';
                }
            }
        }
    }

    // 6. Staff Management: Delete Admin User
    elseif ($action === 'delete_admin') {
        if ($currentRole !== 'superadmin') {
            $message = 'Only Superadmin can delete staff accounts.';
            $messageType = 'error';
        } else {
            $aid = (int)($_POST['admin_id'] ?? 0);
            if ($aid === $currentUserId) {
                $message = 'You cannot delete your own account.';
                $messageType = 'error';
            } elseif ($aid > 0) {
                $stmt = $conn->prepare('DELETE FROM admin_users WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $aid);
                    $stmt->execute();
                    $stmt->close();
                    cj_log_activity($conn, 'admin_delete', "Deleted admin staff #$aid");
                    $message = 'Staff user removed.';
                    $messageType = 'success';
                }
            }
        }
    }
}

// -------------------------------------------------------------
// Data Queries
// -------------------------------------------------------------

// Customer Overview Statistics
$totalCust = 0;
$totalSpentAll = 0.0;
$totalCustOrders = 0;
$newThisMonth = 0;

$statRes = $conn->query('SELECT COUNT(*) AS c FROM customers');
if ($statRes) {
    $totalCust = (int)($statRes->fetch_assoc()['c'] ?? 0);
}

$statRes2 = $conn->query("
    SELECT COUNT(DISTINCT o.id) AS total_orders,
           COALESCE(SUM(CASE WHEN o.status = 'confirmed' THEN o.grand_total ELSE 0 END), 0) AS total_spent
    FROM orders o
    WHERE o.customer_id > 0 OR o.customer_phone IN (SELECT phone FROM customers)
");
if ($statRes2) {
    $row2 = $statRes2->fetch_assoc();
    $totalCustOrders = (int)($row2['total_orders'] ?? 0);
    $totalSpentAll = (float)($row2['total_spent'] ?? 0);
}

$statRes3 = $conn->query("SELECT COUNT(*) AS c FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($statRes3) {
    $newThisMonth = (int)($statRes3->fetch_assoc()['c'] ?? 0);
}

// Search and Filter for Customers
$search = trim((string)($_GET['search'] ?? ''));
$filter = (string)($_GET['filter'] ?? 'all');
$sort = (string)($_GET['sort'] ?? 'newest');

$whereClauses = ['1=1'];
$bindTypes = '';
$bindParams = [];

if ($search !== '') {
    $cleanPhone = preg_replace('/\D+/', '', $search);
    $searchTerm = '%' . $search . '%';
    if (strlen($cleanPhone) >= 6) {
        $whereClauses[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
        $bindTypes .= 'ssss';
        $bindParams[] = $searchTerm;
        $bindParams[] = '%' . $cleanPhone . '%';
        $bindParams[] = $searchTerm;
        $bindParams[] = '%' . $cleanPhone . '%';
    } else {
        $whereClauses[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $bindTypes .= 'sss';
        $bindParams[] = $searchTerm;
        $bindParams[] = $searchTerm;
        $bindParams[] = $searchTerm;
    }
}

$whereSql = implode(' AND ', $whereClauses);

$havingSql = '';
if ($filter === 'with_orders') {
    $havingSql = 'HAVING total_orders > 0';
} elseif ($filter === 'no_orders') {
    $havingSql = 'HAVING total_orders = 0';
} elseif ($filter === 'high_value') {
    $havingSql = 'HAVING total_spent >= 10000';
}

$orderSql = 'ORDER BY c.id DESC';
if ($sort === 'oldest') {
    $orderSql = 'ORDER BY c.id ASC';
} elseif ($sort === 'most_orders') {
    $orderSql = 'ORDER BY total_orders DESC, c.id DESC';
} elseif ($sort === 'highest_spent') {
    $orderSql = 'ORDER BY total_spent DESC, c.id DESC';
} elseif ($sort === 'name') {
    $orderSql = 'ORDER BY c.name ASC';
}

$custQuery = "
    SELECT c.id, c.name, c.phone, c.email, c.created_at,
           COUNT(DISTINCT o.id) AS total_orders,
           COALESCE(SUM(CASE WHEN o.status = 'confirmed' THEN o.grand_total ELSE 0 END), 0) AS total_spent,
           MAX(o.created_at) AS last_order_date
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.id OR o.customer_phone = c.phone OR o.customer_phone = CONCAT('91', c.phone)
    WHERE $whereSql
    GROUP BY c.id
    $havingSql
    $orderSql
    LIMIT 150
";

$customers = [];
$cstmt = $conn->prepare($custQuery);
if ($cstmt) {
    if (!empty($bindParams)) {
        $cstmt->bind_param($bindTypes, ...$bindParams);
    }
    $cstmt->execute();
    $cres = $cstmt->get_result();
    while ($crow = $cres->fetch_assoc()) {
        $customers[] = $crow;
    }
    $cstmt->close();
}

// Staff Query (Tab 2)
$staffUsers = [];
if ($currentRole === 'superadmin') {
    $sres = $conn->query('SELECT id, username, display_name, role, created_at, last_login_at FROM admin_users ORDER BY id ASC');
    if ($sres) {
        while ($srow = $sres->fetch_assoc()) {
            $staffUsers[] = $srow;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Customer & User Management — ChandraKala Jewellers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <?php include __DIR__ . '/includes/admin-head-theme.php'; ?>
    <style>
        .badge-gold { background: rgba(245, 158, 11, 0.12); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.25); }
        .badge-blue { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.25); }
        .avatar-circle { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; background: linear-gradient(135deg, #d97706, #b45309); font-size: 14px; text-transform: uppercase; }
        .modal-backdrop { background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(2px); }
    </style>
</head>
<body class="admin-body min-h-screen bg-gray-50/60 font-sans text-gray-800">

<?php if (!$embed): ?>
<header class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap justify-between items-center gap-3">
        <div class="flex items-center gap-3">
            <img src="images/logo.png" alt="ChandraKala Jewellers" class="h-10 w-auto">
            <div>
                <h1 class="text-base sm:text-lg font-bold font-serif text-gray-900 leading-tight">ChandraKala Jewellers</h1>
                <p class="text-[11px] text-amber-600 font-semibold tracking-wider uppercase">User & Customer Directory</p>
            </div>
        </div>
        <div class="flex items-center gap-2 sm:gap-3">
            <?php include __DIR__ . '/includes/admin-theme-toggle.php'; ?>
            <a href="index.php" class="text-xs font-semibold text-gray-600 hover:text-amber-600 bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-xl transition-colors">Store Admin</a>
            <a href="orders.php" class="text-xs font-semibold text-gray-600 hover:text-amber-600 bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-xl transition-colors">Orders</a>
            <?php if ($currentRole === 'superadmin'): ?>
                <a href="superadmin.php" class="text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-3 py-2 rounded-xl transition-colors">Control Center</a>
            <?php endif; ?>
            <a href="index.php?logout" class="text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 px-3 py-2 rounded-xl border border-red-100 transition-colors">Log Out</a>
        </div>
    </div>
</header>
<?php endif; ?>

<main class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    <!-- Header & Navigation Tabs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold font-serif text-gray-900 flex items-center gap-2">
                <i class="fas fa-users text-amber-500"></i> Users & Customers
            </h2>
            <p class="text-xs text-gray-500 mt-1">Manage registered shopper accounts, review purchase history, and manage store staff.</p>
        </div>
        <div class="flex items-center gap-2 border border-gray-200 bg-white p-1 rounded-xl shadow-sm">
            <a href="?tab=customers<?= $embed ? '&amp;embed=1' : '' ?>" class="px-4 py-2 text-xs font-bold rounded-lg transition-all <?= $tab === 'customers' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' ?>">
                <i class="fas fa-shopping-bag mr-1.5"></i> Customers (<?= $totalCust ?>)
            </a>
            <?php if ($currentRole === 'superadmin'): ?>
                <a href="?tab=staff<?= $embed ? '&amp;embed=1' : '' ?>" class="px-4 py-2 text-xs font-bold rounded-lg transition-all <?= $tab === 'staff' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' ?>">
                    <i class="fas fa-user-shield mr-1.5"></i> Staff Admins (<?= count($staffUsers) ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Feedback -->
    <?php if ($message !== ''): ?>
        <div class="p-4 rounded-xl mb-6 shadow-sm flex items-center gap-3 <?= $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-red-500' ?> text-lg shrink-0"></i>
            <span class="text-xs sm:text-sm font-semibold"><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'customers'): ?>
        <!-- Statistics Row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Registered</span>
                    <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm"><i class="fas fa-users"></i></span>
                </div>
                <div class="text-2xl font-bold text-gray-900 font-serif"><?= number_format($totalCust) ?></div>
                <div class="text-[11px] text-gray-500 mt-1">Verified shopper profiles</div>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Orders</span>
                    <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm"><i class="fas fa-receipt"></i></span>
                </div>
                <div class="text-2xl font-bold text-gray-900 font-serif"><?= number_format($totalCustOrders) ?></div>
                <div class="text-[11px] text-gray-500 mt-1">Placed by registered users</div>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Revenue</span>
                    <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm"><i class="fas fa-rupee-sign"></i></span>
                </div>
                <div class="text-2xl font-bold text-gray-900 font-serif">₹<?= number_format(round($totalSpentAll)) ?></div>
                <div class="text-[11px] text-gray-500 mt-1">Total confirmed value</div>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">New (30d)</span>
                    <span class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm"><i class="fas fa-user-plus"></i></span>
                </div>
                <div class="text-2xl font-bold text-gray-900 font-serif"><?= number_format($newThisMonth) ?></div>
                <div class="text-[11px] text-gray-500 mt-1">Joined in last month</div>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm mb-6">
            <form method="get" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="customers">
                <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

                <div class="relative flex-1 min-w-[220px]">
                    <i class="fas fa-search absolute left-3.5 top-3 text-gray-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by customer name, 10-digit mobile, or email..." class="w-full pl-9 pr-4 py-2 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>

                <div class="w-full sm:w-auto flex flex-wrap items-center gap-2">
                    <select name="filter" class="text-xs border border-gray-200 rounded-xl px-3 py-2 bg-white text-gray-700 outline-none focus:border-amber-500">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Customers</option>
                        <option value="with_orders" <?= $filter === 'with_orders' ? 'selected' : '' ?>>With Orders</option>
                        <option value="no_orders" <?= $filter === 'no_orders' ? 'selected' : '' ?>>No Orders Yet</option>
                        <option value="high_value" <?= $filter === 'high_value' ? 'selected' : '' ?>>High Value (₹10k+)</option>
                    </select>

                    <select name="sort" class="text-xs border border-gray-200 rounded-xl px-3 py-2 bg-white text-gray-700 outline-none focus:border-amber-500">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="highest_spent" <?= $sort === 'highest_spent' ? 'selected' : '' ?>>Highest Spent</option>
                        <option value="most_orders" <?= $sort === 'most_orders' ? 'selected' : '' ?>>Most Orders</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                    </select>

                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs transition-colors shadow-sm">
                        Filter
                    </button>
                    <?php if ($search !== '' || $filter !== 'all' || $sort !== 'newest'): ?>
                        <a href="?tab=customers<?= $embed ? '&amp;embed=1' : '' ?>" class="text-xs text-gray-500 hover:text-gray-800 px-2 py-2">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Customer List Table -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Shopper Directory (<?= count($customers) ?>)</h3>
                <span class="text-xs text-gray-500">Displaying up to 150 accounts</span>
            </div>

            <?php if (empty($customers)): ?>
                <div class="text-center py-12 px-4">
                    <i class="fas fa-user-slash text-4xl text-gray-300 mb-3"></i>
                    <p class="text-sm font-semibold text-gray-700">No customers found.</p>
                    <p class="text-xs text-gray-400 mt-1">Try adjusting your search criteria or filter options.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-xs">
                        <thead class="bg-gray-50/75 text-gray-500 uppercase font-semibold text-[11px] tracking-wider">
                            <tr>
                                <th scope="col" class="px-6 py-3.5 text-left">Customer</th>
                                <th scope="col" class="px-6 py-3.5 text-left">Contact Info</th>
                                <th scope="col" class="px-6 py-3.5 text-center">Orders</th>
                                <th scope="col" class="px-6 py-3.5 text-right">Total Spent</th>
                                <th scope="col" class="px-6 py-3.5 text-left">Joined</th>
                                <th scope="col" class="px-6 py-3.5 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($customers as $c):
                                $initial = mb_substr($c['name'] ?: 'C', 0, 1);
                                $ordersCount = (int)$c['total_orders'];
                                $spent = (float)$c['total_spent'];
                                $joinDate = date('d M Y', strtotime($c['created_at']));
                                $waUrl = 'https://wa.me/91' . preg_replace('/\D+/', '', $c['phone']);
                            ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-circle shrink-0"><?= htmlspecialchars($initial) ?></div>
                                        <div>
                                            <div class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($c['name']) ?></div>
                                            <div class="text-gray-400 text-[10px]">ID: #<?= (int)$c['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <a href="tel:<?= htmlspecialchars($c['phone']) ?>" class="font-semibold text-gray-900 hover:text-amber-600">
                                            <i class="fas fa-phone-alt text-[10px] text-gray-400 mr-1"></i><?= htmlspecialchars($c['phone']) ?>
                                        </a>
                                        <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="text-emerald-500 hover:text-emerald-600 text-sm" title="Chat on WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    </div>
                                    <?php if (!empty($c['email'])): ?>
                                        <div class="text-gray-500 text-[11px] mt-0.5 truncate max-w-[200px]" title="<?= htmlspecialchars($c['email']) ?>">
                                            <i class="fas fa-envelope text-[10px] text-gray-400 mr-1"></i><?= htmlspecialchars($c['email']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($ordersCount > 0): ?>
                                        <a href="orders.php?status=all&search=<?= urlencode($c['phone']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition-colors">
                                            <i class="fas fa-receipt text-[10px]"></i> <?= $ordersCount ?> <?= $ordersCount === 1 ? 'order' : 'orders' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-gray-900 text-sm">
                                    <?= $spent > 0 ? ('₹' . number_format(round($spent))) : '<span class="text-gray-400 font-normal">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    <?= $joinDate ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" onclick="openEditCustomerModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit Customer Details">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" onclick="openResetPasswordModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this customer account? This cannot be undone.');" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete_customer">
                                            <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                            <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                            <button type="submit" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete Account">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'staff' && $currentRole === 'superadmin'): ?>
        <!-- Staff Management Section -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-bold font-serif text-gray-900">Administrative Staff Accounts</h3>
                <p class="text-xs text-gray-500 mt-0.5">Control staff logins and permissions for the store admin panel.</p>
            </div>
            <button type="button" onclick="openAddAdminModal()" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i class="fas fa-user-plus"></i> Add Staff Member
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-xs">
                    <thead class="bg-gray-50/75 text-gray-500 uppercase font-semibold text-[11px] tracking-wider">
                        <tr>
                            <th scope="col" class="px-6 py-3.5 text-left">Staff Member</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Username</th>
                            <th scope="col" class="px-6 py-3.5 text-center">Role</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Created</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Last Login</th>
                            <th scope="col" class="px-6 py-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($staffUsers as $s):
                            $isSelf = (int)$s['id'] === $currentUserId;
                        ?>
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-user-shield text-gray-400"></i>
                                <?= htmlspecialchars($s['display_name'] ?: $s['username']) ?>
                                <?php if ($isSelf): ?>
                                    <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-600">
                                <?= htmlspecialchars($s['username']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider <?= $s['role'] === 'superadmin' ? 'badge-gold' : 'badge-blue' ?>">
                                    <?= htmlspecialchars($s['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?= date('d M Y', strtotime($s['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?= !empty($s['last_login_at']) ? date('d M Y, h:i A', strtotime($s['last_login_at'])) : '<span class="text-gray-300">Never</span>' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" onclick="openEditAdminModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit Role / Password">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$isSelf): ?>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this staff account?');" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete_admin">
                                            <input type="hidden" name="admin_id" value="<?= (int)$s['id'] ?>">
                                            <input type="hidden" name="tab" value="staff">
                                            <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                            <button type="submit" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete Staff Account">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- Modal: Edit Customer Profile -->
<div id="editCustomerModal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold font-serif text-gray-900">Edit Customer Profile</h3>
            <button type="button" onclick="closeEditCustomerModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" name="customer_id" id="edit_cust_id">
            <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Customer Name</label>
                <input type="text" name="name" id="edit_cust_name" required maxlength="120" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>
            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Mobile Number (10 digits)</label>
                <input type="tel" name="phone" id="edit_cust_phone" required maxlength="15" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Email Address (optional)</label>
                <input type="email" name="email" id="edit_cust_email" maxlength="160" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeEditCustomerModal()" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Customer Password -->
<div id="resetPasswordModal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-gray-100">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold font-serif text-gray-900">Reset Customer Password</h3>
            <button type="button" onclick="closeResetPasswordModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-xs text-gray-500 mb-4">Set a temporary or new password for <strong id="reset_cust_name" class="text-gray-800"></strong>.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="reset_customer_password">
            <input type="hidden" name="customer_id" id="reset_cust_id">
            <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">New Password (min 6 characters)</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Enter new password" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeResetPasswordModal()" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs shadow-sm">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add New Staff Admin -->
<div id="addAdminModal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold font-serif text-gray-900">Add Staff Account</h3>
            <button type="button" onclick="closeAddAdminModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add_admin">
            <input type="hidden" name="tab" value="staff">
            <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Username (login handle)</label>
                <input type="text" name="username" required pattern="[a-zA-Z0-9_\-]+" placeholder="e.g. staff1" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>
            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Display Name</label>
                <input type="text" name="display_name" required placeholder="e.g. Rahul Sharma" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>
            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Role Permission</label>
                <select name="role" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl bg-white outline-none focus:border-amber-500">
                    <option value="admin">Store Admin (inventory, orders, rates)</option>
                    <option value="superadmin">Super Admin (all controls, staff management, integrations)</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Password (min 6 characters)</label>
                <input type="password" name="password" required minlength="6" placeholder="Choose a secure password" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeAddAdminModal()" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs shadow-sm">Create Staff User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Staff Admin -->
<div id="editAdminModal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold font-serif text-gray-900">Edit Staff Account</h3>
            <button type="button" onclick="closeEditAdminModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="edit_admin">
            <input type="hidden" name="admin_id" id="edit_admin_id">
            <input type="hidden" name="tab" value="staff">
            <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Username</label>
                <input type="text" id="edit_admin_username" disabled class="w-full text-xs p-2.5 border border-gray-200 rounded-xl bg-gray-50 text-gray-500">
            </div>
            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Display Name</label>
                <input type="text" name="display_name" id="edit_admin_display_name" required class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>
            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Role Permission</label>
                <select name="role" id="edit_admin_role" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl bg-white outline-none focus:border-amber-500">
                    <option value="admin">Store Admin</option>
                    <option value="superadmin">Super Admin</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Change Password (leave blank to keep current)</label>
                <input type="password" name="new_password" minlength="6" placeholder="Enter new password (optional)" class="w-full text-xs p-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeEditAdminModal()" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCustomerModal(c) {
    document.getElementById('edit_cust_id').value = c.id;
    document.getElementById('edit_cust_name').value = c.name || '';
    document.getElementById('edit_cust_phone').value = c.phone || '';
    document.getElementById('edit_cust_email').value = c.email || '';
    const m = document.getElementById('editCustomerModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeEditCustomerModal() {
    const m = document.getElementById('editCustomerModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function openResetPasswordModal(id, name) {
    document.getElementById('reset_cust_id').value = id;
    document.getElementById('reset_cust_name').textContent = name;
    const m = document.getElementById('resetPasswordModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeResetPasswordModal() {
    const m = document.getElementById('resetPasswordModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function openAddAdminModal() {
    const m = document.getElementById('addAdminModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeAddAdminModal() {
    const m = document.getElementById('addAdminModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function openEditAdminModal(s) {
    document.getElementById('edit_admin_id').value = s.id;
    document.getElementById('edit_admin_username').value = s.username || '';
    document.getElementById('edit_admin_display_name').value = s.display_name || '';
    document.getElementById('edit_admin_role').value = s.role || 'admin';
    const m = document.getElementById('editAdminModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeEditAdminModal() {
    const m = document.getElementById('editAdminModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>

</body>
</html>
