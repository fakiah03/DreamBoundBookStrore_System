<?php
// reset_password.php
// Step 2: User clicks reset link → verify token → let them set a new password
session_start();
require_once '../db.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

// ── Validate token ─────────────────────────────────────────────────────────
if (empty($token)) {
    header("Location: forgot_password.php");
    exit();
}

$safe_token = mysqli_real_escape_string($conn, $token);
$token_q = $conn->query("
    SELECT * FROM password_resets
    WHERE token = '$safe_token'
      AND used = 0
      AND expires_at > NOW()
    LIMIT 1
");

if (!$token_q || $token_q->num_rows === 0) {
    $error = "This reset link is invalid or has expired. Please request a new one.";
}

$token_row = ($token_q && $token_q->num_rows > 0) ? $token_q->fetch_assoc() : null;

// ── Handle form submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_row) {
    $new_pass  = $_POST['new_password']  ?? '';
    $conf_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_pass !== $conf_pass) {
        $error = "Passwords do not match. Please try again.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $email  = mysqli_real_escape_string($conn, $token_row['email']);

        // Update password
        $conn->query("UPDATE users SET password = '$hashed' WHERE email = '$email'");

        // Mark token as used
        $conn->query("UPDATE password_resets SET used = 1 WHERE token = '$safe_token'");

        // Log it
        $conn->query("INSERT INTO system_logs (log_message) VALUES ('Password successfully reset for: $email')");

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore – Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Englebert', sans-serif; }
        body { background-color: #0A2647; display: flex; min-height: 100vh; overflow: hidden; }
        .sidebar { width: 20%; min-width: 220px; background-color: #0A2647; display: flex; align-items: center; justify-content: center; }
        .sidebar-logo { text-align: center; color: white; }
        .sidebar-logo img { width: 80px; margin-bottom: 12px; opacity: 0.85; }
        .sidebar-logo span { font-size: 20px; letter-spacing: 2px; display: block; color: #FC9D01; }
        .main-content { flex: 1; background-color: #FC9D01; border-top-left-radius: 30px; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .card { background: #fff; width: 100%; max-width: 440px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); padding: 38px 36px 30px; text-align: center; }
        .icon-container { width: 58px; height: 58px; background-color: #0A2647; color: #FC9D01; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 22px; font-size: 22px; }
        h2 { color: #0A2647; font-size: 28px; margin-bottom: 10px; }
        .subtitle { color: #64748b; font-size: 16px; margin-bottom: 26px; line-height: 1.5; }
        .input-wrap { position: relative; margin-bottom: 16px; }
        .input-wrap i.field-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; }
        .input-wrap .toggle-eye { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 16px; background: none; border: none; padding: 0; }
        input[type="password"], input[type="text"] { width: 100%; padding: 14px 44px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 16px; color: #0A2647; outline: none; font-family: 'Englebert', sans-serif; transition: border-color 0.2s; }
        input:focus { border-color: #0A2647; box-shadow: 0 0 0 3px rgba(10,38,71,0.08); }
        .strength-bar { height: 5px; border-radius: 4px; background: #e2e8f0; margin: -8px 0 14px; overflow: hidden; }
        .strength-fill { height: 100%; width: 0%; border-radius: 4px; transition: width 0.3s, background 0.3s; }
        .btn-group { border-top: 1px solid #f1f5f9; padding-top: 20px; display: flex; gap: 12px; }
        .btn { flex: 1; padding: 13px; border-radius: 10px; font-size: 16px; cursor: pointer; font-family: 'Englebert', sans-serif; transition: all 0.2s; text-decoration: none; text-align: center; border: none; }
        .btn-cancel { background: #fff; border: 1.5px solid #e2e8f0; color: #64748b; display: inline-block; }
        .btn-cancel:hover { background: #f8fafc; }
        .btn-confirm { background: #0A2647; color: #FC9D01; }
        .btn-confirm:hover { background: #071c35; }
        .error-box { background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; padding: 12px 16px; border-radius: 10px; font-size: 15px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; text-align: left; }
        .success-icon { width: 70px; height: 70px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px; color: #10b981; }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { display: none; }
            .main-content { border-top-left-radius: 0; padding: 40px 20px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="../img/logo1.png" alt="Dreambound" onerror="this.style.display='none'">
            <span>DREAMBOUND</span>
        </div>
    </div>
    <div class="main-content">
        <div class="card">

        <?php if ($success): ?>

            <!-- ── SUCCESS ── -->
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <h2>Password Updated!</h2>
            <p class="subtitle">Your password has been changed successfully. You can now log in with your new password.</p>
            <div class="btn-group" style="border-top:none;padding-top:0;">
                <a href="login.php" class="btn btn-confirm"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            </div>

        <?php elseif (!empty($error) && !$token_row): ?>

            <!-- ── INVALID / EXPIRED TOKEN ── -->
            <div class="icon-container"><i class="fas fa-times"></i></div>
            <h2>Link Expired</h2>
            <p class="subtitle"><?php echo htmlspecialchars($error); ?></p>
            <div class="btn-group" style="border-top:none;padding-top:0;">
                <a href="forgot_password.php" class="btn btn-confirm"><i class="fas fa-redo"></i> Request New Link</a>
            </div>

        <?php else: ?>

            <!-- ── RESET FORM ── -->
            <div class="icon-container"><i class="fas fa-key"></i></div>
            <h2>Set New Password</h2>
            <p class="subtitle">Choose a strong password for your account.</p>

            <?php if (!empty($error)): ?>
            <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-wrap">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" id="new_password" name="new_password" placeholder="New password" required minlength="6" oninput="checkStrength(this.value)">
                    <button type="button" class="toggle-eye" onclick="toggleVis('new_password', this)"><i class="fas fa-eye"></i></button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>

                <div class="input-wrap">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required minlength="6">
                    <button type="button" class="toggle-eye" onclick="toggleVis('confirm_password', this)"><i class="fas fa-eye"></i></button>
                </div>

                <div class="btn-group">
                    <a href="login.php" class="btn btn-cancel">Cancel</a>
                    <button type="submit" class="btn btn-confirm"><i class="fas fa-check"></i> Update Password</button>
                </div>
            </form>

        <?php endif; ?>

        </div>
    </div>

    <script>
        function toggleVis(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function checkStrength(val) {
            const fill = document.getElementById('strength-fill');
            let score = 0;
            if (val.length >= 6) score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const widths = ['0%', '20%', '45%', '65%', '85%', '100%'];
            const colors = ['#e2e8f0', '#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
            fill.style.width  = widths[score];
            fill.style.background = colors[score];
        }
    </script>
</body>
</html>