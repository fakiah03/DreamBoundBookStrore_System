<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Ambil fail sambungan pangkalan data
require_once '../db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // find user by email
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Sahkan password yang dimasukkan dengan password dalam database (di-hash)
        if (password_verify($password, $user['password'])) {
            
            // 1. Simpan data dalam Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            // 2. REKOD LOG MASUK KE DATABASE (Data untuk Live Logs Dashboard)
            $nama = $_SESSION['fullname'];
            $conn->query("INSERT INTO system_logs (log_message) VALUES ('User $nama has logged in.')");

            // 3. Logik hala tuju / redirect berdasarkan peranan (Role)
            if ($user['role'] == 'admin') {
                echo "<script>alert('Welcome Admin!'); window.location.href='../Admin/ad_DashBoard.php';</script>";
            } else {
                echo "<script>alert('Login successful!'); window.location.href='../Customer/cust_home.php';</script>";
            }
            exit();
            
        } else {
            echo "<script>alert('Wrong password!'); window.location.href='login.php';</script>";
        }
    } else {
        echo "<script>alert('No account found with this email!'); window.location.href='login.php';</script>";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Log in</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
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
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #0E2C46; 
            background-image: 
                radial-gradient(rgba(255, 255, 255, 0.05) 1.5px, transparent 1.5px),
                radial-gradient(at 0% 0%, rgba(252, 157, 1, 0.4) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(253, 245, 230, 0.3) 0px, transparent 50%),
                radial-gradient(at 80% 20%, rgba(252, 157, 1, 0.3) 0px, transparent 40%);
            background-size: 30px 30px, 100% 100%, 100% 100%, 100% 100%;
            overflow: hidden;
            position: relative;
        }

        .bg-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 1;
            opacity: 0.5;
            animation: floatAnimation 8s ease-in-out infinite alternate;
        }
        .blob-1 {
            width: 400px;
            height: 400px;
            background-color: #FC9D01;
            top: -100px;
            left: -50px;
        }
        .blob-2 {
            width: 500px;
            height: 500px;
            background-color: #FDF5E6;
            bottom: -150px;
            right: 10%;
            animation-delay: 2s;
        }

        @keyframes floatAnimation {
            0% { transform: translateY(0px) scale(1); }
            100% { transform: translateY(30px) scale(1.05); }
        }

        .container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between; 
            padding: 0 10%;
            position: relative;
            z-index: 2; 
        }

        .brand-showcase {
            color: #FDF5E6;
            max-width: 45%;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .brand-showcase h1 {
            font-size: 3.8rem;
            line-height: 1.1;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        .brand-showcase h1 span {
            color: #FC9D01;
        }

        .brand-showcase p {
            font-size: 1.4rem;
            font-weight: 500;
            color: #e0e0e0;
        }

        .login-card {
            background-color: rgba(14, 44, 70, 0.85); 
            width: 420px;
            padding: 40px;
            border-radius: 24px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4); 
            text-align: center;
            color: #ffffff;
            border: 3px solid #FC9D01;
            backdrop-filter: blur(10px);
        }

        .login-card h2 {
            font-size: 2.2rem;
            margin-bottom: 25px;
            color: #FC9D01;
            letter-spacing: 1px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 1.1rem;
            color: #FDF5E6;
        }

        input {
            width: 100%;
            padding: 12px 20px;
            border-radius: 25px;
            border: 2px solid transparent;
            background-color: #FDF5E6; 
            color: #0E2C46;
            outline: none;
            font-size: 1.05rem;
            transition: all 0.3s ease;
        }
        
        input:focus {
            border-color: #FC9D01;
            box-shadow: 0 0 10px rgba(252, 157, 1, 0.5);
        }

        .login-btn {
            width: 85%; 
            padding: 12px;
            margin-top: 15px;
            border-radius: 30px;
            border: 3px solid #FC9D01;
            background-color: #FC9D01;
            color: #0E2C46;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 4px 4px 0px #FDF5E6;
            transition: all 0.2s ease;
        }

        .login-btn:hover {
            background-color: #FDF5E6;
            border-color: #0E2C46;
            color: #0E2C46;
            transform: translateY(-2px);
            box-shadow: 5px 5px 0px #FC9D01;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 25px 0 15px 0;
            color: #FDF5E6;
            font-size: 14px;
        }

        .divider::before, 
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 2px solid rgba(253, 245, 230, 0.3);
        }

        .divider span {
            padding: 0 10px;
        }

        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 11px;
            background-color: #ffffff;
            color: #0E2C46;
            border: 2px solid #0E2C46;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 20px;
        }

        .google-btn img {
            width: 20px;
            margin-right: 10px;
        }

        .google-btn:hover {
            background-color: #FDF5E6;
            transform: translateY(-2px);
        }

        .links-container {
            margin-top: 15px;
            font-size: 0.95rem;
        }

        .forgot-pass {
            color: #b0b0b0;
            text-decoration: none;
            display: block; 
            margin-bottom: 15px;
            transition: 0.3s;
        }

        .forgot-pass:hover {
            color: #FC9D01;
        }

        .signup-text {
            color: #ffffff;
        }

        .signup-link {
            color: #FC9D01;
            text-decoration: none;
            font-weight: bold;
        }

        .signup-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            .container { justify-content: center; padding: 20px; }
            .brand-showcase { display: none; }
            .login-card { width: 100%; max-width: 400px; }
        }
    </style>
</head>
<body>

    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="container">
        <div class="brand-showcase">
            <h1>DREAMBOUND<br><span>BOOKSTORE</span></h1>
            <p>Your ultimate gateway to a world of endless imagination and knowledge.</p>
        </div>

        <div class="login-card">
            <h2>Welcome Back</h2>
            
            <!-- DITUKAR: action digosongkan supaya submit ke fail ini sendiri -->
            <form action="" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <!-- TAMBAH: name="email" supaya PHP boleh baca input -->
                    <input type="email" id="email" name="email" placeholder="johndoe@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <!-- TAMBAH: name="password" supaya PHP boleh baca input -->
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="login-btn">Log In</button>

                <div class="divider">
                    <span>or log in with</span>
                </div>

                <button type="button" class="google-btn">
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" alt="Google logo">
                    Google
                </button>

                <div class="links-container">
                    <a href="#" class="forgot-pass">Forgot Password?</a>
                    <p class="signup-text">
                        New to Dreambound? <a href="signup.php" class="signup-link">Sign up here</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>