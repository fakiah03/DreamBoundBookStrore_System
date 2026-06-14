<?php
session_start();
require_once '../db.php';

// ── Security: customers only ────────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? '';

// ── Load user profile ───────────────────────────────────────────────────────
$user_q = $conn->query("SELECT fullname, email, phone, address, postcode, city, state FROM users WHERE id = $user_id LIMIT 1");
$user   = ($user_q && $user_q->num_rows > 0) ? $user_q->fetch_assoc() : [];

// Split fullname into first / last for the form
$name_parts = explode(' ', trim($user['fullname'] ?? ''), 2);
$first_name = $name_parts[0] ?? '';
$last_name  = $name_parts[1] ?? '';

// ── Load cart items ─────────────────────────────────────────────────────────
$cart_q = $conn->query("
    SELECT c.quantity, b.id AS book_id, b.title, b.author, b.price, b.book_img, b.stock
    FROM cart c
    JOIN books b ON c.book_id = b.id
    WHERE c.user_id = $user_id
");

$cart_items = [];
$subtotal   = 0;
if ($cart_q && $cart_q->num_rows > 0) {
    while ($row = $cart_q->fetch_assoc()) {
        $cart_items[] = $row;
        $subtotal    += $row['price'] * $row['quantity'];
    }
}

// Redirect if cart is empty
if (empty($cart_items)) {
    header("Location: cust_cart.php");
    exit();
}

// ── Shipping fee ────────────────────────────────────────────────────────────
$ship_sem = 4.50;
$ship_bor = 8.50;
$s_q = $conn->query("SELECT ship_semenanjung, ship_borneo FROM store_settings LIMIT 1");
if ($s_q && $s_q->num_rows > 0) {
    $s = $s_q->fetch_assoc();
    $ship_sem = (float)$s['ship_semenanjung'];
    $ship_bor = (float)$s['ship_borneo'];
}

// ── Handle Place Order (POST) ───────────────────────────────────────────────
$order_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $first      = mysqli_real_escape_string($conn, trim($_POST['firstName'] ?? ''));
    $last       = mysqli_real_escape_string($conn, trim($_POST['lastName']  ?? ''));
    $email      = mysqli_real_escape_string($conn, trim($_POST['email']     ?? ''));
    $phone      = mysqli_real_escape_string($conn, trim(($_POST['countryCode'] ?? '+60') . ($_POST['phoneNumber'] ?? '')));
    $state      = mysqli_real_escape_string($conn, trim($_POST['state']     ?? ''));
    $city       = mysqli_real_escape_string($conn, trim($_POST['city']      ?? ''));
    $postcode   = mysqli_real_escape_string($conn, trim($_POST['zipCode']   ?? ''));
    $pay_method = $_POST['paymentMethod'] ?? 'card';
    $ship_zone  = $_POST['ship_zone']    ?? 'semenanjung';

    $shipping_fee = ($ship_zone === 'borneo') ? $ship_bor : $ship_sem;
    $total        = $subtotal + $shipping_fee;

    // Validate stock
    $stock_ok = true;
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $order_error = "Sorry! \"" . htmlspecialchars($item['title']) . "\" only has " . $item['stock'] . " units in stock.";
            $stock_ok = false;
            break;
        }
    }

    if ($stock_ok) {
        $conn->begin_transaction();
        try {
            // Insert order
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("id", $user_id, $total);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $stmt->close();

            // Insert order items + update stock
            foreach ($cart_items as $item) {
                $s2 = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
                $s2->bind_param("iiid", $order_id, $item['book_id'], $item['quantity'], $item['price']);
                $s2->execute();
                $s2->close();
                $conn->query("UPDATE books SET stock = stock - {$item['quantity']}, sold_qty = sold_qty + {$item['quantity']} WHERE id = {$item['book_id']}");
            }

            // Update user profile with latest info
            $full = "$first $last";
            $conn->query("UPDATE users SET fullname='$full', phone='$phone', postcode='$postcode', city='$city', state='$state' WHERE id=$user_id");

            // Clear cart
            $conn->query("DELETE FROM cart WHERE user_id = $user_id");

            // Log
            $conn->query("INSERT INTO system_logs (log_message) VALUES ('Order #$order_id placed by user ID $user_id via $pay_method. Total: RM " . number_format($total, 2) . "')");

            $conn->commit();

            // Store order ID + payment method in session for the success screen
            $_SESSION['last_order_id']     = $order_id;
            $_SESSION['last_pay_method']   = $pay_method;
            $_SESSION['last_order_total']  = $total;

            // QR payment → show QR modal; Card → straight to success
            if ($pay_method === 'qr') {
                header("Location: cust_payment.php?show_qr=1");
            } else {
                header("Location: cust_orders.php?order_success=$order_id");
            }
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $order_error = "Order failed. Please try again. (" . $e->getMessage() . ")";
        }
    }
}

// After QR modal "Done" → redirect to orders
if (isset($_GET['qr_done'])) {
    $oid = $_SESSION['last_order_id'] ?? 0;
    header("Location: cust_orders.php?order_success=$oid");
    exit();
}

$show_qr = isset($_GET['show_qr']) && isset($_SESSION['last_order_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout – Dreambound Bookstore</title>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #0A2647;
            --accent-orange: #FC9D01;
            --main-bg: #E0E0E0;
            --input-bg: #FFFFFF;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Englebert', sans-serif; }
        body { background-color: var(--accent-orange); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 10px; }
        .checkout-container { width: 100%; max-width: 1100px; background-color: var(--main-bg); border-radius: 24px; border: 2px solid #B5B5B5; overflow: hidden; box-shadow: 0 12px 24px rgba(0,0,0,0.15); display: flex; flex-direction: column; margin: 10px auto; }
        .checkout-header { padding: 20px; text-align: center; }
        .checkout-header h1 { font-size: clamp(28px, 5vw, 42px); color: var(--primary-blue); text-transform: uppercase; letter-spacing: 1px; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 15px; color: #555; text-decoration: none; padding: 0 20px 10px; }
        .back-link:hover { color: var(--primary-blue); }
        .checkout-divider { height: 1px; background-color: #A0A0A0; width: 100%; }
        .checkout-body { display: flex; flex-direction: row; flex-wrap: wrap; }
        .form-section { flex: 1 1 60%; min-width: 300px; padding: 25px 20px; }
        .summary-section { flex: 1 1 40%; min-width: 300px; padding: 25px 20px; background-color: rgba(255,255,255,0.25); display: flex; flex-direction: column; justify-content: space-between; border-top: 1px solid #A0A0A0; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px 20px; margin-bottom: 25px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-grid-triple { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 25px; }
        label { font-size: 18px; color: #1A1A1A; }
        .star-required { color: #FF0000; font-weight: bold; margin-left: 2px; }
        .form-input, .form-select { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #757575; background-color: var(--input-bg); font-size: 16px; color: #000; outline: none; font-family: 'Englebert', sans-serif; }
        .form-input:focus, .form-select:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 2px rgba(10,38,71,0.1); }
        .phone-wrapper { display: flex; gap: 8px; }
        .phone-wrapper .country-select { flex: 45; }
        .phone-wrapper .phone-input { flex: 55; }
        .payment-title-row { font-size: 20px; color: #1A1A1A; margin-bottom: 12px; }
        .payment-options-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .payment-box { background-color: var(--input-bg); border: 1px solid #757575; border-radius: 12px; padding: 12px 15px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s ease; }
        .payment-box.selected { border-color: var(--primary-blue); background: #EAF0FA; }
        .payment-box-left { display: flex; align-items: center; gap: 8px; font-size: 16px; }
        .payment-box input[type="radio"] { width: 16px; height: 16px; accent-color: var(--primary-blue); }
        .payment-icon { font-size: 20px; color: var(--primary-blue); }
        .card-details-panel { background-color: rgba(255,255,255,0.4); border: 1px dashed #757575; border-radius: 12px; padding: 15px; margin-bottom: 20px; animation: fadeIn 0.3s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .card-row { margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px; }
        .card-inner-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .input-with-icon { position: relative; }
        .input-with-icon .form-input { padding-right: 40px; }
        .input-with-icon i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #555; font-size: 18px; }
        .cart-title { font-size: 22px; color: #000; margin-bottom: 15px; }
        .cart-items-list { background-color: #FFFDF4; border-radius: 14px; padding: 12px; border: 1px solid #B5B5B5; margin-bottom: 20px; display: flex; flex-direction: column; gap: 12px; }
        .cart-item { display: flex; gap: 12px; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .cart-item:last-child { border-bottom: none; padding-bottom: 0; }
        .item-image-box { width: 65px; height: 85px; background-color: #ECECEC; border-radius: 8px; overflow: hidden; border: 1px solid #757575; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .item-image-box img { width: 100%; height: 100%; object-fit: cover; }
        .item-details { flex: 1; }
        .item-name { font-size: 17px; font-weight: bold; color: #1A1A1A; }
        .item-desc { font-size: 13px; color: #555; line-height: 1.3; }
        .item-qty { font-size: 13px; color: #888; }
        .item-price { font-size: 16px; font-weight: bold; color: #000; white-space: nowrap; }
        .pricing-block { border-top: 1px solid #A0A0A0; padding-top: 12px; display: flex; flex-direction: column; gap: 8px; }
        .price-row { display: flex; justify-content: space-between; font-size: 18px; color: #222; }
        .price-row.total-row { font-size: 22px; font-weight: bold; color: #000; border-top: 1px solid #A0A0A0; padding-top: 8px; }
        .zone-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .zone-opt { flex: 1; }
        .zone-opt input[type="radio"] { display: none; }
        .zone-opt label { display: block; border: 1.5px solid #A0A0A0; border-radius: 10px; padding: 8px 10px; cursor: pointer; text-align: center; font-size: 14px; transition: all 0.2s; background: white; }
        .zone-opt input:checked + label { border-color: var(--primary-blue); background: #EAF0FA; color: var(--primary-blue); font-weight: bold; }
        .place-order-btn { width: 100%; background-color: var(--primary-blue); color: #fff; border: none; padding: 12px; font-size: 20px; border-radius: 12px; cursor: pointer; margin-top: 15px; text-transform: uppercase; font-family: 'Englebert', sans-serif; transition: background 0.2s; }
        .place-order-btn:hover { background: #071c35; }
        .required-notice { font-size: 14px; color: #333; margin-top: 8px; }
        .error-box { background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; padding: 12px 16px; border-radius: 10px; font-size: 15px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        /* QR Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 1000; padding: 15px; }
        .modal-overlay.active { display: flex; }
        .qr-modal { background-color: #fff; padding: 20px; border-radius: 20px; text-align: center; max-width: 320px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .qr-modal h3 { font-size: 22px; color: var(--primary-blue); margin-bottom: 8px; }
        .qr-modal p { font-size: 14px; color: #666; margin-bottom: 14px; }
        .qr-image-container { width: 100%; max-width: 220px; aspect-ratio: 1/1; margin: 0 auto 15px; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; background-color: #FAFFAF; }
        .qr-image-container img { width: 100%; height: 100%; object-fit: contain; }
        .close-modal-btn { background-color: #22c55e; color: white; border: none; padding: 10px 25px; font-size: 16px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; font-family: 'Englebert', sans-serif; }
        @media (min-width: 769px) { .form-section { border-right: 1px solid #A0A0A0; } .summary-section { border-top: none; } }
        @media (max-width: 768px) { .checkout-body { flex-direction: column; } .form-grid { grid-template-columns: 1fr; } .form-grid-triple { grid-template-columns: 1fr; } .payment-options-container { grid-template-columns: 1fr; } .checkout-container { border-radius: 16px; } }
    </style>
</head>
<body>

<div class="checkout-container">
    <div class="checkout-header">
        <h1>Checkout</h1>
    </div>
    <a href="cust_cart.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Cart</a>
    <div class="checkout-divider"></div>

    <div class="checkout-body">

        <!-- ── LEFT: FORM ── -->
        <div class="form-section">

            <?php if (!empty($order_error)): ?>
            <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($order_error); ?></div>
            <?php endif; ?>

            <form id="checkoutForm" method="POST">
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="paymentMethod" id="hiddenPayment" value="card">
                <input type="hidden" name="ship_zone" id="hiddenZone" value="semenanjung">

                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name<span class="star-required">*</span></label>
                        <input type="text" name="firstName" class="form-input" required
                            value="<?php echo htmlspecialchars($_POST['firstName'] ?? $first_name); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name<span class="star-required">*</span></label>
                        <input type="text" name="lastName" class="form-input" required
                            value="<?php echo htmlspecialchars($_POST['lastName'] ?? $last_name); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email<span class="star-required">*</span></label>
                        <input type="email" name="email" class="form-input" required
                            value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number<span class="star-required">*</span></label>
                        <div class="phone-wrapper">
                            <select name="countryCode" id="countryCode" class="form-select country-select" onchange="updateStatesAndCities()">
                                <option value="+60">Malaysia (+60)</option>
                                <option value="+66">Thailand (+66)</option>
                                <option value="+62">Indonesia (+62)</option>
                                <option value="+65">Singapore (+65)</option>
                                <option value="+673">Brunei (+673)</option>
                                <option value="+63">Philippines (+63)</option>
                                <option value="+84">Vietnam (+84)</option>
                            </select>
                            <input type="text" name="phoneNumber" id="phoneNumber" class="form-input phone-input"
                                placeholder="123456789" required inputmode="numeric"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                value="<?php echo htmlspecialchars($_POST['phoneNumber'] ?? preg_replace('/^\+\d+/', '', $user['phone'] ?? '')); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-grid-triple">
                    <div class="form-group">
                        <label>State<span class="star-required">*</span></label>
                        <select name="state" id="stateSelect" class="form-select" onchange="updateCitiesOnly()" required>
                            <option value="">Select State</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City<span class="star-required">*</span></label>
                        <select name="city" id="citySelect" class="form-select" required>
                            <option value="">Select City</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Postcode<span class="star-required">*</span></label>
                        <input type="text" name="zipCode" class="form-input" required inputmode="numeric"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="10"
                            value="<?php echo htmlspecialchars($_POST['zipCode'] ?? $user['postcode'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Shipping Zone -->
                <div style="margin-bottom: 20px;">
                    <div class="payment-title-row">Shipping Zone<span class="star-required">*</span></div>
                    <div class="zone-row">
                        <div class="zone-opt">
                            <input type="radio" name="_zone" id="zone_sem" value="semenanjung" checked onchange="setZone('semenanjung')">
                            <label for="zone_sem">Semenanjung<br><small>RM <?php echo number_format($ship_sem, 2); ?></small></label>
                        </div>
                        <div class="zone-opt">
                            <input type="radio" name="_zone" id="zone_bor" value="borneo" onchange="setZone('borneo')">
                            <label for="zone_bor">Sabah / Sarawak<br><small>RM <?php echo number_format($ship_bor, 2); ?></small></label>
                        </div>
                    </div>
                </div>

                <div class="checkout-divider" style="margin-bottom: 20px;"></div>

                <!-- Payment Method -->
                <div class="payment-title-row">Payment Method<span class="star-required">*</span></div>
                <div class="payment-options-container">
                    <div class="payment-box selected" id="boxCard" onclick="selectPaymentMode('card')">
                        <div class="payment-box-left">
                            <input type="radio" id="radioCard" name="_payment" value="card" checked>
                            <span>Card Payment</span>
                        </div>
                        <i class="fa-solid fa-credit-card payment-icon"></i>
                    </div>
                    <div class="payment-box" id="boxQr" onclick="selectPaymentMode('qr')">
                        <div class="payment-box-left">
                            <input type="radio" id="radioQr" name="_payment" value="qr">
                            <span>QR Payment</span>
                        </div>
                        <i class="fa-solid fa-qrcode payment-icon"></i>
                    </div>
                </div>

                <div id="cardDetailsPanel" class="card-details-panel">
                    <div class="card-row">
                        <label>Card Number</label>
                        <div class="input-with-icon">
                            <input type="text" name="card_number" class="form-input" placeholder="1234 1234 1234 1234" maxlength="19"
                                oninput="formatCard(this)">
                            <i class="fa-brands fa-cc-visa"></i>
                        </div>
                    </div>
                    <div class="card-inner-grid">
                        <div class="card-row">
                            <label>Expiration Date</label>
                            <input type="text" name="card_expiry" class="form-input" placeholder="MM / YY" maxlength="7"
                                oninput="formatExpiry(this)">
                        </div>
                        <div class="card-row">
                            <label>Security Code</label>
                            <div class="input-with-icon">
                                <input type="password" name="card_cvc" class="form-input" placeholder="CVC" maxlength="4"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="required-notice"><span class="star-required">*</span> Required Fields</div>
            </form>
        </div>

        <!-- ── RIGHT: SUMMARY ── -->
        <div class="summary-section">
            <div>
                <div class="cart-title">Your Cart (<?php echo count($cart_items); ?> item<?php echo count($cart_items) > 1 ? 's' : ''; ?>)</div>
                <div class="cart-items-list">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <div class="item-image-box">
                            <img src="../<?php echo htmlspecialchars(!empty($item['book_img']) ? $item['book_img'] : 'img/book1.jpg'); ?>"
                                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                                 onerror="this.src='../img/book1.jpg'">
                        </div>
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="item-desc"><?php echo htmlspecialchars($item['author']); ?></div>
                            <div class="item-qty">Qty: <?php echo $item['quantity']; ?> &nbsp;×&nbsp; RM <?php echo number_format($item['price'], 2); ?></div>
                        </div>
                        <div class="item-price">RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pricing-block">
                <div class="price-row">
                    <span>Subtotal</span>
                    <span>RM <?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="price-row">
                    <span>Shipping</span>
                    <span id="shippingDisplay">RM <?php echo number_format($ship_sem, 2); ?></span>
                </div>
                <div class="price-row total-row">
                    <span>Total</span>
                    <span id="totalDisplay">RM <?php echo number_format($subtotal + $ship_sem, 2); ?></span>
                </div>
                <button type="submit" form="checkoutForm" class="place-order-btn" onclick="return validateAndSubmit()">
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
            </div>
        </div>

    </div><!-- /.checkout-body -->
</div>

<!-- ── QR Modal ── -->
<div id="qrModalOverlay" class="modal-overlay <?php echo $show_qr ? 'active' : ''; ?>">
    <div class="qr-modal">
        <h3>Scan to Pay</h3>
        <p>Scan this QR code with your banking app, then tap <strong>Done</strong>.</p>
        <div class="qr-image-container">
            <img src="../img/payment_qr.png" alt="Payment QR Code"
                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22%23eee%22/><text x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22sans-serif%22 font-size=%2214%22 fill=%22%23999%22>Place your QR here</text></svg>'">
        </div>
        <button class="close-modal-btn" onclick="qrDone()"><i class="fas fa-check"></i> Done – I've Paid</button>
    </div>
</div>

<script>
    const SHIP_SEM  = <?php echo $ship_sem; ?>;
    const SHIP_BOR  = <?php echo $ship_bor; ?>;
    const SUBTOTAL  = <?php echo $subtotal; ?>;
    let currentZone = 'semenanjung';

    // ── Geo database ─────────────────────────────────────────────────────────
    const geoDatabase = {
        "+60": {
            "Johor":           ["Johor Bahru","Batu Pahat","Kluang","Muar","Segamat"],
            "Kedah":           ["Alor Setar","Sungai Petani","Kulim","Langkawi"],
            "Kelantan":        ["Kota Bharu","Machang","Pasir Puteh","Tanah Merah","Gua Musang"],
            "Melaka":          ["Melaka City","Alor Gajah","Jasin"],
            "Negeri Sembilan": ["Seremban","Port Dickson","Nilai","Rembau"],
            "Pahang":          ["Kuantan","Temerloh","Bentong","Raub","Cameron Highlands"],
            "Perak":           ["Ipoh","Taiping","Teluk Intan","Manjung","Lumut"],
            "Perlis":          ["Kangar","Arau","Padang Besar"],
            "Pulau Pinang":    ["George Town","Bayan Lepas","Butterworth","Bukit Mertajam"],
            "Sabah":           ["Kota Kinabalu","Sandakan","Tawau","Keningau","Lahad Datu"],
            "Sarawak":         ["Kuching","Miri","Sibu","Bintulu","Limbang"],
            "Selangor":        ["Shah Alam","Petaling Jaya","Subang Jaya","Klang","Kajang","Sepang"],
            "Terengganu":      ["Kuala Terengganu","Kemaman","Dungun","Marang"],
            "Kuala Lumpur":    ["Cheras","Setapak","Bukit Bintang","Wangsa Maju","Kepong"],
            "Labuan":          ["Victoria"],
            "Putrajaya":       ["Putrajaya"]
        },
        "+66": { "Bangkok":["Bang Kapi","Chatuchak","Khlong Toei"], "Chiang Mai":["Mueang Chiang Mai","Mae Rim","Hang Dong"], "Phuket":["Patong","Kathu","Chalong"] },
        "+62": { "Jakarta":["Jakarta Pusat","Jakarta Selatan","Jakarta Barat"], "Bali":["Denpasar","Badung","Gianyar"], "West Java":["Bandung","Bekasi","Depok"] },
        "+65": { "Central Region":["Downtown Core","Bukit Merah","Queenstown"], "East Region":["Tampines","Bedok","Pasir Ris"], "North Region":["Woodlands","Yishun","Sembawang"] },
        "+673": { "Brunei-Muara":["Bandar Seri Begawan","Gadong","Berakas"], "Belait":["Kuala Belait","Seria"], "Tutong":["Pekan Tutong"] },
        "+63": { "Metro Manila":["Manila","Quezon City","Makati"], "Cebu":["Cebu City","Mandaue","Lapu-Lapu"], "Davao":["Davao City"] },
        "+84": { "Ho Chi Minh City":["District 1","District 3","Thu Duc"], "Hanoi":["Ba Dinh","Hoan Kiem","Dong Da"], "Da Nang":["Hai Chau","Thanh Khe"] }
    };

    // Pre-fill saved state/city values from PHP
    const savedState = "<?php echo addslashes($user['state'] ?? ''); ?>";
    const savedCity  = "<?php echo addslashes($user['city']  ?? ''); ?>";

    function updateStatesAndCities() {
        const cc = document.getElementById('countryCode').value;
        const stateEl = document.getElementById('stateSelect');
        const cityEl  = document.getElementById('citySelect');
        stateEl.innerHTML = '<option value="">Select State</option>';
        cityEl.innerHTML  = '<option value="">Select City</option>';
        if (geoDatabase[cc]) {
            Object.keys(geoDatabase[cc]).forEach(s => {
                const o = document.createElement('option');
                o.value = s; o.textContent = s;
                if (s === savedState) o.selected = true;
                stateEl.appendChild(o);
            });
            if (savedState) updateCitiesOnly(savedCity);
        }
    }

    function updateCitiesOnly(preselectCity) {
        const cc    = document.getElementById('countryCode').value;
        const state = document.getElementById('stateSelect').value;
        const cityEl = document.getElementById('citySelect');
        cityEl.innerHTML = '<option value="">Select City</option>';
        if (cc && state && geoDatabase[cc][state]) {
            geoDatabase[cc][state].forEach(c => {
                const o = document.createElement('option');
                o.value = c; o.textContent = c;
                if (c === (preselectCity || '')) o.selected = true;
                cityEl.appendChild(o);
            });
        }
    }

    function setZone(zone) {
        currentZone = zone;
        document.getElementById('hiddenZone').value = zone;
        const fee = (zone === 'borneo') ? SHIP_BOR : SHIP_SEM;
        document.getElementById('shippingDisplay').textContent = 'RM ' + fee.toFixed(2);
        document.getElementById('totalDisplay').textContent    = 'RM ' + (SUBTOTAL + fee).toFixed(2);
    }

    function selectPaymentMode(mode) {
        document.getElementById('hiddenPayment').value = mode;
        document.getElementById('radioCard').checked = (mode === 'card');
        document.getElementById('radioQr').checked   = (mode === 'qr');
        document.getElementById('cardDetailsPanel').style.display = (mode === 'card') ? 'block' : 'none';
        document.getElementById('boxCard').classList.toggle('selected', mode === 'card');
        document.getElementById('boxQr').classList.toggle('selected',  mode === 'qr');
    }

    function formatCard(el) {
        let v = el.value.replace(/\D/g, '').substring(0, 16);
        el.value = v.replace(/(.{4})/g, '$1 ').trim();
    }

    function formatExpiry(el) {
        let v = el.value.replace(/\D/g, '').substring(0, 4);
        if (v.length >= 3) v = v.substring(0,2) + ' / ' + v.substring(2);
        el.value = v;
    }

    function validateAndSubmit() {
        const mode = document.getElementById('hiddenPayment').value;
        if (mode === 'card') {
            const num = document.querySelector('[name="card_number"]').value.replace(/\s/g,'');
            const exp = document.querySelector('[name="card_expiry"]').value;
            const cvc = document.querySelector('[name="card_cvc"]').value;
            if (num.length < 16) { alert('Please enter a valid 16-digit card number.'); return false; }
            if (exp.length < 7)  { alert('Please enter a valid expiration date (MM / YY).'); return false; }
            if (cvc.length < 3)  { alert('Please enter a valid security code.'); return false; }
        }
        return true;
    }

    function qrDone() {
        window.location.href = 'cust_payment.php?qr_done=1';
    }

    window.onload = function() {
        updateStatesAndCities();
    };
</script>
</body>
</html>
