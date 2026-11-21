<?php
include '../includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $shop_name     = $_POST['shop_name'];
    $owner_name    = $_POST['owner_name'];
    $address       = $_POST['address'];
    $mobile        = $_POST['mobile'];
    $email         = $_POST['email'];
    $gst_no        = $_POST['gst_no'];
    $pan_no        = $_POST['pan_no'];
    $invoice_footer = $_POST['invoice_footer'];

    $logo_path = '';

    if (!empty($_FILES['logo']['name']) && !empty($_FILES['logo']['tmp_name'])) {
        // Cloudinary Cloud Sync Integration
        $cldFile = __DIR__ . '/../../includes/cloudinary.php';
        if (file_exists($cldFile)) {
            require_once $cldFile;
            if (function_exists('cj_cloudinary_upload')) {
                $cldRes = cj_cloudinary_upload($_FILES['logo']['tmp_name'], 'shop_logo_' . time(), 'chandrakala/billing');
                if (!empty($cldRes['success']) && !empty($cldRes['secure_url'])) {
                    $logo_path = $cldRes['secure_url'];
                }
            }
        }

        // Local fallback if Cloudinary is not used or offline
        if (empty($logo_path)) {
            $upload_dir = "../uploads/settings/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = time() . "_" . basename($_FILES['logo']['name']);
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                $logo_path = "uploads/settings/" . $filename;
            }
        }
    }

    if (!empty($logo_path)) {

        $stmt = $conn->prepare("
            UPDATE settings_business
            SET
                shop_name=?,
                owner_name=?,
                address=?,
                mobile=?,
                email=?,
                gst_no=?,
                pan_no=?,
                logo=?,
                invoice_footer=?
            WHERE id=1
        ");

        $stmt->bind_param(
            "sssssssss",
            $shop_name,
            $owner_name,
            $address,
            $mobile,
            $email,
            $gst_no,
            $pan_no,
            $logo_path,
            $invoice_footer
        );

    } else {

        $stmt = $conn->prepare("
            UPDATE settings_business
            SET
                shop_name=?,
                owner_name=?,
                address=?,
                mobile=?,
                email=?,
                gst_no=?,
                pan_no=?,
                invoice_footer=?
            WHERE id=1
        ");

        $stmt->bind_param(
            "ssssssss",
            $shop_name,
            $owner_name,
            $address,
            $mobile,
            $email,
            $gst_no,
            $pan_no,
            $invoice_footer
        );
    }

    $stmt->execute();

    $message = "Business settings updated successfully.";
}

$result = $conn->query("
    SELECT *
    FROM settings_business
    WHERE id=1
");

if(!$result){
    die("SQL Error: " . $conn->error);
}

$data = $result->fetch_assoc();

$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Business Settings</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{
    background:#f5f6fa;
    font-family:'Segoe UI';
    margin:0;
}

.container{
    display:flex;
}

.main{
    flex:1;
    padding:25px;
}

.card{
    background:#fff;
    border-radius:12px;
    padding:25px;
    box-shadow:0 2px 8px rgba(0,0,0,.05);
}

.page-title{
    font-size:26px;
    font-weight:700;
    margin-bottom:20px;
}

.form-group{
    margin-bottom:15px;
}

label{
    display:block;
    margin-bottom:6px;
    font-weight:600;
}

input,
textarea{
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
    box-sizing:border-box;
}

textarea{
    resize:vertical;
    min-height:100px;
}

.save-btn{
    background:#2563eb;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
}

.logo-preview{
    max-height:120px;
    margin-top:10px;
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
    <i class="fas fa-store"></i>
    Business Settings
</div>

<div class="card">

<?php if($message): ?>
<div class="success">
    <?= $message ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<div class="form-group">
<label>Shop Name</label>
<input
type="text"
name="shop_name"
value="<?= htmlspecialchars($data['shop_name'] ?? '') ?>"
required>
</div>

<div class="form-group">
<label>Owner Name</label>
<input
type="text"
name="owner_name"
value="<?= htmlspecialchars($data['owner_name'] ?? '') ?>">
</div>

<div class="form-group">
<label>Address</label>
<textarea
name="address"><?= htmlspecialchars($data['address'] ?? '') ?></textarea>
</div>

<div class="form-group">
<label>Mobile Number</label>
<input
type="text"
name="mobile"
value="<?= htmlspecialchars($data['mobile'] ?? '') ?>">
</div>

<div class="form-group">
<label>Email</label>
<input
type="email"
name="email"
value="<?= htmlspecialchars($data['email'] ?? '') ?>">
</div>

<div class="form-group">
<label>GST Number</label>
<input
type="text"
name="gst_no"
value="<?= htmlspecialchars($data['gst_no'] ?? '') ?>">
</div>

<div class="form-group">
<label>PAN Number</label>
<input
type="text"
name="pan_no"
value="<?= htmlspecialchars($data['pan_no'] ?? '') ?>">
</div>

<div class="form-group">
<label>Upload Logo</label>
<input
type="file"
name="logo"
accept="image/*">

<?php if(!empty($data['logo'])): 
    $previewSrc = (strpos($data['logo'], 'http://') === 0 || strpos($data['logo'], 'https://') === 0) 
        ? $data['logo'] 
        : '../' . ltrim($data['logo'], '/');
?>

<img
src="<?= htmlspecialchars($previewSrc) ?>"
class="logo-preview" alt="Shop Logo">

<?php endif; ?>

</div>

<div class="form-group">
<label>Invoice Footer</label>

<textarea
name="invoice_footer"><?= htmlspecialchars($data['invoice_footer'] ?? '') ?></textarea>

</div>

<button
type="submit"
class="save-btn">

<i class="fas fa-save"></i>
Save Settings

</button>

</form>

</div>

</main>

</div>

</body>
</html>