<?php
session_start();
require_once '../db.php'; // Sambungan ke database

// 1. SEKATAN KESELAMATAN: Pastikan pelanggan log masuk
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Customer';

// 2. AMBIL DATA PESANAN DARI DATABASE
// NOTA: Kod ini mengandaikan anda mempunyai jadual `orders`, `order_items`, dan `books`.
// Jika nama jadual anda berbeza, anda hanya perlu tukar nama jadual dalam SQL ini.
$sql_orders = "
    SELECT 
        o.id AS order_id, 
        b.title AS product, 
        oi.price AS unit_price, 
        oi.quantity, 
        (oi.price * oi.quantity) AS total_price, 
        o.status 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN books b ON oi.book_id = b.id
    WHERE o.user_id = $user_id
    ORDER BY o.created_at DESC
";

// Memastikan tiada ralat sekiranya jadual belum dibina
$orders_list = [];
try {
    $result = $conn->query($sql_orders);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders_list[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    // Abaikan jika jadual belum ada, ia akan memaparkan senarai kosong sahaja.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Orders</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
       :root {
            --primary-blue: #0A2647;
            --accent-orange: #F29400;
            --bg-gradient: linear-gradient(135deg, #0A2647 0%, #144272 100%);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Englebert', sans-serif;
        }

        body { 
            background: var(--bg-gradient); 
            height: 100vh; 
            display: flex; 
            overflow: hidden; 
            padding: 15px;
        }

        .dashboard { 
            display: flex; 
            width: 100%; 
            height: 100%; 
            background: rgba(255, 255, 255, 0.03);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .sidebar { 
            width: 280px; 
            background: rgba(10, 38, 71, 0.7);
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 40px 24px; 
            color: white; 
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .profile { text-align: center; width: 100%; margin-bottom: 40px; }

        .profile-circle { 
            width: 85px; height: 85px; 
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05)); 
            border-radius: 50%; 
            border: 2px solid var(--accent-orange); 
            margin: 0 auto 15px auto; 
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .profile-circle i { font-size: 32px; color: #ffffff; }

        .profile h2 { 
            font-size: 18px; font-weight: normal; color: #ffffff;
            text-transform: uppercase; letter-spacing: 1.5px;
        }

        .menu { width: 100%; }

         .menu ul {
            list-style: none; /* Buang titik hitam */
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .menu li {
            width: 100%;
        }

        .menu-item { 
            display: flex; align-items: center; color: rgba(255, 255, 255, 0.6); 
            text-decoration: none; padding: 18px 24px; margin-bottom: 12px; 
            border-radius: 20px; font-size: 22px; letter-spacing: 0.5px;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .menu-item i { margin-right: 20px; font-size: 22px; transition: transform 0.3s ease; }

        .menu-item:hover { color: #ffffff; background: rgba(255, 255, 255, 0.05); }

        .menu-item.active { 
            background: #FC9D01; color: var(--primary-blue);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25); font-weight: bold;
        }

        .logout-btn { 
            margin-top: auto; background: rgba(255, 255, 255, 0.02);
            color: #ff6b6b; border: 1px solid rgba(255, 77, 77, 0.25); 
            padding: 16px 20px; cursor: pointer; border-radius: 20px; 
            width: 100%; display: flex; align-items: center; justify-content: center; gap: 12px;
            font-size: 18px; letter-spacing: 0.5px; transition: all 0.3s ease;
        }

        .logout-btn:hover { background: #ff4d4d; color: white; box-shadow: 0 8px 20px rgba(255, 77, 77, 0.2); border-color: transparent; }


        /* MAIN CONTENT AREA */
        .content { 
            flex: 1; 
            padding: 30px 40px; 
            background: #FC9D01;
            border-top-left-radius: 24px; 
            border-bottom-left-radius: 24px;
            display: flex;
            flex-direction: column;
            overflow: hidden; 
        }

        .content h1 { 
            font-size: 48px; 
            color: var(--primary-blue); 
            margin-bottom: 2px;
            font-weight: bold; 
        }

        .content .subtitle {
            font-size: 16px;
            color: #ffffff;
            margin-bottom: 20px; 
        }

        .search-container { 
            position: relative; 
            width: 100%; 
            max-width: 320px; 
            margin-bottom: 25px; 
            flex-shrink: 0;
        }

        .search-input { 
            width: 100%; 
            padding: 10px 16px 10px 44px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
            outline: none; 
            background-color: #f8fafc; 
            font-size: 16px; 
            color: var(--primary-blue);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--primary-blue);
            background-color: #ffffff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }

        .search-icon { 
            position: absolute; 
            left: 16px; 
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8; 
            font-size: 16px;
        }

        /* TABLE CONTAINER */
        .table-master-container {
            width: 100%; 
            flex: 1; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 20px;
            overflow-y: auto; 
            background-color: #FFFDF4; 
            margin-bottom: 10px;
            -ms-overflow-style: none;  
        }

        .table-master-container::-webkit-scrollbar {
            display: none;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 19px;
            color: #0F172A;
            table-layout: fixed; 
        }

        .order-table th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #0A2647; 
            color: #ffffff;
            padding: 12px 18px; 
            font-size: 17px; 
            font-weight: normal;
            line-height: 1.2; 
            vertical-align: middle;
        }

        .order-table th:nth-child(1), .order-table td:nth-child(1) { width: 15%; } 
        .order-table th:nth-child(2), .order-table td:nth-child(2) { width: 29%; } 
        .order-table th:nth-child(3), .order-table td:nth-child(3) { width: 12%; } 
        .order-table th:nth-child(4), .order-table td:nth-child(4) { width: 12%; } 
        .order-table th:nth-child(5), .order-table td:nth-child(5) { width: 14%; } 
        .order-table th:nth-child(6), .order-table td:nth-child(6) { width: 18%; } 

        .order-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #E2E8F0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis; 
            vertical-align: middle;
        }

        .no-result-row td {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            font-size: 20px;
            background-color: #FFFDF4;
        }

        .no-result-row i {
            display: block;
            font-size: 32px;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 15px;
            display: inline-block;
            text-align: center;
            white-space: nowrap; 
            width: 100%; 
            max-width: 150px;
            font-weight: bold;
        }

        .status-badge.delivered { background-color: #D1FAE5; color: #065F46; }
        .status-badge.transit { background-color: #FEF3C7; color: #92400E; }
        .status-badge.processing { background-color: #DBEAFE; color: #1E40AF; }
        .status-badge.waiting { background-color: #E2E8F0; color: #475569; }
    </style>
</head>
<body>

    <div class="dashboard">
        <aside class="sidebar">
            <div class="profile">
                <div class="profile-circle">
                    <i class="far fa-user"></i>
                </div>
                <h2><?php echo htmlspecialchars($fullname); ?></h2>
            </div>
            
            <nav class="menu">
                <ul>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_home.php" class="menu-item"><i class="fas fa-th-large"></i> HOME</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_cart.php" class="menu-item "><i class="fas fa-shopping-bag"></i> CART</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_orders.php" class="menu-item active "><i class="fas fa-receipt"></i> ORDERS</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_settings.php" class="menu-item"><i class="fas fa-sliders-h"></i> SETTINGS</a></li>
                </ul>
            </nav>
            
            <button class="logout-btn" onclick="location.href='../Auth/logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
        </aside>

        <main class="content">
            <h1>Your Order</h1>
            <p class="subtitle">View the current status of your orders</p>

            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="orderSearch" placeholder="Search by the title or order id..." class="search-input">
            </div>

            <div class="table-master-container">
                <table class="order-table" id="orderTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Unit<br>Price</th>
                            <th>Quantity</th>
                            <th>Total<br>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders_list) > 0): ?>
                            <?php foreach ($orders_list as $order): 
                                // Tetapkan warna / class badge berdasarkan teks status di dalam database
                                $status_db = strtolower($order['status']);
                                $badge_class = 'waiting'; // Lalai
                                
                                if (strpos($status_db, 'deliver') !== false) {
                                    $badge_class = 'delivered';
                                } elseif (strpos($status_db, 'transit') !== false) {
                                    $badge_class = 'transit';
                                } elseif (strpos($status_db, 'process') !== false) {
                                    $badge_class = 'processing';
                                }
                            ?>
                                <tr class="data-row">
                                    <td>ORD-<?php echo str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($order['product']); ?></td>
                                    <td>RM <?php echo number_format($order['unit_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($order['quantity']); ?> Pcs</td>
                                    <td>RM <?php echo number_format($order['total_price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <tr id="noResultRow" class="no-result-row" style="<?php echo (count($orders_list) > 0) ? 'display: none;' : ''; ?>">
                            <td colspan="6">
                                <i class="fas fa-box-open"></i>
                                We couldn't find any orders matching this information.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        const orderSearch = document.getElementById('orderSearch');
        const dataRows = Array.from(document.querySelectorAll('#orderTable tbody tr.data-row'));
        const noResultRow = document.getElementById('noResultRow');

        function filterTableDisplay() {
            const searchValue = orderSearch.value.toLowerCase();
            let hasMatches = false;

            dataRows.forEach(row => {
                const orderId = row.cells[0].textContent.toLowerCase();
                const productTitle = row.cells[1].textContent.toLowerCase();

                if (orderId.includes(searchValue) || productTitle.includes(searchValue)) {
                    row.style.display = "";
                    hasMatches = true;
                } else {
                    row.style.display = "none";
                }
            });

            if (hasMatches) {
                noResultRow.style.display = "none";
            } else {
                noResultRow.style.display = "";
            }
        }

        orderSearch.addEventListener('input', filterTableDisplay);
    </script>
</body>
</html>