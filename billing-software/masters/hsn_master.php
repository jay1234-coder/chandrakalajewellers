<?php
include '../includes/db.php';

/* Add HSN Record */
if(isset($_POST['save']))
{
    $hsn_code = mysqli_real_escape_string($conn, $_POST['hsn_code']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $gst_rate = floatval($_POST['gst_rate']);

    mysqli_query(
        $conn,
        "INSERT INTO hsn_master (hsn_code, description, gst_rate) VALUES ('$hsn_code', '$description', '$gst_rate')"
    );

    header("Location: hsn_master.php?msg=HSN Code Added Successfully");
    exit;
}

/* Delete HSN Record */
if(isset($_GET['delete']))
{
    $id = intval($_GET['delete']);

    mysqli_query(
        $conn,
        "DELETE FROM hsn_master WHERE id='$id'"
    );

    header("Location: hsn_master.php?msg=HSN Code Deleted Successfully");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HSN Master - Chandrakala Jewellers</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body { background: #f5f6fa; margin: 0; font-family: 'Segoe UI', sans-serif; }
    .container { display: flex; }
    .sidebar { width: 260px; min-height: 100vh; background: #111827; color: white; }
    .logo { padding: 25px; text-align: center; border-bottom: 1px solid #333; }
    .sidebar ul { list-style: none; padding: 0; margin: 0; }
    .sidebar ul li a { display: block; padding: 16px 25px; color: white; text-decoration: none; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: #1f2937; }
    .sidebar i { margin-right: 10px; }
    .main { flex: 1; padding: 25px; }
    
    .form-box { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); margin-bottom: 25px; }
    .form-inline { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
    .form-inline input { flex: 1; min-width: 180px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; outline: none; }
    .form-inline input:focus { border-color: #3b82f6; }
    .btn-save { background: #111827; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }
    
    .table-box { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    .table-header { padding: 20px; border-bottom: 1px solid #eee; }
    table { width: 100%; border-collapse: collapse; }
    table th { background: #f8fafc; text-align: left; padding: 15px; font-weight: 600; color: #444; border-bottom: 2px solid #edf2f7; }
    table td { padding: 15px; border-top: 1px solid #eee; color: #333; }
    .btn-delete { color: #ef4444; text-decoration: none; font-weight: 600; }
    .alert-msg { background: #d1fae5; color: #065f46; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
</style>
</head>
<body>

<div class="container">

    <?php 
    $active_page = 'hsn_master'; 
    include '../includes/sidebar.php'; 
    ?>

    <main class="main">
        <div class="header">
            <h1>HSN / SAC Code Master</h1>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert-msg"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>

        <div class="form-box">
            <form method="POST" class="form-inline">
                <input type="text" name="hsn_code" placeholder="HSN / SAC Code (e.g. 7113)" required>
                <input type="text" name="description" placeholder="Goods Description (e.g. Gold Ornaments)" required>
                <input type="number" step="0.01" name="gst_rate" placeholder="GST Rate % (e.g. 3)" required>
                <button type="submit" name="save" class="btn-save">Add HSN Code</button>
            </form>
        </div>

        <div class="table-box">
            <div class="table-header">
                <h3>Registered HSN Master Grid</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>HSN / SAC Code</th>
                        <th>Description</th>
                        <th>Default GST Rate</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $result = mysqli_query($conn, "SELECT * FROM hsn_master ORDER BY id DESC");
                if(mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                        // Safe fallback array key matching system:
                        $rate_value = isset($row['gst_rate']) ? $row['gst_rate'] : (isset($row['gst']) ? $row['gst'] : 0.00);
                ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><strong><?= $row['hsn_code']; ?></strong></td>
                        <td><?= $row['description']; ?></td>
                        <td><?= number_format($rate_value, 2); ?>%</td>
                        <td>
                            <a href="?delete=<?= $row['id']; ?>" class="btn-delete" onclick="return confirm('Permanently remove this HSN category?')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align:center; padding: 20px; color:#777;'>No HSN registry lines cataloged yet.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>