<?php
include '../includes/db.php';

$message = '';
$error = '';

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "jewellery_management";

$result = $conn->query("
    SELECT *
    FROM settings_backup
    WHERE id = 1
");

if($result->num_rows == 0){

    $conn->query("
        INSERT INTO settings_backup(
            id,
            backup_folder
        )
        VALUES(
            1,
            '../backups/'
        )
    ");

    $result = $conn->query("
        SELECT *
        FROM settings_backup
        WHERE id = 1
    ");
}

$data = $result->fetch_assoc();

if(isset($_POST['save_settings'])){

    $backup_folder =
        trim($_POST['backup_folder']);

    $auto_backup =
        isset($_POST['auto_backup']) ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE settings_backup
        SET
            backup_folder=?,
            auto_backup=?
        WHERE id=1
    ");

    $stmt->bind_param(
        "si",
        $backup_folder,
        $auto_backup
    );

    $stmt->execute();

    $message =
        "Backup settings saved.";

    $data['backup_folder'] =
        $backup_folder;

    $data['auto_backup'] =
        $auto_backup;
}

if(isset($_POST['create_backup'])){

    $backup_folder =
        $data['backup_folder'];

    if(!is_dir($backup_folder)){
        mkdir(
            $backup_folder,
            0777,
            true
        );
    }

    $filename =
        'backup_' .
        date('Y_m_d_His') .
        '.sql';

    $backup_file =
        $backup_folder .
        $filename;

    $command =
        '"C:\\xampp\\mysql\\bin\\mysqldump.exe" ' .
        '--user=' . $db_user . ' ' .
        '--password=' . $db_pass . ' ' .
        $db_name .
        ' > "' . $backup_file . '"';

    exec($command,$output,$result_code);

    if(file_exists($backup_file)){

        $conn->query("
            UPDATE settings_backup
            SET last_backup = NOW()
            WHERE id = 1
        ");

        $message =
            "Database backup created successfully.";

        $data['last_backup'] =
            date('Y-m-d H:i:s');

    }else{

        $error =
            "Backup failed. Check mysqldump path.";
    }
}

if(isset($_POST['restore_backup'])){

    if(
        isset($_FILES['restore_file']) &&
        $_FILES['restore_file']['error'] == 0
    ){

        $ext =
            strtolower(
                pathinfo(
                    $_FILES['restore_file']['name'],
                    PATHINFO_EXTENSION
                )
            );

        if($ext != 'sql'){

            $error =
                "Only SQL files allowed.";

        }else{

            $temp_file =
                $_FILES['restore_file']['tmp_name'];

            $command =
                '"C:\\xampp\\mysql\\bin\\mysql.exe" ' .
                '--user=' . $db_user . ' ' .
                '--password=' . $db_pass . ' ' .
                $db_name .
                ' < "' . $temp_file . '"';

            exec(
                $command,
                $output,
                $result_code
            );

            $message =
                "Database restored successfully.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Backup & Restore</title>

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
    border-radius:12px;
    padding:25px;
    box-shadow:0 2px 8px rgba(0,0,0,.05);
}

.form-group{
    margin-bottom:18px;
}

label{
    display:block;
    margin-bottom:8px;
    font-weight:600;
}

input[type=text]{
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
    box-sizing:border-box;
}

.checkbox{
    margin-bottom:15px;
}

.btn{
    border:none;
    border-radius:8px;
    padding:12px 20px;
    color:white;
    cursor:pointer;
    font-weight:600;
}

.btn-save{
    background:#2563eb;
}

.btn-backup{
    background:#16a34a;
}

.btn-restore{
    background:#ea580c;
}

.success{
    background:#dcfce7;
    color:#166534;
    padding:12px;
    border-radius:8px;
    margin-bottom:15px;
}

.error{
    background:#fee2e2;
    color:#991b1b;
    padding:12px;
    border-radius:8px;
    margin-bottom:15px;
}

.section{
    margin-top:30px;
    padding-top:25px;
    border-top:1px solid #e5e7eb;
}

.info-box{
    background:#f8fafc;
    padding:15px;
    border-radius:10px;
    margin-top:10px;
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
<i class="fas fa-database"></i>
Backup & Restore
</div>

<div class="card">

<?php if($message){ ?>
<div class="success">
<?= $message ?>
</div>
<?php } ?>

<?php if($error){ ?>
<div class="error">
<?= $error ?>
</div>
<?php } ?>

<form method="POST">

<h3>Backup Settings</h3>

<div class="form-group">

<label>
Backup Folder
</label>

<input
type="text"
name="backup_folder"
value="<?= htmlspecialchars($data['backup_folder']) ?>">
</div>

<div class="checkbox">

<label>

<input
type="checkbox"
name="auto_backup"
<?= $data['auto_backup'] ? 'checked' : '' ?>>

Enable Auto Backup

</label>

</div>

<button
type="submit"
name="save_settings"
class="btn btn-save">

<i class="fas fa-save"></i>
 Save Settings

</button>

</form>

<div class="info-box">

<b>Last Backup:</b>

<?= !empty($data['last_backup'])
? date('d-M-Y h:i A',strtotime($data['last_backup']))
: 'Never' ?>

</div>

<div class="section">

<h3>Create Backup</h3>

<form method="POST">

<button
type="submit"
name="create_backup"
class="btn btn-backup">

<i class="fas fa-download"></i>
 Create Database Backup

</button>

</form>

</div>

<div class="section">

<h3>Restore Backup</h3>

<form
method="POST"
enctype="multipart/form-data">

<div class="form-group">

<label>Select SQL File</label>

<input
type="file"
name="restore_file"
accept=".sql">

</div>

<button
type="submit"
name="restore_backup"
class="btn btn-restore">

<i class="fas fa-upload"></i>
 Restore Database

</button>

</form>

</div>

</div>

</main>

</div>

</body>
</html>