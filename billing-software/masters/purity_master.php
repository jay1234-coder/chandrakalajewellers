<?php
include '../includes/db.php';

/* Add Purity */
if(isset($_POST['save']))
{
    $name = mysqli_real_escape_string($conn, $_POST['purity_name']);
    $value = mysqli_real_escape_string($conn, $_POST['purity_value']);

    mysqli_query(
        $conn,
        "INSERT INTO purity_master (purity_name, purity_value) VALUES ('$name', '$value')"
    );

    header("Location: purity_master.php");
    exit;
}

/* Delete */
if(isset($_GET['delete']))
{
    $id = intval($_GET['delete']);

    mysqli_query(
        $conn,
        "DELETE FROM purity_master WHERE id='$id'"
    );

    header("Location: purity_master.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purity Master - Chandrakala Jewellers</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    .form-inline { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
    .form-inline input { flex: 1; min-width: 200px; }
</style>
</head>
<body>

<div class="container">

    <?php 
    $active_page = 'purity_master'; 
    include '../includes/sidebar.php'; 
    ?>

    <main class="main">
        <div class="header">
            <h1>Purity Master</h1>
        </div>

        <div class="form-box">
            <form method="POST" class="form-inline">
                <input type="text" name="purity_name" placeholder="Purity Name (e.g. 22K Gold)" required>
                <input type="text" name="purity_value" placeholder="Purity Value (e.g. 91.6)" required>
                <button type="submit" name="save" class="btn-save">Add Purity</button>
            </form>
        </div>

        <div class="table-box">
            <div class="table-header">
                <h3>Configured Purities</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Purity Name</th>
                        <th>Purity Value</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $result = mysqli_query($conn, "SELECT * FROM purity_master ORDER BY id DESC");
                if (mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><strong><?= $row['purity_name']; ?></strong></td>
                        <td><?= $row['purity_value']; ?></td>
                        <td>
                            <a href="?delete=<?= $row['id']; ?>" class="btn-delete" onclick="return confirm('Delete this Purity mapping config?')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='4' style='text-align:center; padding: 20px; color:#777;'>No gold/silver purity lines populated yet.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </main>

</div>

</body>
</html>