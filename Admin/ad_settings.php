<?php
session_start();
require_once '../db.php'; 

// 1. SECUTY CHECK: makesure only logged-in admins can access this page. If not, redirect to login page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$alert_message = "";

// check if store_settings table has at least one row, if not, insert default settings. This ensures that the settings page always has data to work with and prevents errors when trying to access non-existent settings.
$check_empty = $conn->query("SELECT store_status FROM store_settings LIMIT 1");
if (!$check_empty || $check_empty->num_rows == 0) {
    $conn->query("INSERT INTO store_settings (id, store_status, maintenance_mode, ship_semenanjung, ship_borneo, store_region) VALUES (1, 'open', 'inactive', 4.50, 8.50, 'Malaysia (MYR - RM)')");
}

// 3. PROCESS FORM SUBMISSIONS: Handle updates for both admin profile and store settings based on which form is submitted. Each form has a hidden input to indicate its purpose, allowing the same PHP block to process both types of updates without confusion.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ACTION 1: UPDATE ADMIN PROFILE & CREDENTIALS
    if (isset($_POST['action_profile'])) {
        $fullname = mysqli_real_escape_string($conn, trim($_POST['adminName']));
        $email = mysqli_real_escape_string($conn, trim($_POST['adminEmail']));
        $curr_pass = trim($_POST['currPassword']);
        $new_pass = trim($_POST['newPassword']);
        
        if (!empty($fullname) && !empty($email)) {
            // if admin need to change  their password, the system will verify the current password first before allowing the update. This adds an extra layer of security to prevent unauthorized changes to the admin account.
            if (!empty($new_pass)) {
                // check current password against the database. If it matches, proceed to update the password along with the name and email.
                $pass_check = $conn->query("SELECT password FROM users WHERE id = '$admin_id'");
                $user_data = $pass_check->fetch_assoc();
                
                if (password_verify($curr_pass, $user_data['password'])) {
                    $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                    $conn->query("UPDATE users SET fullname = '$fullname', email = '$email', password = '$hashed_new_pass' WHERE id = '$admin_id'");
                    $alert_message = "Administrative Account Sync Completed!\\nProfile for \\\"" . $fullname . "\\\" and security password updated securely.";
                } else {
                    $alert_message = "Error: Current password verification failed!";
                }
            } else {
                // if admin does not want to change the password, only update the name and email
                $conn->query("UPDATE users SET fullname = '$fullname', email = '$email' WHERE id = '$admin_id'");
                $_SESSION['fullname'] = $fullname; // update the admin's name in the session
                $alert_message = "Administrative Account Sync Completed!\\nProfile for \\\"" . $fullname . "\\\" has been updated securely.";
            }
        }
    }

    // Action 2: Update store operations and shipping rate.
    if (isset($_POST['action_store'])) {
        $store_status = isset($_POST['storeStatus']) ? 'open' : 'closed';
        $maintenance_mode = isset($_POST['maintenanceStatus']) ? 'active' : 'inactive';
        $ship_semenanjung = mysqli_real_escape_string($conn, $_POST['shipSemenanjung']);
        $ship_borneo = mysqli_real_escape_string($conn, $_POST['shipBorneo']);
        $store_region = mysqli_real_escape_string($conn, $_POST['storeRegion']);
        
        // update the store settings in the database based on the form input. The system will then provide feedback on the new store status and maintenance mode to confirm that the changes have been applied successfully.
        $update_store = $conn->query("UPDATE store_settings SET 
            store_status = '$store_status', 
            maintenance_mode = '$maintenance_mode', 
            ship_semenanjung = '$ship_semenanjung', 
            ship_borneo = '$ship_borneo', 
            store_region = '$store_region' 
            WHERE 1 LIMIT 1");
            
        if ($update_store) {
            $status_txt = ($store_status === 'open') ? 'OPEN' : 'CLOSED';
            $maint_txt = ($maintenance_mode === 'active') ? 'ACTIVE' : 'INACTIVE';
            $alert_message = "Store Rules Deployed Successfully!\\nStore Status: " . $status_txt . "\\nMaintenance Mode: " . $maint_txt;
        }
    }
}

// 4. Fetch the latest data from the database (the 'username' column has been removed as it does not exist in the database).
$admin_query = $conn->query("SELECT fullname, email FROM users WHERE id = '$admin_id'");
$admin_info = $admin_query->fetch_assoc();

$store_query = $conn->query("SELECT * FROM store_settings LIMIT 1");
$store_info = $store_query->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Setting</title>
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
        }

        .page-title {
            font-size: 2.5rem;
            color: #0E2C46;
            margin-bottom: 25px;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5);
        }

        /* --- SETTINGS GRID SYSTEM --- */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            align-items: start;
        }

        .settings-card {
            background-color: #FDF5E6;
            border: 2px solid #0E2C46;
            border-radius: 15px;
            padding: 25px;
            color: #0E2C46;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        .settings-card h3 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #0E2C46;
            padding-bottom: 8px;
        }

        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 1.05rem;
            font-weight: bold;
        }

        .form-group input, .form-group select {
            padding: 10px 14px;
            border: 2px solid #0E2C46;
            border-radius: 6px;
            font-size: 0.95rem;
            outline: none;
            background: white;
            color: #0E2C46;
        }

        .form-group input:disabled {
            background-color: rgba(14, 44, 70, 0.1);
            cursor: not-allowed;
        }

        /* Toggle Switch System */
        .toggle-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed rgba(14, 44, 70, 0.2);
        }

        .toggle-info h4 {
            font-size: 1.05rem;
            font-weight: bold;
        }

        .toggle-info p {
            font-size: 0.85rem;
            color: #555;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
            border: 1px solid #0E2C46;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            border: 1px solid #0E2C46;
        }

        input:checked + .slider {
            background-color: #0E2C46;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
            background-color: #FC9D01;
        }

        .btn-save {
            background: #0E2C46;
            color: white;
            border: none;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            transition: all 0.2s;
        }

        .btn-save:hover {
            background: #1a446c;
            transform: translateY(-2px);
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
                    <p><?php echo htmlspecialchars(strtoupper($admin_info['fullname'] ?? 'ADMIN')); ?></p>
                </div>
            </div>

            <ul class="nav-links">
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_DashBoard.php"><i class="fas fa-chart-line"></i> DASHBOARD STATUS</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_OrderInfo.php"><i class="fas fa-shopping-cart"></i> ORDER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_ManageBook.php"><i class="fas fa-book"></i> MANAGE BOOK</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_CustomerInfo.php"><i class="fas fa-users"></i> CUSTOMER INFORMATION</a></li>
                <li class="active"><a href="/DreamBoundBookStrore_system/Admin/ad_settings.php"><i class="fas fa-sliders-h"></i> SETTING</a></li>
            </ul>

            <div class="logout-container">
                <button class="btn-logout" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
            </div>
        </nav>

        <main class="main-content">
            <h1 class="page-title">System Settings Portal</h1>

            <div class="settings-grid">
                <section class="settings-card">
                    <h3><i class="fas fa-user-cog"></i> Profile & Administrative Credentials</h3>
                    <form class="settings-form" action="" method="POST">
                        <input type="hidden" name="action_profile" value="1">

                        <div class="form-group">
                            <label for="adminUsername">Admin Account ID (Email)</label>
                            <input type="text" id="adminUsername" value="<?php echo htmlspecialchars($admin_info['email'] ?? 'admin@dreambound.com'); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="adminName">Full Name</label>
                            <input type="text" id="adminName" name="adminName" value="<?php echo htmlspecialchars($admin_info['fullname'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="adminEmail">Email Address</label>
                            <input type="email" id="adminEmail" name="adminEmail" value="<?php echo htmlspecialchars($admin_info['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="currPassword">Current Password</label>
                            <input type="password" id="currPassword" name="currPassword" placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Security Password</label>
                            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password if changing">
                        </div>
                        <button type="submit" class="btn-save"><i class="fas fa-user-shield"></i> Update Admin Credentials</button>
                    </form>
                </section>

                <section class="settings-card">
                    <h3><i class="fas fa-store-alt"></i> Bookstore Operation Rules</h3>
                    <form class="settings-form" action="" method="POST">
                        <input type="hidden" name="action_store" value="1">
                        
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>Bookstore Online Status</h4>
                                <p>Enable or disable client-side ordering system.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="storeStatus" id="storeStatus" <?php echo (isset($store_info['store_status']) && $store_info['store_status'] === 'open') ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>System Maintenance Mode</h4>
                                <p>Locks website storefront for backend upgrades.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="maintenanceStatus" id="maintenanceStatus" <?php echo (isset($store_info['maintenance_mode']) && $store_info['maintenance_mode'] === 'active') ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="shipSemenanjung">Base Shipping Rate - Peninsular (RM)</label>
                            <input type="number" id="shipSemenanjung" name="shipSemenanjung" step="0.01" value="<?php echo htmlspecialchars($store_info['ship_semenanjung'] ?? '4.50'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="shipBorneo">Base Shipping Rate - Sabah/Sarawak (RM)</label>
                            <input type="number" id="shipBorneo" name="shipBorneo" step="0.01" value="<?php echo htmlspecialchars($store_info['ship_borneo'] ?? '8.50'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="storeRegion">Default Store Currency & Operations Region</label>
                            <select id="storeRegion" name="storeRegion">
                                <option value="Malaysia (MYR - RM)" <?php echo (isset($store_info['store_region']) && $store_info['store_region'] === 'Malaysia (MYR - RM)') ? 'selected' : ''; ?>>Malaysia (MYR - RM)</option>
                                <option value="Singapore (SGD - $)" <?php echo (isset($store_info['store_region']) && $store_info['store_region'] === 'Singapore (SGD - $)') ? 'selected' : ''; ?>>Singapore (SGD - $)</option>
                                <option value="Brunei (BND - $)" <?php echo (isset($store_info['store_region']) && $store_info['store_region'] === 'Brunei (BND - $)') ? 'selected' : ''; ?>>Brunei (BND - $)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Deploy Store Operations Configuration</button>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script>
        <?php if (!empty($alert_message)): ?>
            alert("<?php echo $alert_message; ?>");
        <?php endif; ?>

        function confirmLogout() {
            if (confirm("Are you sure you want to log out from the admin platform?")) {
                window.location.href = "../logout.php";
            }
        }
    </script>
</body>
</html>