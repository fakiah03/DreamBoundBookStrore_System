<?php
session_start();
require_once '../db.php'; 

// 1. SECURITY RESTRICTION: Ensure only authorized Admins can enter this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

$alert_message = "";

// 2. Function to update order status (Change Logistics Status)
if (isset($_GET['update_id']) && isset($_GET['new_status'])) {
    $order_id = intval($_GET['update_id']);
    $new_status = mysqli_real_escape_string($conn, strtolower(trim($_GET['new_status'])));
    
    // Ensure admin can only set allowed statuses
    $allowed_status = ['pending', 'shipped', 'delivered', 'cancelled'];
    if (in_array($new_status, $allowed_status)) {
        $conn->query("UPDATE orders SET status = '$new_status' WHERE id = $order_id");
        $conn->query("INSERT INTO system_logs (log_message) VALUES ('Admin menukar status pesanan ID #$order_id kepada $new_status')");
        $alert_message = "Order #DB-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " successfully changed to [" . ucfirst($new_status) . "]";
    } else {
        $alert_message = "Invalid status! Please enter only: Pending, Shipped, Delivered, or Cancelled.";
    }
}

// 3. Retrieve statistics for the 4 widgets above
$stat_new = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'")->fetch_assoc()['total'];
$stat_shipped = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'shipped'")->fetch_assoc()['total'];
$stat_delivered = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'delivered'")->fetch_assoc()['total'];
$stat_total = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'];

// 4. Retrieve the list of orders along with customer names
$sql_orders = "
    SELECT 
        o.id as order_id, 
        u.fullname as customer_name, 
        o.created_at as order_date, 
        o.total_amount as total_price, 
        o.status as order_status
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.id DESC
";
$orders_result = $conn->query($sql_orders);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Order Information</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    
    <style>
       
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Englebert', cursive, sans-serif; }

        body { 
            background-color: #FC9D01; 
            display: flex; 
            height: 100vh; 
            overflow: hidden; }

        .container { 
            display: flex; 
            width: 100%; 
            height: 100vh; }
            
        .sidebar { 
            width: 280px; 
            background-color: #0E2C46; 
            color: white; 
            display: flex; 
            flex-direction: column; 
            padding: 30px 0; 
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.2); 
            flex-shrink: 0; }

        .profile-section { 
            text-align: center; 
            padding: 0 20px 25px 20px; 
            border-bottom: 2px solid rgba(255, 255, 255, 0.1); }

        .logo-img { 
            width: 150px; 
            margin-bottom: 10px; }

        .profile-section h3 { 
            font-size: 1.4rem; 
            letter-spacing: 1px; 
            color: #ffffff; }

        .subtitle { 
            font-size: 0.8rem; 
            color: #FC9D01; 
            letter-spacing: 2px; 
            margin-bottom: 15px; }
            
        .user-info { 
            background-color: rgba(255, 255, 255, 0.1); 
            padding: 10px; 
            border-radius: 8px; 
            font-size: 0.95rem; }

        .nav-links { 
            list-style: none; 
            margin-top: 25px; 
            flex-grow: 1; 
            padding: 0 15px; }

        .nav-links li { 
            margin-bottom: 8px; }

        .nav-links li a { 
            text-decoration: none; 
            color: #ffffff; 
            font-size: 1.05rem; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 12px 20px; 
            border-radius: 8px; 
            transition: all 0.3s ease; 
            background: rgba(255, 255, 255, 0.05); }

        .nav-links li.active a, .nav-links li a:hover { 
            background: #FC9D01; 
            color: #0E2C46; 
            font-weight: bold; 
            transform: translateX(5px); }

        .logout-container { 
            padding: 0 15px; }

        .btn-logout { 
            width: 100%; 
            padding: 12px; 
            border-radius: 8px; 
            border: 2px solid #FC9D01; 
            cursor: pointer; 
            background: transparent; 
            color: #FC9D01; 
            font-size: 1.1rem; 
            font-weight: bold; 
            transition: all 0.3s ease; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; }

        .btn-logout:hover { 
            background: #FC9D01; 
            color: #0E2C46; }

        .main-content { 
            flex-grow: 1; 
            background-color: #FC9D01; 
            padding: 30px; 
            overflow-y: auto; }

        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; }

        .page-title { 
            font-size: 2.5rem; 
            color: #0E2C46; 
            margin-bottom: 20px; 
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5); }

        .order-stats-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px; 
            margin-bottom: 30px; }

        .order-stat-card { 
            background-color: #FDF5E6; 
            border: 2px solid #0E2C46; 
            border-radius: 12px; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            color: #0E2C46; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        .order-stat-card h4 { 
            font-size: 0.95rem; 
            color: #555; }

        .order-stat-card p { 
            font-size: 1.6rem; 
            font-weight: bold; }

        .order-stat-card i { 
            font-size: 1.8rem; 
            color: #FC9D01; 
            background: #0E2C46; 
            padding: 10px; 
            border-radius: 50%; }

        .control-panel { 
            background-color: #FDF5E6; 
            border: 2px solid #0E2C46; 
            border-radius: 15px; 
            padding: 20px; 
            margin-bottom: 25px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 15px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        .filter-tabs { 
            display: flex; 
            gap: 10px; }

        .tab-btn { 
            padding: 8px 16px; 
            border: 2px solid #0E2C46; 
            background: white; 
            color: #0E2C46; 
            border-radius: 20px; 
            cursor: pointer; 
            font-size: 0.95rem; 
            font-weight: bold; 
            transition: all 0.2s; }

        .tab-btn.active, .tab-btn:hover { 
            background: #0E2C46; 
            color: white; }

        .search-box { 
            display: flex; 
            background: white; 
            border: 2px solid #0E2C46;
            border-radius: 25px; 
            overflow: hidden; 
            padding: 2px; 
            width: 100%; 
            max-width: 350px; }

        .search-box input { 
            border: none; 
            padding: 8px 15px; 
            outline: none; 
            flex-grow: 1; 
            font-size: 0.95rem; }

        .search-box button { 
            background: #0E2C46; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 23px; 
            cursor: pointer; }

        .table-panel { 
            background-color: #FDF5E6; 
            border: 2px solid #0E2C46; 
            border-radius: 15px; 
            padding: 20px; 
            box-shadow: 0 6px 12px rgba(0,0,0,0.1); 
            color: #0E2C46; 
            overflow-x: auto; }

        .order-table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
            font-size: 1.05rem; }

        .order-table th { 
            background-color: #0E2C46; 
            color: white; 
            padding: 12px 15px; 
            font-size: 1.1rem; }

        .order-table td { 
            padding: 12px 15px; 
            border-bottom: 1px solid rgba(14, 44, 70, 0.2); 
            background-color: rgba(255, 255, 255, 0.3); }

        .order-table tr:hover td { 
            background-color: rgba(252, 157, 1, 0.1); }

        .status-badge { 
            padding: 5px 10px; 
            border-radius: 6px; 
            font-size: 0.85rem; 
            font-weight: bold; 
            text-align: center; 
            display: inline-block; }

        .status-badge.pending { 
            background-color: #fef08a; 
            color: #854d0e; 
            border: 1px solid #ca8a04; }

        .status-badge.shipped { 
            background-color: #bfdbfe; 
            color: #1e40af; 
            border: 1px solid #3b82f6; }

        .status-badge.delivered { 
            background-color: #bbf7d0; 
            color: #166534; 
            border: 1px solid #22c55e; }

        .status-badge.cancelled { 
            background-color: #fecaca; 
            color: #991b1b; 
            border: 1px solid #ef4444; }

        .action-cell { 
            display: flex; 
            gap: 8px; }

        .btn-table { 
            padding: 6px 12px; 
            border: 1px solid #0E2C46; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            font-weight: bold; 
            transition: all 0.2s; }

        .btn-table.view { 
            background-color: #0E2C46; 
            color: white; }

        .btn-table.view:hover { 
            background-color: #1a446c; }

        .btn-table.update { 
            background-color: #FC9D01; 
            color: #0E2C46; }

        .btn-table.update:hover { 
            background-color: #e08b00; }

        .pagination { 
            display: flex; 
            justify-content: flex-end; 
            margin-top: 20px; gap: 5px; }

        .page-node { 
            padding: 6px 12px; 
            border: 1px solid #0E2C46; 
            background: white; 
            cursor: pointer; 
            border-radius: 4px; 
            font-weight: bold; }

        .page-node.active, .page-node:hover { 
            background-color: #0E2C46; 
            color: white; }
            
    </style>
</head>
<body>

    <div class="container">
        <nav class="sidebar">
            <div class="profile-section">
                <img src="../img/logo1.png" alt="Dreambound Logo" class="logo-img">
                <h3>DREAMBOUND</h3>
                <p class="subtitle">BOOKSTORE</p>
                <div class="user-info">
                    <p><strong>ADMIN PORTAL</strong></p>
                    <p><?php echo isset($_SESSION['fullname']) ? htmlspecialchars(strtoupper($_SESSION['fullname'])) : 'JAMES BUBUYA'; ?></p>
                </div>
            </div>

            <ul class="nav-links">
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_DashBoard.php"><i class="fas fa-chart-line"></i> DASHBOARD STATUS</a></li>
                <li class="active"><a href="/DreamBoundBookStrore_system/Admin/ad_OrderInfo.php"><i class="fas fa-shopping-cart"></i> ORDER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_ManageBook.php"><i class="fas fa-book"></i> MANAGE BOOK</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_CustomerInfo.php"><i class="fas fa-users"></i> CUSTOMER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_settings.php"><i class="fas fa-sliders-h"></i> SETTING</a></li>
            </ul>

            <div class="logout-container">
                <button class="btn-logout" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
            </div>
        </nav>

        <main class="main-content">
            <h1 class="page-title">Order Information Registry</h1>

            <section class="order-stats-grid">
                <div class="order-stat-card">
                    <div>
                        <h4>New Orders</h4>
                        <p><?php echo number_format($stat_new); ?></p>
                    </div>
                    <i class="fas fa-clock"></i>
                </div>
                <div class="order-stat-card">
                    <div>
                        <h4>On Logistics</h4>
                        <p><?php echo number_format($stat_shipped); ?></p>
                    </div>
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="order-stat-card">
                    <div>
                        <h4>Successful Delivery</h4>
                        <p><?php echo number_format($stat_delivered); ?></p>
                    </div>
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="order-stat-card">
                    <div>
                        <h4>Total Invoices</h4>
                        <p><?php echo number_format($stat_total); ?></p>
                    </div>
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </section>

            <section class="control-panel">
                <div class="filter-tabs">
                    <button class="tab-btn active" onclick="filterStatus('all')">All Orders</button>
                    <button class="tab-btn" onclick="filterStatus('pending')">Pending</button>
                    <button class="tab-btn" onclick="filterStatus('shipped')">Shipped</button>
                    <button class="tab-btn" onclick="filterStatus('delivered')">Delivered</button>
                    <button class="tab-btn" onclick="filterStatus('cancelled')">Cancelled</button>
                </div>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search Order ID or Buyer Name...">
                    <button title="Search"><i class="fas fa-search"></i></button>
                </div>
            </section>

            <section class="table-panel">
                <table class="order-table" id="orderTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer Name</th>
                            <th>Date Ordered</th>
                            <th>Books Purchased</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Actions Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while($row = $orders_result->fetch_assoc()): 
                                
                                $order_id = $row['order_id'];
                                $display_id = "DB-" . str_pad($order_id, 4, '0', STR_PAD_LEFT);
                                $status_class = strtolower($row['order_status']); // pending, shipped dll
                                $date_formatted = date("d F Y", strtotime($row['order_date']));
                                
                                // Get the list of books for this order.
                                $books_str = "No items";
                                $book_query = $conn->query("SELECT b.title, oi.quantity FROM order_items oi JOIN books b ON oi.book_id = b.id WHERE oi.order_id = $order_id");
                                if ($book_query && $book_query->num_rows > 0) {
                                    $book_arr = [];
                                    while($b_row = $book_query->fetch_assoc()) {
                                        $book_arr[] = htmlspecialchars($b_row['title']) . " (x" . $b_row['quantity'] . ")";
                                    }
                                    $books_str = implode(", ", $book_arr);
                                }
                            ?>
                            <tr class="order-row" data-status="<?php echo $status_class; ?>">
                                <td><strong>#<?php echo $display_id; ?></strong></td>
                                <td class="customer-name"><?php echo htmlspecialchars($row['customer_name'] ?? 'Unknown User'); ?></td>
                                <td><?php echo $date_formatted; ?></td>
                                <td><?php echo $books_str; ?></td>
                                <td>RM <?php echo number_format($row['total_price'], 2); ?></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($status_class); ?></span></td>
                                <td class="action-cell">
                                    <button class="btn-table view" onclick="triggerView('#<?php echo $display_id; ?>')"><i class="fas fa-eye"></i> View</button>
                                    <button class="btn-table update" onclick="triggerUpdate(<?php echo $order_id; ?>, '#<?php echo $display_id; ?>')"><i class="fas fa-edit"></i> Status</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; font-weight: bold;">No orders were found in the database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <button class="page-node" type="button" aria-label="Previous page">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <button class="page-node active" type="button" aria-label="Page 1">1</button>
                    <button class="page-node" type="button" aria-label="Next page">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Show an alert for PHP success or error status.
        <?php if(!empty($alert_message)): ?>
            alert("<?php echo $alert_message; ?>");
        <?php endif; ?>

        // 1. Live status filter logic for the HTML table.
        function filterStatus(statusType) {
            // hange the color of the active tab.
            const tabs = document.querySelectorAll('.tab-btn');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter rows in the table. if 'all' is selected, show all rows. Otherwise, show only rows that match the selected status.
            const rows = document.querySelectorAll('.order-row');
            rows.forEach(row => {
                if (statusType === 'all') {
                    row.style.display = '';
                } else {
                    if (row.getAttribute('data-status') === statusType) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        // 2. SEARCH LOGIC
        document.getElementById('searchInput').addEventListener('input', function() {
            let searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.order-row');
            
            rows.forEach(row => {
                let orderId = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                let customerName = row.querySelector('.customer-name').textContent.toLowerCase();
                
                if (orderId.includes(searchValue) || customerName.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // 3. Detail view operation (currently using alert only).
        function triggerView(displayId) {
            alert("Fetching and generating Invoice details for Order " + displayId + "\nPreparing digital receipt view...");
        }

        // 4. Detail view operation (currently using alert only). In the future, this can be enhanced to open a modal with detailed information and options to print the invoice.
        function triggerUpdate(orderId, displayId) {
            let nextStatus = prompt("Update status for " + displayId + ":\nType: Pending, Shipped, Delivered, or Cancelled");
            if(nextStatus) {
                // Redirect to a URL for PHP processing.
                window.location.href = "ad_OrderInfo.php?update_id=" + orderId + "&new_status=" + encodeURIComponent(nextStatus);
            }
        }

        function confirmLogout() {
            if(confirm("Are you sure you want to log out from the admin platform?")) {
                window.location.href = "../logout.php";
            }
        }
    </script>
</body>
</html>