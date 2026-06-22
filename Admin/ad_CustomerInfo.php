<?php
session_start();
require_once '../db.php';

// 1. SECURITY RESTRICTION: Ensure only authorized Admins can enter this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

// 2. PROCESS FORM SUBMISSION (POST REQUEST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $fullname        = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $email           = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone           = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $membership_tier = mysqli_real_escape_string($conn, $_POST['membership_tier']);
    $plain_password  = trim($_POST['password']);

    if (empty($fullname) || empty($email) || empty($phone) || empty($membership_tier) || empty($plain_password)) {
        $_SESSION['flash_error'] = "Please fill in all the required fields.";
        header("Location: ad_CustomerInfo.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = "Invalid email format.";
        header("Location: ad_CustomerInfo.php");
        exit();
    }

    $check_email = "SELECT id FROM users WHERE email = '$email'";
    $email_result = $conn->query($check_email);

    if ($email_result->num_rows > 0) {
        $_SESSION['flash_error'] = "This email address is already in use by another user.";
        header("Location: ad_CustomerInfo.php");
        exit();
    }

    // 3. GENERATE CUSTOMER ID (#CUST-XXXX)
    $get_last_id = "SELECT id FROM users ORDER BY id DESC LIMIT 1";
    $id_result = $conn->query($get_last_id);
    
    $next_number = 1;
    if ($id_result->num_rows > 0) {
        $row = $id_result->fetch_assoc();
        $next_number = $row['id'] + 1;
    }
    
    $customer_id_str = "#CUST-" . str_pad($next_number, 4, "0", STR_PAD_LEFT);

    // 4. HASH PASSWORD USING BCRYPT
    $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);
    $role = 'customer';

    // 5. INSERT DATA 
    $insert_sql = "INSERT INTO users (customer_id_str, fullname, email, password, phone, role, membership_tier) 
                   VALUES ('$customer_id_str', '$fullname', '$email', '$hashed_password', '$phone', '$role', '$membership_tier')";

    if ($conn->query($insert_sql) === TRUE) {
        $log_message = "New Customer Registration Successful: " . $fullname . " (" . $customer_id_str . ") by admin.";
        $conn->query("INSERT INTO system_logs (log_message) VALUES ('$log_message')");

        $_SESSION['flash_success'] = "New customer registered successfully with ID: " . $customer_id_str;
    } else {
        $_SESSION['flash_error'] = "Database error failed to register customer.";
    }
    
    header("Location: ad_CustomerInfo.php");
    exit();
}

// --- GET REAL-TIME DATABASE VALUES ---

$total_registered_query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
$total_res = $conn->query($total_registered_query)->fetch_assoc();
$total_customers = $total_res['total'] ?? 0;

$vip_query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND LOWER(membership_tier) = 'vip'";
$vip_res = $conn->query($vip_query)->fetch_assoc();
$vip_customers = $vip_res['total'] ?? 0;

$regular_query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND LOWER(membership_tier) = 'regular'";
$regular_res = $conn->query($regular_query)->fetch_assoc();
$regular_customers = $regular_res['total'] ?? 0;

$new_query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND LOWER(membership_tier) = 'new'";
$new_res = $conn->query($new_query)->fetch_assoc();
$new_customers = $new_res['total'] ?? 0;

// Logic for Search & Membership Filter
$search_keyword = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$tier_filter = isset($_GET['tier']) ? mysqli_real_escape_string($conn, trim($_GET['tier'])) : 'all';

$sql_select_users = "SELECT * FROM users WHERE role = 'customer'";

if (!empty($search_keyword)) {
    $sql_select_users .= " AND (fullname LIKE '%$search_keyword%' OR email LIKE '%$search_keyword%' OR phone LIKE '%$search_keyword%' OR customer_id_str LIKE '%$search_keyword%')";
}

if ($tier_filter !== 'all') {
    $sql_select_users .= " AND LOWER(membership_tier) = LOWER('$tier_filter')";
}

$sql_select_users .= " ORDER BY id DESC";
$customers_list = $conn->query($sql_select_users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Customer Information</title>
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

        .user-info {
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

        .nav-links li.active a,
        .nav-links li a:hover {
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
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .main-content::-webkit-scrollbar {
            display: none;
        }

        .page-title {
            font-size: 2.5rem;
            color: #0E2C46;
            margin-bottom: 20px;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5);
        }

        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .alert-success { background-color: #bbf7d0; color: #166534; border: 2px solid #22c55e; }
        .alert-error { background-color: #fecaca; color: #991b1b; border: 2px solid #ef4444; }

        /* --- CUSTOMER COUNTER STATS --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #FDF5E6;
            border: 2px solid #0E2C46;
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #0E2C46;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-card h4 { font-size: 0.95rem; color: #555; }
        .stat-card p { font-size: 1.6rem; font-weight: bold; }
        .stat-card i { font-size: 1.8rem; color: #FC9D01; background: #0E2C46; padding: 10px; border-radius: 50%; }

        /* --- MANUAL REGISTRATION FORM STYLE --- */
        .admin-form-panel {
            background-color: #FDF5E6;
            border: 2px solid #0E2C46;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            color: #0E2C46;
        }

        .admin-form-panel h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
            border-bottom: 2px solid rgba(14, 44, 70, 0.1);
            padding-bottom: 5px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: bold;
            font-size: 1.05rem;
        }

        .form-control {
            padding: 10px;
            border: 2px solid #0E2C46;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            background-color: #ffffff;
        }

        .form-control:focus {
            border-color: #FC9D01;
        }

        .btn-submit {
            padding: 10px 25px;
            background-color: #0E2C46;
            color: white;
            border: 2px solid #0E2C46;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: bold;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            background-color: #FC9D01;
            color: #0E2C46;
            border-color: #0E2C46;
        }

        /* --- FILTER & SEARCH CONTROLS --- */
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
        }

        .tab-btn {
            padding: 8px 16px;
            border: 2px solid #0E2C46;
            background: white;
            color: #0E2C46;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: bold;
            transition: all 0.2s;
            text-decoration: none;
        }

        .tab-btn.active, .tab-btn:hover {
            background: #0E2C46;
            color: white;
        }

        .search-form {
            display: flex;
            width: 100%;
            max-width: 350px;
        }

        .search-box {
            display: flex;
            background: white;
            border: 2px solid #0E2C46;
            border-radius: 25px;
            overflow: hidden;
            padding: 2px;
            width: 100%;
        }

        .search-box input {
            border: none;
            padding: 8px 15px;
            outline: none;
            flex-grow: 1;
            font-size: 0.95rem;
        }

        .search-box button {
            background: #0E2C46;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 23px;
            cursor: pointer;
        }

        /* --- REGISTRY TABLE PANEL --- */
        .table-panel {
            background-color: #FDF5E6;
            border: 2px solid #0E2C46;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            color: #0E2C46;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .table-panel::-webkit-scrollbar {
            display: none;
        }

        .customer-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 1.05rem;
        }

        .customer-table th {
            background-color: #0E2C46;
            color: white;
            padding: 12px 15px;
            font-size: 1.1rem;
        }

        .customer-table td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(14, 44, 70, 0.2);
            background-color: rgba(255, 255, 255, 0.3);
        }

        .customer-table tr:hover td {
            background-color: rgba(252, 157, 1, 0.1);
        }

        .tier-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: bold;
            text-align: center;
            display: inline-block;
            text-transform: uppercase;
        }
        .tier-badge.vip { background-color: #fef08a; color: #854d0e; border: 1px solid #ca8a04; }
        .tier-badge.regular { background-color: #bfdbfe; color: #1e40af; border: 1px solid #3b82f6; }
        .tier-badge.new { background-color: #bbf7d0; color: #166534; border: 1px solid #22c55e; }

        .action-cell {
            display: flex;
            gap: 8px;
        }

        .btn-table {
            padding: 6px 12px;
            border: 1px solid #0E2C46;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: bold;
            transition: all 0.2s;
        }

        .btn-table.history { background-color: #0E2C46; color: white; }
        .btn-table.history:hover { background-color: #1a446c; }
        
        .btn-table.edit { background-color: #FC9D01; color: #0E2C46; }
        .btn-table.edit:hover { background-color: #e08b00; }

        .pagination {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 5px;
        }

        .page-node {
            padding: 6px 12px;
            border: 1px solid #0E2C46;
            background: white;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
        }

        .page-node.active, .page-node:hover {
            background-color: #0E2C46;
            color: white;
        }
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
                    <p><?php echo isset($_SESSION['fullname']) ? htmlspecialchars(strtoupper($_SESSION['fullname'])) : 'ADMIN STAFF'; ?></p>
                </div>
            </div>

            <ul class="nav-links">
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_DashBoard.php"><i class="fas fa-chart-line"></i> DASHBOARD STATUS</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_OrderInfo.php"><i class="fas fa-shopping-cart"></i> ORDER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_ManageBook.php"><i class="fas fa-book"></i> MANAGE BOOK</a></li>
                <li class="active"><a href="/DreamBoundBookStrore_system/Admin/ad_CustomerInfo.php"><i class="fas fa-users"></i> CUSTOMER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_settings.php"><i class="fas fa-sliders-h"></i> SETTING</a></li>
            </ul>

            <div class="logout-container">
                <button class="btn-logout" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
            </div>
        </nav>

        <main class="main-content">
            <h1 class="page-title">Customer Information Registry</h1>

            <?php if(isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card">
                    <div>
                        <h4>Total Registered</h4>
                        <p><?php echo number_format($total_customers); ?> Users</p>
                    </div>
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-card">
                    <div>
                        <h4>Regular Members</h4>
                        <p><?php echo number_format($regular_customers); ?> Club</p>
                    </div>
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-card">
                    <div>
                        <h4>VIP Customers</h4>
                        <p><?php echo number_format($vip_customers); ?> Buyers</p>
                    </div>
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-card">
                    <div>
                        <h4>New Members</h4>
                        <p><?php echo number_format($new_customers); ?> Tier</p>
                    </div>
                    <i class="fas fa-user-plus"></i>
                </div>
            </section>

            <section class="admin-form-panel">
                <h3>Add New Customer Manually</h3>
                
                <form action="ad_CustomerInfo.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name:</label>
                            <input type="text" name="fullname" class="form-control" placeholder="Enter full name" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address:</label>
                            <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
                        </div>

                        <div class="form-group">
                            <label>Contact Number:</label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. +60123456789" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Membership Tier:</label>
                            <select name="membership_tier" class="form-control" required>
                                <option value="">-- Select Membership --</option>
                                <option value="Regular">Regular</option>
                                <option value="VIP">VIP</option>
                                <option value="New">New</option>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label>Temporary Password:</label>
                            <input type="password" name="password" class="form-control" placeholder="Create temporary login security password" required>
                            <small style="color: #666; font-style: italic;">Provide this security password to the client for their first login milestone.</small>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 10px;">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Register Customer
                        </button>
                    </div>
                </form>
            </section>

            <section class="control-panel">
                <div class="filter-tabs">
                    <a href="ad_CustomerInfo.php?tier=all&search=<?php echo urlencode($search_keyword); ?>" class="tab-btn <?php echo $tier_filter === 'all' ? 'active' : ''; ?>">All Customers</a>
                    <a href="ad_CustomerInfo.php?tier=vip&search=<?php echo urlencode($search_keyword); ?>" class="tab-btn <?php echo $tier_filter === 'vip' ? 'active' : ''; ?>">VIP Tier</a>
                    <a href="ad_CustomerInfo.php?tier=regular&search=<?php echo urlencode($search_keyword); ?>" class="tab-btn <?php echo $tier_filter === 'regular' ? 'active' : ''; ?>">Regular</a>
                    <a href="ad_CustomerInfo.php?tier=new&search=<?php echo urlencode($search_keyword); ?>" class="tab-btn <?php echo $tier_filter === 'new' ? 'active' : ''; ?>">New Members</a>
                </div>
                
                <form action="ad_CustomerInfo.php" method="GET" class="search-form">
                    <input type="hidden" name="tier" value="<?php echo htmlspecialchars($tier_filter); ?>">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search Name, Email, ID or Phone..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                        <button type="submit" title="Search"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </section>

            <section class="table-panel">
                <table class="customer-table">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Contact Number</th>
                            <th>Membership</th>
                            <th>Administrative Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($customers_list && $customers_list->num_rows > 0): ?>
                            <?php while($row = $customers_list->fetch_assoc()): 
                                $tier_class = strtolower($row['membership_tier']);
                                if ($tier_class !== 'vip' && $tier_class !== 'regular' && $tier_class !== 'new') {
                                    $tier_class = 'regular'; 
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['customer_id_str']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td>
                                        <span class="tier-badge <?php echo $tier_class; ?>">
                                            <?php if($tier_class === 'vip'): ?><i class="fas fa-crown"></i><?php endif; ?>
                                            <?php echo htmlspecialchars($row['membership_tier']); ?>
                                        </span>
                                    </td>
                                    <td class="action-cell">
                                        <button class="btn-table history" onclick="viewHistory('<?php echo addslashes($row['fullname']); ?>')"><i class="fas fa-history"></i> History</button>
                                        <button class="btn-table edit" onclick="editCustomer('<?php echo $row['customer_id_str']; ?>')"><i class="fas fa-user-edit"></i> Edit</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px; font-weight: bold;">No customer information found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination">
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
        function viewHistory(customerName) {
            alert("Loading full Dreambound purchase history logs for: " + customerName);
        }

        function editCustomer(customerId) {
            let updatedPhone = prompt("Modify Contact Number for " + customerId + ":");
            if (updatedPhone) {
                alert("Customer account " + customerId + " successfully updated with new contact: " + updatedPhone);
            }
        }

        function confirmLogout() {
            if (confirm("Are you sure you want to log out from the admin platform?")) {
                window.location.href = "../logout.php";
            }
        }
    </script>
</body> 
</html>