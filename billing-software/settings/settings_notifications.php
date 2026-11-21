<?php
include '../includes/db.php';

$message = '';

$result = $conn->query("
    SELECT *
    FROM settings_notifications
    WHERE id = 1
");

if($result->num_rows == 0){

    $conn->query("
        INSERT INTO settings_notifications(id)
        VALUES(1)
    ");

    $result = $conn->query("
        SELECT *
        FROM settings_notifications
        WHERE id = 1
    ");
}

$data = $result->fetch_assoc();

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $backup_reminder =
        isset($_POST['backup_reminder']) ? 1 : 0;

    $pending_payment_alert =
        isset($_POST['pending_payment_alert']) ? 1 : 0;

    $low_storage_alert =
        isset($_POST['low_storage_alert']) ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE settings_notifications
        SET
            backup_reminder=?,
            pending_payment_alert=?,
            low_storage_alert=?
        WHERE id=1
    ");

    $stmt->bind_param(
        "iii",
        $backup_reminder,
        $pending_payment_alert,
        $low_storage_alert
    );

    $stmt->execute();

    $message =
        "Notification settings saved.";

    $data['backup_reminder'] =
        $backup_reminder;

    $data['pending_payment_alert'] =
        $pending_payment_alert;

    $data['low_storage_alert'] =
        $low_storage_alert;
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Notification Settings</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{
    margin:0;
    background:#f5f6fa;
    font-family:'Segoe UI';
}

.container{
    display:flex;
}

.main{
    flex:1;
    padding:25px;
}

.page-title{
    font-size:26px;
    font-weight:700;
    margin-bottom:20px;
}

.card{
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,.05);
}

.checkbox{
    margin-bottom:20px;
}

.checkbox label{
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:600;
    cursor:pointer;
}

.info{
    font-size:13px;
    color:#64748b;
    margin-left:28px;
    margin-top:5px;
}

.btn{
    background:#2563eb;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
}

.success{
    background:#dcfce7;
    color:#166534;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
}
</style>

</head>

<body>

<div class="container">

<?php
$active_page='settings';
include '../includes/sidebar.php';
?>

<main class="main">

<div class="page-title">
<i class="fas fa-bell"></i>
Notification Settings
</div>

<div class="card">

<?php if($message){ ?>
<div class="success">
<?= $message ?>
</div>
<?php } ?>

<form method="POST">

<div class="checkbox">

<label>

<input
type="checkbox"
name="backup_reminder"
<?= $data['backup_reminder'] ? 'checked' : '' ?>>

Backup Reminder

</label>

<div class="info">
Show warning if no backup created for 7 days.
</div>

</div>

<div class="checkbox">

<label>

<input
type="checkbox"
name="pending_payment_alert"
<?= $data['pending_payment_alert'] ? 'checked' : '' ?>>

Pending Payment Alert

</label>

<div class="info">
Show reminder for customers having outstanding balance.
</div>

</div>

<div class="checkbox">

<label>

<input
type="checkbox"
name="low_storage_alert"
<?= $data['low_storage_alert'] ? 'checked' : '' ?>>

Low Storage Warning

</label>

<div class="info">
Warn when system disk space becomes critically low.
</div>

</div>

<button
type="submit"
class="btn">

<i class="fas fa-save"></i>
 Save Settings

</button>

</form>

</div>

</main>

</div>

</body>
</html>