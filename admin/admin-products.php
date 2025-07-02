<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/admin_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';
require_once __DIR__ . '/../include/fees.php';

if ($isLoggedIn) {
    if ($_SESSION['user']['role'] === 'admin') {
        $admin_id = $_SESSION['user']['user_id'] ?? null;
        if ($admin_id) {
            log_attempt($pdo, $admin_id, 'view', 'admin dashboard', 'admin');
        }
    }
}

$activePage = 'products'; // Set the active page for the sidebar

// Fetch products
$products = getAllProducts(); // Fetch all products for the product list
// Fetch site fees
$shippingFee = get_site_fee($pdo, 'shipping');
$taxFee = get_site_fee($pdo, 'tax');

// Success message handling
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success'] ?? '');
} else {
    $success_message = '';
}

// Error message handling
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error'] ?? '');
} else {
    $error_message = '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Administrator</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    // Include the header file
    include 'views/admin-header.php';
    include 'views/admin-sidebar.php';
    // Include admin-login.php if user is not logged in or not an admin
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        include 'admin-login.php';
        exit;
    }
    ?>
    <main>
        <div class="main-content">
            <div class="user-list">
                <div class="search-filter-bar">
                    <input id="searchInput" type="text" placeholder="Search products...">
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message"
                        style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>

                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="success-message"
                        style="color: #2e7d32; margin-bottom: 15px; padding: 12px; background-color: #e8f5e8; border: 1px solid #4caf50; border-radius: 5px; font-weight: bold;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>

                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <table id="user-table" class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['product_price']); ?></td>
                                <td>
                                    <button type="submit"
                                        style="background:#e55039;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;">
                                        <a href="edit-product.php?product_id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                            style="color: white; text-decoration: none;">Edit</a>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                    $(function () {
                        // init DataTable without its own search box
                        const table = $('#user-table').DataTable({
                            dom: 'tip', // just table, info, pagination
                            pagingType: 'simple_numbers',
                            pageLength: 10,
                            search: {
                                smart: true
                            },
                            columnDefs: [
                                {
                                    targets: [3], // Action column
                                    searchable: false
                                }
                            ]
                        });

                        $('#searchInput').on('keyup', function () {
                            const searchTerm = this.value.toLowerCase();
                            table.search(searchTerm).draw();
                        });
                    });
                </script>

                <h3>Site Fees</h3>
                <form action="handlers/update_fees.php" method="POST" class="site-fees-form"
                    style="margin-bottom:25px;">
                    <label>Shipping ($): <input type="number" step="0.01" name="shipping_fee"
                            value="<?php echo htmlspecialchars($shippingFee); ?>" required></label>
                    <label>Tax ($): <input type="number" step="0.01" name="tax_fee"
                            value="<?php echo htmlspecialchars($taxFee); ?>" required></label>
                    <button type="submit" style="padding:6px 14px;">Update Fees</button>
                </form>
            </div>
    </main>
</body>
<style>
    .site-fees-form input {
        width: 80px;
        text-align: center;        
        padding: 10px 14px;
        font-size: 1rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        background-color: #fff;
        outline: none;
        transition: border-color 0.2s ease;
    }

    .site-fees-form label {
        font-weight: 500;
    }

    .site-fees-form button {
        background-color: #4a90e2;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 10px 14px;
        cursor: pointer;
        font-size: 1rem;
        transition: background-color 0.2s ease;
    }

    .main-content {
        margin-left: 220px;
        padding: 20px;
    }

    /* ---- Intake Table Modern Styling ---- */
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin: 20px 0;
        font-family: 'Segoe UI', sans-serif;
    }

    th,
    td {
        padding: 14px 16px;
        text-align: left;
        font-size: 1rem;
        color: #333;
        border-bottom: 1px solid #eee;
    }

    th {
        background-color: #f8f9fb;
        font-weight: 600;
        color: #555;
    }

    tr:hover {
        background-color: #f1f7ff;
        transition: background-color 0.2s ease;
    }

    tr:last-child td {
        border-bottom: none;
    }

    /* Responsive */
    @media (max-width: 700px) {
        table {
            width: 100%;
            font-size: 0.95em;
            border-radius: 0;
            box-shadow: none;
        }

        th,
        td {
            padding: 10px 8px;
        }
    }

    .search-filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .search-filter-bar input[type="text"],
    .search-filter-bar select {
        padding: 10px 14px;
        font-size: 1rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        background-color: #fff;
        outline: none;
        transition: border-color 0.2s ease;
    }

    .search-filter-bar select {
        width: 150px;
    }

    .search-filter-bar input[type="text"]:focus,
    .search-filter-bar select:focus {
        border-color: #4a90e2;
    }

    @media (max-width: 900px) {
        .main-content {
            margin: 80px 12px 24px 12px;
            /* space for toggle   */
        }

        /* Search / filter bar stacks vertically */
        .search-filter-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .search-filter-bar input[type="text"],
        .search-filter-bar select {
            width: 100%;
        }

        /* Table font & paddings slightly smaller */
        th,
        td {
            padding: 10px 8px;
            font-size: 0.95rem;
        }

        /* Hide less-important columns on very small screens
       (IDs & timestamps can be toggled back if needed) */
        @media (max-width: 900px) {
            #user-table th:nth-child(1),
            #user-table td:nth-child(1),
            /* User ID */
            #user-table th:nth-child(8),
            #user-table td:nth-child(8),
            /* Created At */
            #user-table th:nth-child(9),
            #user-table td:nth-child(9)

            /* Last Login */
                {
                display: none;
            }
        }
    }
</style>