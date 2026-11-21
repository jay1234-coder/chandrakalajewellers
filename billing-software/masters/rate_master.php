<?php
include '../includes/db.php';

if(isset($_POST['save']))
{
    $purity_id = intval($_POST['purity_id']);
    $rate = floatval($_POST['rate']);

    mysqli_query(
        $conn,
        "INSERT INTO rate_master (purity_id, rate) VALUES ('$purity_id', '$rate')"
    );

    header("Location: rate_master.php");
    exit;
}

if(isset($_GET['delete']))
{
    $id = intval($_GET['delete']);

    mysqli_query(
        $conn,
        "DELETE FROM rate_master WHERE id='$id'"
    );

    header("Location: rate_master.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rate Master - Chandrakala Jewellers</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    .form-inline { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
    .form-inline select, .form-inline input { flex: 1; min-width: 200px; }
</style>
</head>
<body>

<div class="container">

    <?php 
    $active_page = 'rate_master'; 
    include '../includes/sidebar.php'; 
    ?>

    <main class="main">
        <div class="header">
            <h1>Rate Master</h1>
        </div>

        <div class="form-box">
            <form method="POST" class="form-inline">
                <select name="purity_id" required>
                    <option value="">Select Purity</option>
                    <?php
                    $purity = mysqli_query($conn, "SELECT * FROM purity_master");
                    while($p = mysqli_fetch_assoc($purity)) {
                        echo "<option value='".$p['id']."'>".$p['purity_name']."</option>";
                    }
                    ?>
                </select>
                <input type="number" step="0.01" name="rate" placeholder="Rate (₹ per Gram)" required>
                <button type="submit" name="save" class="btn-save">Save Rate</button>
            </form>
        </div>

        <div class="table-box">
            <div class="table-header">
                <h3>Active Commodity Rates</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Purity Group</th>
                        <th>Current Rate Valuation</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sql = "SELECT rate_master.*, purity_master.purity_name 
                        FROM rate_master 
                        LEFT JOIN purity_master ON rate_master.purity_id = purity_master.id 
                        ORDER BY rate_master.id DESC";
                $result = mysqli_query($conn, $sql);

                if(mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><strong><?= !empty($row['purity_name']) ? $row['purity_name'] : 'Unmapped'; ?></strong></td>
                        <td>₹ <?= number_format($row['rate'], 2); ?></td>
                        <td><?= !empty($row['updated_at']) ? $row['updated_at'] : 'N/A'; ?></td>
                        <td>
                            <a href="?delete=<?= $row['id']; ?>" class="btn-delete" onclick="return confirm('Delete this current valuation line entry?')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align:center; padding: 20px; color:#777;'>No active commodity evaluation rows found.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </main>

</div>

</body>
</html>