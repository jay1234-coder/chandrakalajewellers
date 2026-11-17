<?php
/**
 * ChandraKala Jewellers — Secret Login Gate
 */
require_once 'config/db.php';
require_once 'includes/auth.php';

cj_auth_bootstrap($conn);
cj_admin_noindex_headers();

// If already logged in, redirect role-wise
if (!empty($_SESSION['loggedin'])) {
    $role = $_SESSION['role'] ?? 'admin';
    if ($role === 'superadmin') {
        header('Location: /admin/superadmin.php');
    } else {
        header('Location: /admin/index.php');
    }
    exit();
}

$login_shake = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['password_submit']) || isset($_POST['admin_password']))) {
    $rateStatus = cj_login_rate_limit_status($conn, 'admin');
    
    if (!$rateStatus['ok']) {
        $error_message = cj_login_rate_limit_message((int) $rateStatus['wait']);
        $login_shake = true;
    } else {
        $username = trim((string) ($_POST['admin_username'] ?? ''));
        $entered_password = trim((string) ($_POST['admin_password'] ?? ''));
        $ok = false;

        if ($username === '' && $entered_password !== '') {
            $username = 'admin';
        }

        if ($username !== '' && $entered_password !== '') {
            $ok = cj_try_user_login($conn, $username, $entered_password);
        } elseif ($entered_password !== '') {
            // Legacy password-only login as admin
            $stmt = $conn->prepare("SELECT id, username, password_hash, role, display_name FROM admin_users WHERE username = 'admin' AND role = 'admin' LIMIT 1");
            if ($stmt) {
                $stmt->execute();
                $adminUser = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($adminUser && password_verify($entered_password, $adminUser['password_hash'])) {
                    $adminUser['role'] = 'admin';
                    cj_finish_login_session($adminUser);
                    $ok = true;
                    cj_log_activity($conn, 'login', 'Password-only login as admin');
                }
            }
        }

        if ($ok) {
            cj_login_rate_limit_clear($conn, 'admin');
            $rememberMe = !empty($_POST['remember_me']);
            if ($rememberMe) {
                cj_issue_remember_token($conn, (int) ($_SESSION['user_id'] ?? 0));
            } else {
                cj_invalidate_remember_tokens($conn, 0);
            }
            if (function_exists('cj_persist_session_cookie')) {
                cj_persist_session_cookie();
            }
            if (function_exists('cj_issue_auth_cookie')) {
                cj_issue_auth_cookie([
                    'id' => (int) ($_SESSION['user_id'] ?? 0),
                    'username' => (string) ($_SESSION['username'] ?? 'admin'),
                    'role' => (string) ($_SESSION['role'] ?? 'admin'),
                    'display_name' => (string) ($_SESSION['display_name'] ?? 'Admin'),
                ]);
            }
            session_write_close();

            // Redirect role-wise
            $role = $_SESSION['role'] ?? 'admin';
            if ($role === 'superadmin') {
                header('Location: /admin/superadmin.php');
            } else {
                header('Location: /admin/index.php');
            }
            exit();
        } else {
            cj_login_rate_limit_hit($conn, 'admin');
            $error_message = 'Incorrect username or password. Please try again.';
            $login_shake = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Control Gate — ChandraKala Jewellers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #faf9f6;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(212, 175, 55, 0.2);
        }
        .text-gold {
            color: #d4af37;
        }
        .bg-gold {
            background-color: #d4af37;
        }
    </style>
</head>
<body class="admin-body flex items-center justify-center min-h-screen relative p-4 sm:p-6 bg-gray-50/50">
    <div class="absolute inset-0 z-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#d4af37 1px, transparent 1px); background-size: 24px 24px;"></div>
    
    <div class="glass-card p-8 rounded-2xl shadow-xl w-full max-w-md z-10 <?= $login_shake ? 'shake' : '' ?>">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold font-serif text-gray-900 tracking-wide">ChandraKala Jewellers</h1>
            <p class="text-xs text-amber-600 font-semibold tracking-widest uppercase mt-1">Control Gate</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="admin-alert bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl mb-6 text-sm flex gap-2 items-start" role="alert">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5" autocomplete="on">
            <input type="hidden" name="password_submit" value="1">
            <div>
                <label for="admin_username" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Username</label>
                <input type="text" id="admin_username" name="admin_username" value="admin" autocomplete="username" class="px-4 py-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none text-base" placeholder="admin">
            </div>
            <div>
                <label for="admin_password" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Password</label>
                <input type="password" id="admin_password" name="admin_password" autocomplete="current-password" class="px-4 py-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200 text-base" required autofocus>
            </div>

            <label for="remember_me" class="flex items-center gap-2.5 cursor-pointer select-none group">
                <input type="checkbox" id="remember_me" name="remember_me" value="1" checked class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500/40 focus:ring-offset-0 accent-amber-600">
                <span class="text-sm text-gray-600 group-hover:text-gray-800">Remember me</span>
            </label>
            
            <button type="submit" id="loginSubmitBtn" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-sm hover:shadow text-sm tracking-wide active:scale-[0.98]">
                Sign In
            </button>
        </form>
    </div>

    <script>
        document.querySelectorAll('.admin-alert').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(function () { alert.remove(); }, 420);
            }, 4500);
        });
    </script>
</body>
</html>
