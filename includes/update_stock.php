<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['id'];
    $product_name = $_POST['name'];
    $new_stock = $_POST['stock'];
    $expiry_date = $_POST['expiry_date'];
    $image = $_FILES['image'];

    // Set default values if fields are empty
    if (empty($expiry_date)) {
        $expiry_date = null;
    }

    $image_path = null;

    if ($image['error'] === 0) {
        $target_dir = "uploads/";
        $imageFileType = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $valid_extensions = ["jpg", "jpeg", "png", "gif"];

        if (!in_array($imageFileType, $valid_extensions)) {
            die("Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.");
        }

        $new_file_name = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_file_name;

        if (!move_uploaded_file($image['tmp_name'], $target_file)) {
            die("Sorry, there was an error uploading your image.");
        }

        $image_path = $target_file;
    }

    if ($image_path !== null) {
        $stmt = $conn->prepare("UPDATE products SET name = ?, stock = ?, expiry_date = ?, image = ? WHERE id = ?");
        $stmt->bind_param("sissi", $product_name, $new_stock, $expiry_date, $image_path, $product_id);
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, stock = ?, expiry_date = ? WHERE id = ?");
        $stmt->bind_param("sisi", $product_name, $new_stock, $expiry_date, $product_id);
    }

    if ($stmt->execute()) {
        echo "
        <div class='popup' id='popup'>
            <p>Product updated successfully!</p>
        </div>
        <script>
            document.getElementById('popup').style.display = 'block';
            setTimeout(function() {
                document.getElementById('popup').style.display = 'none';
                window.location.href = 'admin.php';
            }, 3000);
        </script>";
    } else {
        echo "<div class='popup error' id='popup'>Error: " . $stmt->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Stock</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .popup {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            z-index: 1000;
            border-radius: 5px;
        }

        .popup.error {
            background-color: #f44336;
        }

        .table-wrapper {
            max-height: 500px;
            overflow-y: auto;
        }

        .modal-lg {
            max-width: 700px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Update Product Stock</h2>

    <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search product...">

    <div class="table-wrapper">
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>Name</th>
                <th>Stock</th>
                <th>Expiry Date</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="productTable">
            <?php
            $result = $conn->query("SELECT * FROM products");
            while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['stock']) ?></td>
                    <td><?= htmlspecialchars($row['expiry_date']) ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm editBtn"
                                data-id="<?= $row['id'] ?>"
                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                data-stock="<?= $row['stock'] ?>"
                                data-expiry="<?= $row['expiry_date'] ?>">
                            Edit
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="productId">
                    <div class="mb-3">
                        <label for="productName" class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="productName" required>
                    </div>
                    <div class="mb-3">
                        <label for="productStock" class="form-label">Stock</label>
                        <input type="number" class="form-control" name="stock" id="productStock" required>
                    </div>
                    <div class="mb-3">
                        <label for="productExpiry" class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date" id="productExpiry">
                    </div>
                    <div class="mb-3">
                        <label for="productImage" class="form-label">Image (optional)</label>
                        <input type="file" class="form-control" name="image" id="productImage">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Update Product</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    $(document).ready(function () {
        $('.editBtn').click(function () {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const stock = $(this).data('stock');
            const expiry = $(this).data('expiry');

            $('#productId').val(id);
            $('#productName').val(name);
            $('#productStock').val(stock);
            $('#productExpiry').val(expiry);

            $('#editModal').modal('show');
        });

        $('#searchInput').on('input', function () {
            const value = $(this).val().toLowerCase();
            $('#productTable tr').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
    });
</script>
</body>
</html>
