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
    .form-inline { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
    .form-inline input { flex: 1; min-width: 180px; }
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