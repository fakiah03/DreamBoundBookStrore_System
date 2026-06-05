<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Ambil fail sambungan database anda
require_once '../db.php'; 

// Pastikan user dah log masuk
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. Ambil data terkini menggunakan lajur 'phone' (ditambah ?? "" untuk elak amaran Deprecated PHP 8.1+)
$sql = "SELECT fullname, email, phone, address, postcode, city, state FROM users WHERE id = '$user_id'";
$result = $conn->query($sql);

$fullname = $email = $phone = $address = $postcode = $city = $state = "";

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $fullname = $user['fullname'] ?? "";
    $email = $user['email'] ?? "";
    $phone = $user['phone'] ?? ""; 
    $address = $user['address'] ?? "";
    $postcode = $user['postcode'] ?? "";
    $city = $user['city'] ?? "";
    $state = $user['state'] ?? "";
}

// 3. Logik simpan data apabila butang "SAVE CHANGES" ditekan
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']); 
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $postcode = mysqli_real_escape_string($conn, $_POST['postcode']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);

    // Kemas kini database menggunakan lajur 'phone' dan alamat baharu
    $update_sql = "UPDATE users SET 
                    fullname='$fullname', 
                    email='$email', 
                    phone='$phone', 
                    address='$address', 
                    postcode='$postcode', 
                    city='$city', 
                    state='$state' 
                  WHERE id='$user_id'";

    if ($conn->query($update_sql)) {
        $_SESSION['fullname'] = $fullname; // Kemas kini nama dalam session jika berubah
        
        // Logik tukar password jika checkbox dicentang
        if (isset($_POST['changePasswordCheck'])) {
            $currpass = $_POST['currpass'];
            $newpass = $_POST['newpass'];
            $confpass = $_POST['confpass'];

            $pass_query = $conn->query("SELECT password FROM users WHERE id='$user_id'");
            $pass_data = $pass_query->fetch_assoc();

            if (password_verify($currpass, $pass_data['password'])) {
                if ($newpass === $confpass) {
                    $hashed_new_password = password_hash($newpass, PASSWORD_DEFAULT);
                    $conn->query("UPDATE users SET password='$hashed_new_password' WHERE id='$user_id'");
                    echo "<script>alert('Profile and Password successfully updated!'); window.location.href='cust_settings.php';</script>";
                    exit();
                } else {
                    echo "<script>alert('New password and confirm password do not match!');</script>";
                }
            } else {
                echo "<script>alert('Incorrect current password!');</script>";
            }
        } else {
            echo "<script>alert('Account Information successfully updated!'); window.location.href='cust_settings.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Failed to update account information: " . $conn->error . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Settings</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-blue: #0A2647;
            --accent-orange: #FC9D01; 
            --bg-gradient: linear-gradient(135deg, #0A2647 0%, #144272 100%);
            --form-bg: #FFFDF4;
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

        .profile {
            text-align: center;
            width: 100%;
            margin-bottom: 40px;
        }

        .profile-circle { 
            width: 85px; 
            height: 85px; 
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05)); 
            border-radius: 50%; 
            border: 2px solid var(--accent-orange); 
            margin: 0 auto 15px auto; 
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .profile-circle i { 
            font-size: 32px;
            color: #ffffff;
        }

        .profile h2 { 
            font-size: 18px; 
            font-weight: normal;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .menu { 
            width: 100%; 
        }

        .menu-item { 
            display: flex; 
            align-items: center; 
            color: rgba(255, 255, 255, 0.6); 
            text-decoration: none; 
            padding: 18px 24px; 
            margin-bottom: 12px; 
            border-radius: 20px; 
            font-size: 22px; 
            letter-spacing: 0.5px;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .menu-item i {
            margin-right: 20px;
            font-size: 22px;
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1); 
        }

        .menu-item:hover:not(.active) {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.15); 
        }

        .menu-item:hover i {
            transform: scale(1.2); 
        }

        .menu-item.active { 
            background: #FC9D01; 
            color: #0A2647;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25);
            font-weight: bold;
        }

        .logout-btn { 
            margin-top: auto; 
            background: rgba(255, 255, 255, 0.02);
            color: #ff6b6b; 
            border: 1px solid rgba(255, 77, 77, 0.25); 
            padding: 16px 20px; 
            cursor: pointer; 
            border-radius: 20px; 
            width: 100%; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 18px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff4d4d;
            color: white;
            box-shadow: 0 8px 20px rgba(255, 77, 77, 0.2);
            border-color: transparent; 
        }

        .content { 
            flex: 1; 
            padding: 40px 50px; 
            background: #FC9D01;
            border-top-left-radius: 24px; 
            border-bottom-left-radius: 24px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .content h1 { 
            font-size: 54px; 
            color: var(--primary-blue); 
            margin-bottom: 2px;
            font-weight: bold; 
        }

        .content .subtitle-white {
            font-size: 22px;
            color: #ffffff;
            margin-bottom: 30px;
        }

        .form-master-container {
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 20px;
            background-color: var(--form-bg); 
            padding: 35px;
        }

        .form-title {
            font-size: 32px;
            color: var(--primary-blue);
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .form-subtitle {
            font-size: 20px;
            color: var(--primary-blue);
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .shipping-title {
            margin: 45px 0 15px 0; 
        }

        .personal-info-title {
            margin: 0 0 15px 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px 30px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .label-container {
            display: flex;
            align-items: center;
            font-size: 19px;
            color: #0F172A;
        }

        .star-required {
            color: red;
            margin-left: 4px;
            font-weight: bold;
        }

        .error-message {
            color: red;
            font-size: 16px;
            margin-left: 10px;
            display: none;
            font-weight: bold;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid #94a3b8;
            background-color: #ffffff;
            font-size: 18px;
            color: #0F172A;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 8px rgba(10, 38, 71, 0.15);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
            font-size: 19px;
            cursor: pointer;
            color: #0F172A;
            width: max-content;
        }

        .checkbox-container input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .password-section {
            display: none;
            border-top: 1px dashed #CBD5E1;
            margin-top: 25px;
            padding-top: 20px;
        }

        .form-actions {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }

        .save-btn {
            background-color: var(--primary-blue);
            color: white;
            border: 2px solid var(--primary-blue);
            padding: 12px 50px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 25px;
            cursor: pointer;
            box-shadow: 4px 4px 0px var(--accent-orange);
            transition: all 0.2s ease;
        }

        .save-btn:hover {
            background-color: #ffffff;
            color: var(--primary-blue);
            transform: translateY(-2px);
            box-shadow: 5px 5px 0px var(--accent-orange);
        }

        .required-legend {
            color: #64748B;
            font-size: 16px;
        }
    </style>
</head>
<body>

    <div class="dashboard">
        <aside class="sidebar">
            <div class="profile">
                <div class="profile-circle">
                    <i class="far fa-user"></i>
                </div>
                <h2>ACCOUNT</h2>
            </div>
            <nav class="menu">
                <a href="cust_home.php" class="menu-item"><i class="fas fa-th-large"></i> HOME</a>
                <a href="cust_cart.php" class="menu-item"><i class="fas fa-shopping-bag"></i> CART</a>
                <a href="cust_orders.php" class="menu-item"><i class="fas fa-receipt"></i> ORDERS</a>
                <a href="cust_settings.php" class="menu-item active"><i class="fas fa-sliders-h"></i> SETTINGS</a>
            </nav>
            <button class="logout-btn" onclick="location.href='../Auth/logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
        </aside>

        <main class="content">
            <h1>Account Information</h1>
            <p class="subtitle-white">Please update your profile and shipping details below.</p>

            <div class="form-master-container">
                <form id="accountForm" action="" method="POST" onsubmit="return validateForm(event)">
                    
                    <div class="form-title">User Settings</div>

                    <div class="form-subtitle personal-info-title">Personal Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="label-container">
                                Full Name <span class="star-required">*</span>
                                <span class="error-message" id="error-fullname">required</span>
                            </div>
                            <input type="text" id="fullname" name="fullname" class="form-input" placeholder="Enter your full name" value="<?php echo htmlspecialchars($fullname ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <div class="label-container">
                                Mobile Number <span class="star-required">*</span>
                                <span class="error-message" id="error-phone">required</span>
                            </div>
                            <input type="text" id="phone" name="phone" class="form-input" placeholder="e.g. 0133467376" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <div class="label-container">
                                Email Address <span class="star-required">*</span>
                                <span class="error-message" id="error-email">required</span>
                            </div>
                            <input type="email" id="email" name="email" class="form-input" placeholder="example@gmail.com" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-subtitle shipping-title">Shipping Address</div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <div class="label-container">
                                Street Address <span class="star-required">*</span>
                                <span class="error-message" id="error-address">required</span>
                            </div>
                            <textarea id="address" name="address" class="form-textarea" placeholder="No. Rumah, Nama Jalan, Taman/Kampung"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <div class="label-container">
                                Postcode <span class="star-required">*</span>
                                <span class="error-message" id="error-postcode">required</span>
                            </div>
                            <input type="text" id="postcode" name="postcode" class="form-input" placeholder="e.g. 15200" inputmode="numeric" maxlength="5" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?php echo htmlspecialchars($postcode ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <div class="label-container">
                                Town / City <span class="star-required">*</span>
                                <span class="error-message" id="error-city">required</span>
                            </div>
                            <input type="text" id="city" name="city" class="form-input" placeholder="e.g. Kota Bharu" value="<?php echo htmlspecialchars($city ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <div class="label-container">
                                State <span class="star-required">*</span>
                                <span class="error-message" id="error-state">required</span>
                            </div>
                            <select id="state" name="state" class="form-select" aria-label="State">
                                <option value="">~ Select State ~</option>
                                <?php
                                $states = ["Johor", "Kedah", "Kelantan", "Melaka", "Negeri Sembilan", "Pahang", "Perak", "Perlis", "Pulau Pinang", "Sabah", "Sarawak", "Selangor", "Terengganu", "Wp Kuala Lumpur", "Wp Labuan", "Wp Putrajaya"];
                                foreach ($states as $s) {
                                    $selected = ($state == $s) ? "selected" : "";
                                    echo "<option value='$s' $selected>$s</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <label class="checkbox-container">
                        <input type="checkbox" id="changePasswordCheck" name="changePasswordCheck" onchange="togglePasswordSection()"> Change Password
                    </label>

                    <div id="passwordSection" class="password-section">
                        <div class="form-subtitle">Change Password</div>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <div class="label-container">
                                    Current Password <span class="star-required">*</span>
                                    <span class="error-message" id="error-currpass">required</span>
                                </div>
                                <input type="password" id="currpass" name="currpass" class="form-input">
                            </div>
                            <div class="form-group full-width">
                                <div class="label-container">
                                    New Password <span class="star-required">*</span>
                                    <span class="error-message" id="error-newpass">required</span>
                                </div>
                                <input type="password" id="newpass" name="newpass" class="form-input">
                            </div>
                            <div class="form-group full-width">
                                <div class="label-container">
                                    Confirm New Password <span class="star-required">*</span>
                                    <span class="error-message" id="error-confpass">required</span>
                                </div>
                                <input type="password" id="confpass" name="confpass" class="form-input">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="save-btn">SAVE CHANGES</button>
                        <div class="required-legend"><span style="color:red;">*</span> Required Fields for shipping and contact purposes.</div>
                    </div>

                </form>
            </div>
        </main>
    </div>

    <script>
        function togglePasswordSection() {
            const checkbox = document.getElementById('changePasswordCheck');
            const passwordSection = document.getElementById('passwordSection');
            
            if(checkbox.checked) {
                passwordSection.style.display = 'block';
            } else {
                passwordSection.style.display = 'none';
                document.getElementById('currpass').value = '';
                document.getElementById('newpass').value = '';
                document.getElementById('confpass').value = '';
                document.getElementById('error-currpass').style.display = 'none';
                document.getElementById('error-newpass').style.display = 'none';
                document.getElementById('error-confpass').style.display = 'none';
            }
        }

        function validateForm(event) {
            let isValid = true;

            const errors = document.querySelectorAll('.error-message');
            errors.forEach(err => err.style.display = 'none');

            if (document.getElementById('fullname').value.trim() === "") {
                document.getElementById('error-fullname').style.display = 'inline';
                isValid = false;
            }
            if (document.getElementById('phone').value.trim() === "") {
                document.getElementById('error-phone').style.display = 'inline';
                isValid = false;
            }
            if (document.getElementById('email').value.trim() === "") {
                document.getElementById('error-email').style.display = 'inline';
                isValid = false;
            }
            if (document.getElementById('address').value.trim() === "") {
                document.getElementById('error-address').style.display = 'inline';
                isValid = false;
            }
            if (document.getElementById('postcode').value.trim() === "") {
                document.getElementById('error-postcode').style.display = 'inline';
                isValid = false;
            }
            if (document.getElementById('city').value.trim() === "") {
                document.getElementById('error-city').style.display = 'inline';
                isValid = false;
            }
            if (document.getElementById('state').value === "") {
                document.getElementById('error-state').style.display = 'inline';
                isValid = false;
            }

            if (document.getElementById('changePasswordCheck').checked) {
                if (document.getElementById('currpass').value.trim() === "") {
                    document.getElementById('error-currpass').style.display = 'inline';
                    isValid = false;
                }
                if (document.getElementById('newpass').value.trim() === "") {
                    document.getElementById('error-newpass').style.display = 'inline';
                    isValid = false;
                }
                if (document.getElementById('confpass').value.trim() === "") {
                    document.getElementById('error-confpass').style.display = 'inline';
                    isValid = false;
                }
            }

            if (!isValid) {
                event.preventDefault(); // Sekat hantar ke PHP jika tidak valid
            }

            return isValid;
        }
    </script>
</body>
</html>