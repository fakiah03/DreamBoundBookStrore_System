<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data from the HTML form.
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    // Verify that the password values match.
    if ($password !== $confirmPassword) {
        echo "<script>alert('Passwords do not match!'); window.location.href='signup.php';</script>";
        exit();
    }

    $checkEmail->bind_param("s", $email); //Apply password encryption (for security).
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 4. Verify duplicate email registration using a prepared statement.
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->execute();
    $result = $checkEmail->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('This email is already registered!'); window.location.href='signup.php';</script>";
        $checkEmail->close();
        exit();
    }
    $checkEmail->close(); 
    $res_count = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
    $row_count = $res_count->fetch_assoc();
    $next_id_num = $row_count['total'] + 1;
    
    // Generate sequential IDs in the format #CUST-0001, #CUST-0002, etc.
    $customer_id_str = "#CUST-" . str_pad($next_id_num, 4, "0", STR_PAD_LEFT);
    $membership_tier = "Regular"; // Set default membership status for new user registrations
    // 5. SQL query to add a new user (updated to align with the current database schema).
    $sql_register = "INSERT INTO users (customer_id_str, fullname, email, password, phone, role, membership_tier) 
                     VALUES ('$customer_id_str', '$fullname', '$email', '$hashed_password', '$phone', 'customer', '$membership_tier')";

    if ($conn->query($sql_register) === TRUE) {
        
        // 6. Log new user registration activity into the system_logs table.
        $log_msg = "New user $fullname ($email) has registered with ID $customer_id_str.";
        $conn->query("INSERT INTO system_logs (log_message) VALUES ('$log_msg')");

        // 7. Implement auto-login by setting session data and redirecting the customer to cust_home.php.
        $new_user_id = $conn->insert_id;
        $_SESSION['user_id']  = $new_user_id;
        $_SESSION['fullname'] = $fullname;
        $_SESSION['role']     = 'customer';

        echo "<script>alert('Register successful! Your Customer ID is $customer_id_str'); window.location.href='../Customer/cust_home.php';</script>";
        exit();

    } else {
        echo "<script>alert('Error occurred while registering: " . $conn->error . "'); window.location.href='signup.php';</script>";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Sign Up</title>
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

        /* Container */
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
            font-size: 3.5rem;
            line-height: 1.1;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }

        .brand-showcase h1 span {
            color: #FC9D01;
        }

        .brand-showcase p {
            font-size: 1.3rem;
            font-weight: 500;
            color: #e0e0e0;
        }

        .signup-card {
            background-color: rgba(14, 44, 70, 0.85); 
            width: 390px;
            padding: 20px 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            text-align: center;
            color: #ffffff;
            border: 3px solid #FC9D01;
            backdrop-filter: blur(10px);
        }

        .signup-card h2 {
            font-size: 1.8rem;
            margin-bottom: 12px;
            color: #FC9D01;
            letter-spacing: 1px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 10px;
            width: 100%;
        }

        label {
            display: block;
            margin-bottom: 4px;
            font-size: 1rem;
            color: #FDF5E6;
        }

        input {
            width: 100%;
            padding: 8px 15px;
            border-radius: 20px;
            border: 2px solid transparent;
            background-color: #FDF5E6;
            color: #0E2C46;
            outline: none;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        input:focus {
            border-color: #FC9D01;
            box-shadow: 0 0 8px rgba(252, 157, 1, 0.5);
        }

        /* Create Account Dynamic Button */
        .create-btn {
            width: 85%;
            padding: 10px;
            margin-top: 8px;
            border-radius: 25px;
            border: 3px solid #FC9D01;
            background-color: #FC9D01;
            color: #0E2C46;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 3px 3px 0px #FDF5E6;
            transition: all 0.2s ease;
        }

        .create-btn:hover {
            background-color: #FDF5E6;
            border-color: #0E2C46;
            color: #0E2C46;
            transform: translateY(-2px);
            box-shadow: 4px 4px 0px #FC9D01;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 12px 0 10px 0;
            color: #FDF5E6;
            font-size: 13px;
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
            padding: 8px;
            background-color: #ffffff;
            color: #0E2C46;
            border: 2px solid #0E2C46;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 12px;
        }

        .google-btn img {
            width: 18px;
            margin-right: 8px;
        }

        .google-btn:hover {
            background-color: #FDF5E6;
            transform: translateY(-2px);
        }

        .login-text {
            color: #ffffff;
            font-size: 0.9rem;
        }

        .login-link {
            color: #FC9D01;
            text-decoration: none;
            font-weight: bold;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            .container { justify-content: center; padding: 20px; }
            .brand-showcase { display: none; }
            .signup-card { width: 100%; max-width: 380px; padding: 20px 20px; }
        }
    </style>
</head>
<body>

    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="container">
        <div class="brand-showcase">
            <h1>DREAMBOUND<br><span>BOOKSTORE</span></h1>
            <p>Join our reading community today and step into a world built from stories.</p>
        </div>

        <div class="signup-card">
            <h2>Create an Account</h2>
            
            <form action="" method="POST" id="signupForm">
                
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="johndoe@example.com" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" placeholder="e.g. 0123456789" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                </div>

                <button type="submit" class="create-btn">Sign Up</button>
                
                <div class="divider">
                    <span>or sign up with</span>
                </div>

                <button type="button" class="google-btn">
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" alt="Google logo">
                    Google
                </button>

                <div>
                    <p class="login-text">
                        Already have an account?
                        <a href="login.php" class="login-link">Log in here</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
    
</body>
</html>