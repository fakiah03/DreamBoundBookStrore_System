<?php
session_start();
require_once '../db.php';

// SECURITY RESTRICTION: Ensure that the customer is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Customer';

// ============================================================
// STEP 1: HANDLE ORDER PLACEMENT (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    // Get shipping info from POST
    $ship_address  = mysqli_real_escape_string($conn, trim($_POST['ship_address']));
    $ship_postcode = mysqli_real_escape_string($conn, trim($_POST['ship_postcode']));
    $ship_city     = mysqli_real_escape_string($conn, trim($_POST['ship_city']));
    $ship_state    = mysqli_real_escape_string($conn, trim($_POST['ship_state']));
    $ship_zone     = $_POST['ship_zone'] ?? 'semenanjung'; // semenanjung or borneo
    $voucher_code  = mysqli_real_escape_string($conn, trim($_POST['voucher_code'] ?? ''));

    // Validate: address must not be empty
    if (empty($ship_address) || empty($ship_postcode) || empty($ship_city) || empty($ship_state)) {
        $error_msg = "Please fill in all shipping address fields.";
    } else {

        // --- Fetch cart items ---
        $cart_result = $conn->query("
            SELECT c.id as cart_id, c.quantity, b.id as book_id, b.title, b.price, b.stock
            FROM cart c
            JOIN books b ON c.book_id = b.id
            WHERE c.user_id = $user_id
        ");

        $cart_items = [];
        $subtotal = 0;
        if ($cart_result && $cart_result->num_rows > 0) {
            while ($row = $cart_result->fetch_assoc()) {
                $cart_items[] = $row;
                $subtotal += $row['price'] * $row['quantity'];
            }
        }

        if (empty($cart_items)) {
            header("Location: cust_cart.php");
            exit();
        }

        // --- Get shipping fee from store_settings ---
        $shipping_fee = 5.00;
        $setting_q = $conn->query("SELECT ship_semenanjung, ship_borneo FROM store_settings LIMIT 1");
        if ($setting_q && $setting_q->num_rows > 0) {
            $s = $setting_q->fetch_assoc();
            $shipping_fee = ($ship_zone === 'borneo') ? $s['ship_borneo'] : $s['ship_semenanjung'];
        }

        // --- Apply voucher if provided ---
        $discount = 0;
        $voucher_msg = '';
        if (!empty($voucher_code)) {
            $v_q = $conn->query("SELECT * FROM vouchers WHERE code = '$voucher_code' AND status = 'active' LIMIT 1");
            if ($v_q && $v_q->num_rows > 0) {
                $voucher = $v_q->fetch_assoc();
                if ($voucher['type'] === 'percentage') {
                    $discount = $subtotal * ($voucher['value'] / 100);
                } else { // flat
                    $discount = $voucher['value'];
                }
                $discount = min($discount, $subtotal); // can't discount more than subtotal
            } else {
                $error_msg = "Invalid or expired voucher code.";
            }
        }

        if (!isset($error_msg)) {
            $total_amount = $subtotal + $shipping_fee - $discount;

            // --- Check stock for all items ---
            $stock_ok = true;
            foreach ($cart_items as $item) {
                if ($item['quantity'] > $item['stock']) {
                    $error_msg = "Sorry! \"" . htmlspecialchars($item['title']) . "\" only has " . $item['stock'] . " in stock.";
                    $stock_ok = false;
                    break;
                }
            }

            if ($stock_ok) {
                // --- Create order record ---
                $conn->begin_transaction();
                try {
                    // Insert into orders
                    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')");
                    $stmt->bind_param("id", $user_id, $total_amount);
                    $stmt->execute();
                    $order_id = $conn->insert_id;
                    $stmt->close();

                    // Insert order_items and update stock
                    foreach ($cart_items as $item) {
                        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("iiid", $order_id, $item['book_id'], $item['quantity'], $item['price']);
                        $stmt2->execute();
                        $stmt2->close();

                        // Reduce stock & increase sold_qty
                        $conn->query("UPDATE books SET stock = stock - {$item['quantity']}, sold_qty = sold_qty + {$item['quantity']} WHERE id = {$item['book_id']}");
                    }

                    // Save shipping address back to user profile if not set
                    $conn->query("UPDATE users SET address='$ship_address', postcode='$ship_postcode', city='$ship_city', state='$ship_state' WHERE id=$user_id AND (address IS NULL OR address='')");

                    // Clear cart
                    $conn->query("DELETE FROM cart WHERE user_id = $user_id");

                    // Log it
                    $conn->query("INSERT INTO system_logs (log_message) VALUES ('New order #$order_id placed by $fullname (User ID: $user_id). Total: RM " . number_format($total_amount, 2) . "')");

                    $conn->commit();

                    // Redirect to orders page with success
                    header("Location: cust_orders.php?order_success=$order_id");
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Order failed. Please try again. (" . $e->getMessage() . ")";
                }
            }
        }
    }
}

// ============================================================
// STEP 2: LOAD DATA FOR DISPLAY
// ============================================================

// Get cart items to display
$cart_result = $conn->query("
    SELECT c.id as cart_id, c.quantity, b.id as book_id, b.title, b.author, b.price, b.book_img, b.stock
    FROM cart c
    JOIN books b ON c.book_id = b.id
    WHERE c.user_id = $user_id
");

$cart_items = [];
$subtotal = 0;
if ($cart_result && $cart_result->num_rows > 0) {
    while ($row = $cart_result->fetch_assoc()) {
        $cart_items[] = $row;
        $subtotal += $row['price'] * $row['quantity'];
    }
}

// Redirect back to cart if empty
if (empty($cart_items)) {
    header("Location: cust_cart.php");
    exit();
}

// Get shipping rates
$ship_semenanjung = 4.50;
$ship_borneo = 8.50;
$setting_q = $conn->query("SELECT ship_semenanjung, ship_borneo FROM store_settings LIMIT 1");
if ($setting_q && $setting_q->num_rows > 0) {
    $s = $setting_q->fetch_assoc();
    $ship_semenanjung = $s['ship_semenanjung'];
    $ship_borneo = $s['ship_borneo'];
}

// Get user's saved address
$user_q = $conn->query("SELECT fullname, email, phone, address, postcode, city, state FROM users WHERE id = $user_id");
$user_data = ($user_q && $user_q->num_rows > 0) ? $user_q->fetch_assoc() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Checkout</title>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #0A2647;
            --accent-orange: #F29400;
            --bg-gradient: linear-gradient(135deg, #0A2647 0%, #144272 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Englebert', sans-serif; }
        body { background: var(--bg-gradient); min-height: 100vh; display: flex; overflow: hidden; padding: 15px; }
        .dashboard { display: flex; width: 100%; height: calc(100vh - 30px); background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border-radius: 24px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
        .sidebar { width: 280px; background: rgba(10,38,71,0.7); display: flex; flex-direction: column; align-items: center; padding: 40px 24px; color: white; border-right: 1px solid rgba(255,255,255,0.05); flex-shrink: 0; }
        .profile { text-align: center; width: 100%; margin-bottom: 40px; }
        .profile-circle { width: 85px; height: 85px; background: linear-gradient(135deg,rgba(255,255,255,0.1),rgba(255,255,255,0.05)); border-radius: 50%; border: 2px solid var(--accent-orange); margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; }
        .profile-circle i { font-size: 32px; color: #fff; }
        .profile h2 { font-size: 18px; font-weight: normal; color: #fff; text-transform: uppercase; letter-spacing: 1.5px; }
        .menu { width: 100%; }
        .menu ul { list-style: none; padding: 0; margin: 0; width: 100%; }
        .menu li { width: 100%; }
        .menu-item { display: flex; align-items: center; color: rgba(255,255,255,0.6); text-decoration: none; padding: 18px 24px; margin-bottom: 12px; border-radius: 20px; font-size: 22px; letter-spacing: 0.5px; transition: all 0.4s; }
        .menu-item i { margin-right: 20px; font-size: 22px; }
        .menu-item:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .menu-item.active { background: #FC9D01; color: var(--primary-blue); font-weight: bold; }
        .logout-btn { margin-top: auto; background: rgba(255,255,255,0.02); color: #ff6b6b; border: 1px solid rgba(255,77,77,0.25); padding: 16px 20px; cursor: pointer; border-radius: 20px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 12px; font-size: 18px; transition: all 0.3s; }
        .logout-btn:hover { background: #ff4d4d; color: white; }
        .content { flex: 1; padding: 40px 50px; overflow-y: auto; background: #FC9D01; border-top-left-radius: 24px; border-bottom-left-radius: 24px; }
        .content::-webkit-scrollbar { width: 6px; }
        .content::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
        header h1 { font-size: 42px; color: var(--primary-blue); margin-bottom: 5px; }
        header p { font-size: 17px; color: #fff; margin-bottom: 25px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--primary-blue); text-decoration: none; font-size: 18px; margin-bottom: 15px; transition: transform 0.2s; }
        .back-link:hover { transform: translateX(-5px); }
        .checkout-grid { display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap; }
        .checkout-left { flex: 2; min-width: 400px; display: flex; flex-direction: column; gap: 20px; }
        .card { background: white; border-radius: 20px; padding: 28px; box-shadow: 0 8px 24px rgba(0,0,0,0.07); }
        .card-title { font-size: 22px; color: var(--primary-blue); margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: var(--accent-orange); }
        .form-row { display: flex; gap: 15px; margin-bottom: 14px; }
        .form-group { flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 15px; color: #64748b; }
        .form-group input, .form-group select { padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 16px; font-family: 'Englebert', sans-serif; transition: border 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-orange); }
        .order-items-list { display: flex; flex-direction: column; gap: 12px; }
        .order-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .order-item:last-child { border-bottom: none; }
        .order-img { width: 55px; height: 72px; object-fit: cover; border-radius: 8px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); }
        .order-item-info { flex: 1; }
        .order-item-info h4 { font-size: 17px; color: var(--primary-blue); }
        .order-item-info p { font-size: 14px; color: #94a3b8; }
        .order-item-price { font-size: 17px; font-weight: bold; color: var(--primary-blue); }
        .checkout-right { flex: 1; min-width: 280px; }
        .summary-card { background: var(--primary-blue); color: white; border-radius: 20px; padding: 28px; position: sticky; top: 20px; }
        .summary-title { font-size: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 14px; margin-bottom: 18px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 13px; font-size: 17px; color: rgba(255,255,255,0.8); }
        .summary-row.discount { color: #4ade80; }
        .summary-row.total { border-top: 1px solid rgba(255,255,255,0.15); padding-top: 14px; margin-top: 14px; font-size: 20px; font-weight: bold; color: #FC9D01; }
        .voucher-row { display: flex; gap: 10px; margin: 10px 0 16px; }
        .voucher-row input { flex: 1; padding: 11px 14px; border-radius: 10px; border: none; font-size: 15px; font-family: 'Englebert', sans-serif; }
        .voucher-btn { background: var(--accent-orange); color: var(--primary-blue); border: none; padding: 11px 18px; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: bold; white-space: nowrap; }
        .place-order-btn { width: 100%; background: #FC9D01; color: var(--primary-blue); border: none; padding: 16px; border-radius: 14px; font-size: 19px; font-weight: bold; cursor: pointer; margin-top: 16px; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 8px 20px rgba(242,148,0,0.25); transition: all 0.3s; }
        .place-order-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(242,148,0,0.35); background: white; }
        .error-box { background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; padding: 14px 18px; border-radius: 12px; margin-bottom: 16px; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .zone-options { display: flex; gap: 12px; margin-bottom: 8px; }
        .zone-opt { flex: 1; }
        .zone-opt input[type="radio"] { display: none; }
        .zone-opt label { display: block; border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; cursor: pointer; text-align: center; transition: all 0.2s; font-size: 15px; }
        .zone-opt input:checked + label { border-color: var(--accent-orange); background: #fff7ed; color: var(--primary-blue); font-weight: bold; }
        .zone-price { font-size: 13px; color: #94a3b8; display: block; margin-top: 3px; }
    </style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="profile">
            <div class="profile-circle"><i class="far fa-user"></i></div>
            <h2><?php echo htmlspecialchars($fullname); ?></h2>
        </div>
        <nav class="menu">
            <ul>
                <li><a href="cust_home.php" class="menu-item"><i class="fas fa-th-large"></i> HOME</a></li>
                <li><a href="cust_cart.php" class="menu-item active"><i class="fas fa-shopping-bag"></i> CART</a></li>
                <li><a href="cust_orders.php" class="menu-item"><i class="fas fa-receipt"></i> ORDERS</a></li>
                <li><a href="cust_settings.php" class="menu-item"><i class="fas fa-sliders-h"></i> SETTINGS</a></li>
            </ul>
        </nav>
        <button class="logout-btn" onclick="location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
    </aside>

    <main class="content">
        <a href="cust_cart.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Cart</a>
        <header>
            <h1>Checkout</h1>
            <p>Complete your order details below</p>
        </header>

        <?php if (!empty($error_msg)): ?>
        <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="POST" id="checkout-form">
            <div class="checkout-grid">
                <div class="checkout-left">

                    <!-- Shipping Address -->
                    <div class="card">
                        <div class="card-title"><i class="fas fa-map-marker-alt"></i> Shipping Address</div>
                        <div class="form-row">
                            <div class="form-group" style="flex:2">
                                <label>Street Address *</label>
                                <input type="text" name="ship_address" required
                                    value="<?php echo htmlspecialchars($_POST['ship_address'] ?? $user_data['address'] ?? ''); ?>"
                                    placeholder="e.g., No. 12, Jalan Bahagia">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Postcode *</label>
                                <input type="text" name="ship_postcode" required maxlength="10"
                                    value="<?php echo htmlspecialchars($_POST['ship_postcode'] ?? $user_data['postcode'] ?? ''); ?>"
                                    placeholder="e.g., 47810">
                            </div>
                            <div class="form-group" style="flex:2">
                                <label>City *</label>
                                <input type="text" name="ship_city" required
                                    value="<?php echo htmlspecialchars($_POST['ship_city'] ?? $user_data['city'] ?? ''); ?>"
                                    placeholder="e.g., Subang Jaya">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>State *</label>
                                <select name="ship_state" required>
                                    <?php
                                    $states = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Labuan','Putrajaya'];
                                    $sel_state = $_POST['ship_state'] ?? $user_data['state'] ?? '';
                                    foreach ($states as $s) {
                                        $sel = ($sel_state === $s) ? 'selected' : '';
                                        echo "<option value=\"$s\" $sel>$s</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Zone -->
                    <div class="card">
                        <div class="card-title"><i class="fas fa-truck"></i> Shipping Zone</div>
                        <div class="zone-options">
                            <div class="zone-opt">
                                <input type="radio" name="ship_zone" id="zone_sem" value="semenanjung" checked onchange="updateShipping(this.value)">
                                <label for="zone_sem">
                                    Semenanjung Malaysia
                                    <span class="zone-price">RM <?php echo number_format($ship_semenanjung, 2); ?></span>
                                </label>
                            </div>
                            <div class="zone-opt">
                                <input type="radio" name="ship_zone" id="zone_bor" value="borneo" onchange="updateShipping(this.value)">
                                <label for="zone_bor">
                                    Sabah / Sarawak
                                    <span class="zone-price">RM <?php echo number_format($ship_borneo, 2); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="card">
                        <div class="card-title"><i class="fas fa-book"></i> Your Items (<?php echo count($cart_items); ?>)</div>
                        <div class="order-items-list">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <img src="../<?php echo htmlspecialchars(!empty($item['book_img']) ? $item['book_img'] : 'img/book1.jpg'); ?>" alt="Cover" class="order-img" onerror="this.src='../img/logo1.png'">
                                <div class="order-item-info">
                                    <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($item['author']); ?> &nbsp;|&nbsp; Qty: <?php echo $item['quantity']; ?></p>
                                </div>
                                <span class="order-item-price">RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
                <!-- /.checkout-left -->

                <!-- Order Summary -->
                <div class="checkout-right">
                    <div class="summary-card">
                        <div class="summary-title">Order Summary</div>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>RM <?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="summary-row" id="shipping-row">
                            <span>Shipping</span>
                            <span id="shipping-display">RM <?php echo number_format($ship_semenanjung, 2); ?></span>
                        </div>
                        <div class="summary-row discount" id="discount-row" style="display:none;">
                            <span>Voucher Discount</span>
                            <span id="discount-display">- RM 0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="total-display">RM <?php echo number_format($subtotal + $ship_semenanjung, 2); ?></span>
                        </div>

                        <!-- Voucher input -->
                        <p style="font-size:15px; color:rgba(255,255,255,0.7); margin-bottom:8px;">Have a voucher?</p>
                        <div class="voucher-row">
                            <input type="text" name="voucher_code" id="voucher_input" placeholder="Enter code..." value="<?php echo htmlspecialchars($_POST['voucher_code'] ?? ''); ?>">
                            <button type="button" class="voucher-btn" onclick="applyVoucher()"><i class="fas fa-tag"></i> Apply</button>
                        </div>
                        <p id="voucher-msg" style="font-size:14px; min-height:16px; margin-bottom:8px;"></p>

                        <button type="submit" name="place_order" class="place-order-btn">
                            <i class="fas fa-check-circle"></i> Place Order
                        </button>

                        <p style="font-size:13px; color:rgba(255,255,255,0.5); text-align:center; margin-top:14px;">
                            <i class="fas fa-lock"></i> Your order is processed securely
                        </p>
                    </div>
                </div>

            </div>
            <!-- /.checkout-grid -->
        </form>
    </main>
</div>

<script>
    // Shipping fee values from PHP
    const SHIP_SEM = <?php echo $ship_semenanjung; ?>;
    const SHIP_BOR = <?php echo $ship_borneo; ?>;
    const SUBTOTAL  = <?php echo $subtotal; ?>;

    let currentShipping = SHIP_SEM;
    let currentDiscount = 0;

    function updateShipping(zone) {
        currentShipping = (zone === 'borneo') ? SHIP_BOR : SHIP_SEM;
        document.getElementById('shipping-display').textContent = 'RM ' + currentShipping.toFixed(2);
        recalcTotal();
    }

    function recalcTotal() {
        let total = SUBTOTAL + currentShipping - currentDiscount;
        document.getElementById('total-display').textContent = 'RM ' + total.toFixed(2);
    }

    function applyVoucher() {
        const code = document.getElementById('voucher_input').value.trim();
        const msgEl = document.getElementById('voucher-msg');
        const discountRow = document.getElementById('discount-row');

        if (!code) { msgEl.style.color='#fca5a5'; msgEl.textContent='Please enter a voucher code.'; return; }

        // AJAX call to validate voucher
        fetch('../Admin/check_voucher.php?code=' + encodeURIComponent(code))
            .then(r => r.json())
            .then(data => {
                if (data.valid) {
                    if (data.type === 'percentage') {
                        currentDiscount = Math.min(SUBTOTAL * (data.value / 100), SUBTOTAL);
                    } else {
                        currentDiscount = Math.min(data.value, SUBTOTAL);
                    }
                    document.getElementById('discount-display').textContent = '- RM ' + currentDiscount.toFixed(2);
                    discountRow.style.display = 'flex';
                    msgEl.style.color = '#4ade80';
                    msgEl.textContent = '✓ Voucher applied!';
                    recalcTotal();
                } else {
                    currentDiscount = 0;
                    discountRow.style.display = 'none';
                    msgEl.style.color = '#fca5a5';
                    msgEl.textContent = 'Invalid or expired voucher.';
                    recalcTotal();
                }
            })
            .catch(() => { msgEl.style.color='#fca5a5'; msgEl.textContent='Could not verify voucher.'; });
    }
</script>
</body>
</html>
