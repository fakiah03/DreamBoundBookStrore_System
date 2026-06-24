<?php
session_start();
require_once '../db.php'; // 

// 1. SECURITY RESTRICTION: Ensure only authorized Admins can enter this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: Auth/login.php");
    exit();
}

// 2. QUERY FOR ROW 1: CORE STAT CARDS (use created_at for time-sensitive data like sales/orders, and role/status for users/orders)

// card 1: Total Sales 
$res_sales = $conn->query("SELECT SUM(total_amount) as total FROM orders");
$row_sales = $res_sales->fetch_assoc();
$total_sales = $row_sales['total'] ?? 0;

// card 2: Active Orders (status = 'pending')
$res_orders = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$row_orders = $res_orders->fetch_assoc();
$active_orders = $row_orders['total'] ?? 0;

// card 3: Total Books
$res_books = $conn->query("SELECT COUNT(*) as total FROM books");
$row_books = $res_books->fetch_assoc();
$total_books = $row_books['total'] ?? 0;

// card 4: Registered Users (using role 'customer')
$res_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$row_users = $res_users->fetch_assoc();
$total_customers = $row_users['total'] ?? 0;


// 3. QUERY FOR ROW 2: BEST SELLER, LOWER STOCK, WORST SELLER (use sold_qty for sales performance and stock for inventory status)
// Best Seller: pick 2 best-selling books based on sold_qty
$best_result = $conn->query("SELECT * FROM books ORDER BY sold_qty DESC LIMIT 2");

// Lower Stock: pick 2 books with the lowest stock (stock <= 10)
$low_stock_result = $conn->query("SELECT * FROM books WHERE stock <= 10 ORDER BY stock ASC LIMIT 2");

// Worst Seller: pick 2 books with the lowest sales (sold_qty)
$worst_result = $conn->query("SELECT * FROM books ORDER BY sold_qty ASC LIMIT 2");


// 4. QUERY FOR ROW 3: STRUCTURE OF ANALYTICS & KPI (using created_at)

$wk1_res = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE WEEK(created_at, 1) = WEEK(NOW(), 1) - 3");
$wk1_sales = $wk1_res->fetch_assoc()['total'] ?? 0;

$wk2_res = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE WEEK(created_at, 1) = WEEK(NOW(), 1) - 2");
$wk2_sales = $wk2_res->fetch_assoc()['total'] ?? 0;

$wk3_res = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE WEEK(created_at, 1) = WEEK(NOW(), 1) - 1");
$wk3_sales = $wk3_res->fetch_assoc()['total'] ?? 0;

$wk4_res = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE WEEK(created_at, 1) = WEEK(NOW(), 1)");
$wk4_sales = $wk4_res->fetch_assoc()['total'] ?? 0;

// calculate KPI achievement percentage (assuming target is RM15,000 for the month)
$kpi_target = 15000;
$kpi_percentage = ($total_sales > 0) ? ($total_sales / $kpi_target) * 100 : 0;
if ($kpi_percentage > 100) $kpi_percentage = 100; // Maksimum bar 100%


// 5. QUERY FOR DYNAMIC CONTENT IN ROW 4 & 5: Vouchers, Reviews, System Logs, Staff List (use status for vouchers/reviews and role for staff)

// pick the 5 newest active vouchers to display in the voucher management panel
$vouchers_result = $conn->query("SELECT * FROM vouchers WHERE status = 'active' ORDER BY id DESC LIMIT 5");
// Retrieve the list of pending reviews that have not yet been processed, along with the user's name and the book title.
$reviews_result = $conn->query("SELECT r.*, u.fullname, b.title FROM reviews r 
                                JOIN users u ON r.user_id = u.id 
                                JOIN books b ON r.book_id = b.id 
                                WHERE r.status = 'pending'");

// Retrieve system logs from the system_logs table
$logs_result = $conn->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 3");

// Retrieve the list of active staff members (clerk & manager) to be displayed in the bottom panel
$staff_list_result = $conn->query("SELECT fullname, role FROM users WHERE role IN ('clerk', 'manager', 'admin') ORDER BY role DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">

    <style>
        /* Base Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Englebert', cursive, sans-serif;
        }

        body {
            background-color: #FC9D01; 
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        /* --- SIDEBAR STYLE --- */
        .sidebar {
            width: 280px;
            background-color: #0E2C46; 
            color: white;
            display: flex;
            flex-direction: column;
            padding: 30px 0;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }

        .profile-section {
            text-align: center;
            padding: 0 20px 25px 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .logo-img {
            width: 150px;
            margin-bottom: 10px;
        }

        .profile-section h3 {
            font-size: 1.4rem;
            letter-spacing: 1px;
            color: #ffffff;
        }

        .subtitle {
            font-size: 0.8rem;
            color: #FC9D01;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }

        .profile-info {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .nav-links {
            list-style: none;
            margin-top: 25px;
            flex-grow: 1;
            padding: 0 15px;
        }

        .nav-links li {
            margin-bottom: 8px;
        }

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
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-links li a:hover, 
        .nav-links li.active a {
            background: #FC9D01;
            color: #0E2C46;
            font-weight: bold;
            transform: translateX(5px);
        }

        .logout-container {
            padding: 0 15px;
        }

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
            gap: 10px;
        }

        .btn-logout:hover {
            background: #FC9D01;
            color: #0E2C46;
        }

        /* --- MAIN CONTENT AREA --- */
        .main-content {
            flex-grow: 1;
            background-color: #FC9D01;
            padding: 30px;
            overflow-y: auto;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .search-bar {
            display: flex;
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 25px;
            overflow: hidden;
            padding: 2px;
            border: 2px solid #0E2C46;
        }

        .search-bar input {
            border: none;
            padding: 10px 20px;
            flex-grow: 1;
            outline: none;
            font-size: 1rem;
        }

        .search-bar button {
            padding: 10px 20px;
            border: none;
            background: #0E2C46;
            color: white;
            cursor: pointer;
            border-radius: 23px;
        }

        .page-title {
            font-size: 2.5rem;
            color: #0E2C46;
            margin-bottom: 20px;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5);
        }

        /* --- 4 CORE STAT CARDS --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #FDF5E6;
            border: 4px solid #0E2C46;
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #0E2C46;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 2.3rem;
            color: #FC9D01;
            background: #0E2C46;
            padding: 10px;
            border-radius: 12px;
            width: 60px;
            text-align: center;
        }

        .stat-info h4 {
            font-size: 0.95rem;
            color: #555;
        }

        .stat-info p {
            font-size: 1.6rem;
            font-weight: bold;
        }

        /* --- ROW2: BEST SELLER, LOWER STOCK, WORST SELLER --- */
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .insight-card {
            background-color: #0E2C46;
            color: white;
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .insight-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding-bottom: 8px;
        }
        .insight-card.best-seller h3 { color: #FC9D01; }
        .insight-card.lower-stock h3 { color: #ff4d4d; }
        .insight-card.worst-seller h3 { color: #a3a3a3; }

        .book-snippet {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .book-snippet img {
            width: 40px;
            height: 55px;
            object-fit: cover;
            border-radius: 4px;
        }

        .book-details {
            flex-grow: 1;
            overflow: hidden;
        }

        .book-details h5 { 
            font-size: 1.05rem; 
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .book-details p { font-size: 0.85rem; color: #cbd5e1; }

        .badge {
            margin-left: auto;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
            white-space: nowrap;
        }
        .badge.qty { background-color: #FC9D01; color: #0E2C46; }
        .badge.alert { background-color: #ff4d4d; color: white; }
        .badge.slow { background-color: rgba(255,255,255,0.2); color: white; }

        /* --- ROW3: SALES ANALYTICS & KPI TRACKER --- */
        .analytics-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .panel-box {
            background: #FDF5E6;
            border: 2px solid #0E2C46;
            border-radius: 15px;
            padding: 20px;
            color: #0E2C46;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .panel-box h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #0E2C46;
            padding-bottom: 5px;
        }

        /* Mock Chart CSS Graphics */
        .mock-chart {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            height: 180px;
            padding: 10px 20px;
            background: rgba(14, 44, 70, 0.05);
            border-radius: 10px;
            border-left: 3px solid #0E2C46;
            border-bottom: 3px solid #0E2C46;
            position: relative;
        }

        .chart-bar {
            position: relative;
            width: 40px;
            background: #0E2C46;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
            text-align: center;
            transition: background 0.3s;
        }

        .chart-bar:hover { background: #FC9D01; }
        .chart-bar span {
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.85rem;
            font-weight: bold;
            white-space: nowrap;
        }
        .chart-label {
            font-size: 0.9rem;
            margin-top: 8px;
            color: #0E2C46;
            font-weight: bold;
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
        }

        /* KPI Progress Tracker */
        .kpi-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            justify-content: center;
            height: 180px;
        }

        .progress-wrapper h5 { font-size: 1.1rem; margin-bottom: 5px; }
        .progress-bar-bg {
            width: 100%;
            background: #cbd5e1;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #0E2C46;
        }
        .progress-bar-fill {
            height: 100%;
            background: #0E2C46;
            border-radius: 10px;
            transition: width 0.5s ease-in-out;
        }

        .sales-summary {
            font-size: 0.95rem;
            text-align: center;
        }
        /* --- ROW 4: VOUCHERS & STAFF ACCESS SYSTEM --- */
        .management-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .voucher-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }

        .voucher-form input, .voucher-form select {
            padding: 10px;
            border: 2px solid #0E2C46;
            border-radius: 8px;
            font-size: 1rem;
        }

        .btn-action {
            background: #0E2C46;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-action:hover { background: #1a446c; }
        .staff-btn {
            width: 100%;
            margin-bottom: 15px;
        }
        .status.active {
            color: #22c55e;
            font-weight: bold;
        }

        /* Tables/Lists styling inside panels */
        .data-list {
            list-style: none;
            max-height: 160px;
            overflow-y: auto;
        }

        .data-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: rgba(0,0,0,0.04);
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .role-tag {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            color: white;
            font-weight: bold;
        }
        .role-tag.super { background: #0E2C46; }
        .role-tag.clerk { background: #6b7280; }
        .role-tag.manager { background: #eab308; }

        /* --- ROW 5: REVIEWS & SYSTEM LOGS --- */
        .system-row {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .review-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 180px;
            overflow-y: auto;
        }

        .review-item {
            background: white;
            border: 1px solid #0E2C46;
            padding: 10px;
            border-radius: 8px;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 5px;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 8px;
            justify-content: flex-end;
        }

        .btn-mini {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .btn-mini.approve { background: #22c55e; color: white; }
        .btn-mini.reject { background: #ef4444; color: white; }

        /* System Activity Log Box */
        .log-terminal {
            background: #0f172a;
            color: #38bdf8;
            font-family: monospace !important;
            padding: 15px;
            border-radius: 10px;
            height: 180px;
            overflow-y: auto;
            font-size: 0.85rem;
            line-height: 1.5;
            border: 2px solid #0E2C46;
        }
        .log-line { margin-bottom: 6px; }
        .log-time { color: #f43f5e; margin-right: 5px; }
    </style>
</head>
<body>

    <div class="container">
        <nav class="sidebar">
            <div class="profile-section">
                <img src="../img/logo1.png" alt="Dreambound Logo" class="logo-img">
                <h3>DREAMBOUND</h3>
                <p class="subtitle">BOOKSTORE</p>
                <div class="profile-info">
                    <p><strong>ADMIN PORTAL</strong></p>
                    <p><?php echo isset($_SESSION['fullname']) ? strtoupper($_SESSION['fullname']) : 'ADMIN TERM'; ?></p>
                </div>
            </div>

            <ul class="nav-links">
                <li class="active"><a href="/DreamBoundBookStrore_system/Admin/ad_DashBoard.php"><i class="fas fa-chart-line"></i> DASHBOARD STATUS</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_OrderInfo.php"><i class="fas fa-shopping-cart"></i> ORDER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_ManageBook.php"><i class="fas fa-book"></i> MANAGE BOOK</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_CustomerInfo.php"><i class="fas fa-users"></i> CUSTOMER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_settings.php"><i class="fas fa-sliders-h"></i> SETTING</a></li>
            </ul>

            <div class="logout-container">
                <button class="btn-logout" onclick="confirmLogout()" title="Log Out">
                    <i class="fas fa-sign-out-alt"></i> LOG OUT
                </button>
            </div>
        </nav>
        
        <main class="main-content">
            <header class="top-header">
                <div class="search-bar">
                    <input type="text" placeholder="Search operational dashboard components...">
                    <button title="Search"><i class="fas fa-search"></i></button>
                </div>
            </header>

            <h1 class="page-title">Admin Workstation Terminal</h1>

            <section class="dashboard-grid">
                <div class="stat-card">
                    <i class="fas fa-wallet"></i>
                    <div class="stat-info">
                        <h4>Total Sales</h4>
                        <p>RM <?php echo number_format($total_sales, 2); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-truck"></i>
                    <div class="stat-info">
                        <h4>Active Orders</h4>
                        <p><?php echo $active_orders; ?> Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-book-open"></i>
                    <div class="stat-info">
                        <h4>Total Books</h4>
                        <p><?php echo $total_books; ?> Items</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-check"></i>
                    <div class="stat-info">
                        <h4>Registered Users</h4>
                        <p><?php echo $total_customers; ?> Customers</p>
                    </div>
                </div>
            </section>

            <section class="insights-grid">
                <div class="insight-card best-seller">
                    <h3><i class="fas fa-crown"></i> Best Seller</h3>
                    <?php if ($best_result && $best_result->num_rows > 0): ?>
                        <?php while($book = $best_result->fetch_assoc()): ?>
                        <div class="book-snippet">
                            <img src="../<?php echo !empty($book['book_img']) ? $book['book_img'] : 'img/default-book.jpg'; ?>" alt="Cover">
                            <div class="book-details">
                                <h5><?php echo htmlspecialchars($book['title']); ?></h5>
                                <p><?php echo htmlspecialchars($book['genre']); ?></p>
                            </div>
                            <span class="badge qty"><?php echo $book['sold_qty']; ?> Sold</span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="font-size:0.9rem; color:#ccc;">Tiada data jualan.</p>
                    <?php endif; ?>
                </div>

                <div class="insight-card lower-stock">
                    <h3><i class="fas fa-exclamation-triangle"></i> Lower Stock</h3>
                    <?php if ($low_stock_result && $low_stock_result->num_rows > 0): ?>
                        <?php while($book = $low_stock_result->fetch_assoc()): ?>
                        <div class="book-snippet">
                            <img src="../<?php echo !empty($book['book_img']) ? $book['book_img'] : 'img/default-book.jpg'; ?>" alt="Cover">
                            <div class="book-details">
                                <h5><?php echo htmlspecialchars($book['title']); ?></h5>
                                <p>Stok semasa: <?php echo $book['stock']; ?></p>
                            </div>
                            <span class="badge alert"><?php echo $book['stock']; ?> Left</span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="font-size:0.9rem; color:#ccc;">All stock is sufficient. (>10).</p>
                    <?php endif; ?>
                </div>

                <div class="insight-card worst-seller">
                    <h3><i class="fas fa-chart-line fa-flip-vertical"></i> Worst Seller</h3>
                    <?php if ($worst_result && $worst_result->num_rows > 0): ?>
                        <?php while($book = $worst_result->fetch_assoc()): ?>
                        <div class="book-snippet">
                            <img src="../<?php echo !empty($book['book_img']) ? $book['book_img'] : 'img/default-book.jpg'; ?>" alt="Cover">
                            <div class="book-details">
                                <h5><?php echo htmlspecialchars($book['title']); ?></h5>
                                <p><?php echo htmlspecialchars($book['genre']); ?></p>
                            </div>
                            <span class="badge slow"><?php echo $book['sold_qty']; ?> Sold</span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="font-size:0.9rem; color:#ccc;">No inventory data available.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="analytics-row">
                <div class="panel-box">
                    <h3><i class="fas fa-chart-bar"></i> Weekly Sales Analytics Trend</h3>
                    <div class="mock-chart">
                        <div class="chart-bar" style="height: <?php echo min(max(($wk1_sales/200), 15), 100); ?>%;">
                            <span>RM<?php echo number_format($wk1_sales/1000, 1); ?>k</span>
                            <div class="chart-label">Wk 1</div>
                        </div>
                        <div class="chart-bar" style="height: <?php echo min(max(($wk2_sales/200), 15), 100); ?>%;">
                            <span>RM<?php echo number_format($wk2_sales/1000, 1); ?>k</span>
                            <div class="chart-label">Wk 2</div>
                        </div>
                        <div class="chart-bar" style="height: <?php echo min(max(($wk3_sales/200), 15), 100); ?>%;">
                            <span>RM<?php echo number_format($wk3_sales/1000, 1); ?>k</span>
                            <div class="chart-label">Wk 3</div>
                        </div>
                        <div class="chart-bar" style="height: <?php echo min(max(($wk4_sales/200), 15), 100); ?>%;">
                            <span>RM<?php echo number_format($wk4_sales/1000, 1); ?>k</span>
                            <div class="chart-label">Wk 4</div>
                        </div>
                    </div>
                </div>

                <div class="panel-box">
                    <h3><i class="fas fa-bullseye"></i> Monthly Sales KPI Target</h3>
                    <div class="kpi-container">
                        <div class="progress-wrapper">
                            <h5>Target Reach Achievement (<?php echo number_format($kpi_percentage, 0); ?>%)</h5>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo $kpi_percentage; ?>%;"></div>
                            </div>
                        </div>
                        <p class="sales-summary">
                            <strong>RM <?php echo number_format($total_sales, 2); ?></strong> generated out of RM <?php echo number_format($kpi_target); ?> corporate store objective.
                        </p>
                    </div>
                </div>
            </section>

            <section class="management-row">
                <div class="panel-box">
                    <h3><i class="fas fa-tags"></i> Coupon & Marketing Voucher Tool</h3>
                    <form method="POST" action="process_voucher.php" class="voucher-form">
                        <input type="text" name="voucher_code" placeholder="Enter Voucher Code (e.g. RAYA20)" required>
                        <label for="discount-type">Select Discount Type</label>
                        <select id="discount-type" name="voucher_type">
                            <option value="percentage">Discount Percentage</option>
                            <option value="flat">Flat Rate RM Cut</option>
                        </select>
                        <input type="number" name="voucher_value" placeholder="Value (e.g. 20)" min="0.01" step="0.01" required>
                        <button type="submit" name="add_voucher" class="btn-action">Generate Promotion</button>
                    </form>
                    <ul class="data-list">
                        <?php if ($vouchers_result && $vouchers_result->num_rows > 0): ?>
                            <?php while($v = $vouchers_result->fetch_assoc()): ?>
                                <li class="data-item">
                                    <span><strong><?php echo htmlspecialchars($v['code']); ?></strong> - <?php echo ($v['type'] == 'percentage') ? $v['value'].'% off' : 'RM'.$v['value'].' off'; ?></span>
                                    <span class="status active"><?php echo ucfirst($v['status']); ?></span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="data-item">No active vouchers are available at this time.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="panel-box">
                    <h3><i class="fas fa-user-shield"></i> System Staff Roles & Access Authorization</h3>
                    <form method="POST" action="process_staff.php" class="voucher-form staff-form">
                        <input type="text" name="staff_name" placeholder="Full Name" required>
                        <input type="email" name="staff_email" placeholder="Email Address (e.g. staff@dreambound.com)" required>
                        <input type="text" name="staff_phone" placeholder="Contact Number" required>
                        <input type="password" name="staff_password" placeholder="Create Access Password" required>
                        <label for="staff-role">Assign Role</label>
                        <select id="staff-role" name="staff_role">
                            <option value="clerk">Stock Clerk</option>
                            <option value="manager">Manager</option>
                        </select>
                        <button type="submit" class="btn-action staff-btn">Authorize New Member</button>
                    </form>
                    <ul class="data-list">
                        <?php if ($staff_list_result && $staff_list_result->num_rows > 0): ?>
                            <?php while($staff = $staff_list_result->fetch_assoc()): ?>
                                <li class="data-item">
                                    <span><?php echo htmlspecialchars($staff['fullname']); ?></span>
                                    <?php if ($staff['role'] === 'admin'): ?>
                                        <span class="role-tag super">Super Admin</span>
                                    <?php elseif ($staff['role'] === 'manager'): ?>
                                        <span class="role-tag manager">Manager</span>
                                    <?php else: ?>
                                        <span class="role-tag clerk">Stock Clerk</span>
                                    <?php endif; ?>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="data-item">No staff data found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>

            <section class="system-row">
                <div class="panel-box">
                    <h3><i class="fas fa-comments"></i> Book Reviews & Ratings Moderation</h3>
                    <div class="review-box">
                        <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
                            <?php while($rev = $reviews_result->fetch_assoc()): ?>
                            <div class="review-item" style="margin-bottom: 8px;">
                                <div class="review-header">
                                    <span><strong><?php echo htmlspecialchars($rev['fullname']); ?></strong> - <i><?php echo htmlspecialchars($rev['title']); ?></i></span>
                                    <span><?php echo str_repeat('⭐', $rev['rating']); ?></span>
                                </div>
                                <p>"<?php echo htmlspecialchars($rev['comment']); ?>"</p>
                                <div class="review-actions">
                                    <button class="btn-mini approve" onclick="location.href='approve_review.php?id=<?php echo $rev['id']; ?>'">Approve</button>
                                    <button class="btn-mini reject" onclick="location.href='reject_review.php?id=<?php echo $rev['id']; ?>'">Delete</button>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="font-size:0.9rem; color:#555;">No new customer reviews to moderate.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel-box">
                    <h3><i class="fas fa-terminal"></i> Live Security & Activity System Logs</h3>
                        <div class="log-terminal" id="log-box">
                        <div class="log-line"><span class="log-time">[...]:</span> Connecting to log terminal...</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
    function loadLiveLogs() {
        fetch('get_live_logs.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error 404: The get_live_logs.php file could not be found.');
                }
                return response.text();
            })
            .then(data => {
                const logBox = document.getElementById('log-box');
                if (logBox) {
                    logBox.innerHTML = data;
                }
            })
            .catch(error => {
                console.error('Log Issue:', error);
                document.getElementById('log-box').innerHTML = 
                    '<div class="log-line" style="color:red;">[ERROR]: ' + error.message + '</div>';
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadLiveLogs();
        setInterval(loadLiveLogs, 3000);
    });

    function confirmLogout() {
        if (confirm("Are you sure you want to log out?")) {
            window.location.href = "../logout.php"; 
        }
    }
</script>
</body>
</html>