<?php
session_start();
require_once '../db.php'; 

// 1. SECURITY RESTRICTION: Ensure that the customer is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Customer';


// 2. Action handler for updating cart items (increase, decrease, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cart_id = intval($_POST['cart_id']);
    $action = $_POST['action'];

    if ($action === 'increase') {
        $conn->query("UPDATE cart SET quantity = quantity + 1 WHERE id = $cart_id AND user_id = $user_id");
    } elseif ($action === 'decrease') {
        $conn->query("UPDATE cart SET quantity = GREATEST(quantity - 1, 1) WHERE id = $cart_id AND user_id = $user_id");
    } elseif ($action === 'remove') {
        $conn->query("DELETE FROM cart WHERE id = $cart_id AND user_id = $user_id");
    }
    
    // Refresh the page to reflect changes
    header("Location: cust_cart.php");
    exit();
}

// 3. TAKE SHIPPING FEE FROM DATABASE
$shipping_fee = 5.00; 
$setting_query = $conn->query("SELECT ship_semenanjung FROM store_settings LIMIT 1");
if ($setting_query && $setting_query->num_rows > 0) {
    $store_data = $setting_query->fetch_assoc();
    $shipping_fee = $store_data['ship_semenanjung'];
}

// 4. TAKE ITEM DATA FROM THE CUSTOMER'S CART
$cart_query = $conn->query("
    SELECT c.id as cart_id, c.quantity, b.title, b.author, b.price, b.book_img 
    FROM cart c 
    JOIN books b ON c.book_id = b.id 
    WHERE c.user_id = $user_id
");

$subtotal = 0;
$total_items = 0;
$cart_items = [];

if ($cart_query && $cart_query->num_rows > 0) {
    while ($row = $cart_query->fetch_assoc()) {
        $cart_items[] = $row;
        $subtotal += ($row['price'] * $row['quantity']);
        $total_items += $row['quantity'];
    }
}

// if cart is empty, set shipping fee to 0
if ($total_items == 0) {
    $shipping_fee = 0;
}

$total_price = $subtotal + $shipping_fee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Cart</title>
    
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

        .content { 
            flex: 1; padding: 50px; overflow-y: auto; background: #FC9D01;
            border-top-left-radius: 24px; border-bottom-left-radius: 24px;
        }

        .content::-webkit-scrollbar { width: 6px; }
        .content::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.1); border-radius: 10px; }

        header h1 { font-size: 46px; color: var(--primary-blue); margin-bottom: 5px; }
        header p { font-size: 18px; color: #ffffff; margin-bottom: 35px; }

        .cart-container { display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap; }

        .cart-main {
            flex: 2; min-width: 500px; background: white; border-radius: 24px;
            padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th { text-align: left; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; color: #94a3b8; font-size: 16px; text-transform: uppercase; }
        .cart-item { border-bottom: 1px solid #f1f5f9; }
        .cart-item:last-child { border-bottom: none; }
        .cart-item td { padding: 20px 0; vertical-align: middle; }

        .book-details { display: flex; align-items: center; gap: 15px; }
        .cart-img-wrapper { width: 70px; height: 95px; background: #f8fafc; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .cart-img-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        
        .book-info h3 { font-size: 18px; color: var(--primary-blue); margin-bottom: 2px; }
        .book-info .author { color: #94a3b8; font-size: 14px; }
        .item-price { font-size: 18px; color: var(--primary-blue); font-weight: bold; }

        /* Kumpulan Borang untuk Butang */
        form.inline-form { display: inline-flex; align-items: center; }

        .quantity-control { display: flex; align-items: center; gap: 10px; }
        .qty-btn { 
            background: #f1f5f9; border: none; width: 28px; height: 28px; 
            border-radius: 8px; cursor: pointer; display: flex; align-items: center; 
            justify-content: center; color: var(--primary-blue); transition: all 0.2s; 
        }
        .qty-btn:hover { background: var(--primary-blue); color: white; }
        .qty-val { font-size: 16px; font-weight: bold; color: var(--primary-blue); min-width: 20px; text-align: center; }

        .remove-btn { background: none; border: none; color: #ff6b6b; cursor: pointer; font-size: 16px; transition: transform 0.2s; }
        .remove-btn:hover { transform: scale(1.2); }

        .cart-summary {
            flex: 1; min-width: 300px; background: var(--primary-blue); color: white;
            border-radius: 24px; padding: 30px; box-shadow: 0 10px 30px rgba(10, 38, 71, 0.15);
        }

        .summary-title { font-size: 26px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 20px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 18px; color: rgba(255,255,255,0.8); }
        .summary-row.total { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; margin-top: 15px; font-size: 22px; font-weight: bold; color: #FC9D01; }

        .checkout-btn {
            width: 100%; background: #FC9D01; color: var(--primary-blue); border: none;
            padding: 15px; border-radius: 14px; font-size: 18px; font-weight: bold; cursor: pointer;
            margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 8px 20px rgba(242, 148, 0, 0.2); transition: all 0.3s ease; text-decoration: none;
        }

        .checkout-btn:hover { transform: translateY(-3px); box-shadow: 0 12px 25px rgba(242, 148, 0, 0.3); background: #ffffff; }
        
        /* Disable checkout button if empty */
        .checkout-btn[disabled] { opacity: 0.5; cursor: not-allowed; transform: none; background: #ccc; box-shadow: none; color: #666; }

        .continue-shopping {
            display: inline-flex; align-items: center; gap: 8px; color: var(--primary-blue);
            text-decoration: none; font-size: 18px; margin-bottom: 20px; transition: transform 0.2s;
        }
        .continue-shopping:hover { transform: translateX(-5px); }

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
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_cart.php" class="menu-item active"><i class="fas fa-shopping-bag"></i> CART</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_orders.php" class="menu-item"><i class="fas fa-receipt"></i> ORDERS</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_settings.php" class="menu-item"><i class="fas fa-sliders-h"></i> SETTINGS</a></li>
                </ul>
            </nav>

            <button class="logout-btn" onclick="location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
        </aside>

        <main class="content">
            <header>
                <a href="cust_home.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </a>
                <h1>Your Shopping Cart</h1>
                <p>Review your items before proceeding to secure checkout</p>
            </header>

            <div class="cart-container">
                
                <div class="cart-main">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Book Details</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($cart_items) > 0): ?>
                                <?php foreach ($cart_items as $item): ?>
                                    <tr class="cart-item">
                                        <td>
                                            <div class="book-details">
                                                <div class="cart-img-wrapper">
                                                    <img src="../img/<?php echo !empty($item['book_img']) ? htmlspecialchars($item['book_img']) : 'book1.jpg'; ?>" alt="Book Cover">
                                                </div>
                                                <div class="book-info">
                                                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                                    <p class="author"><?php echo htmlspecialchars($item['author']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="item-price">RM <?php echo number_format($item['price'], 2); ?></span></td>
                                        <td>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                <div class="quantity-control">
                                                    <button type="submit" name="action" value="decrease" class="qty-btn" title="Decrease Quantity"><i class="fas fa-minus"></i></button>
                                                    <span class="qty-val"><?php echo $item['quantity']; ?></span>
                                                    <button type="submit" name="action" value="increase" class="qty-btn" title="Increase Quantity"><i class="fas fa-plus"></i></button>
                                                </div>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                <button type="submit" name="action" value="remove" class="remove-btn" title="Remove Item"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding: 40px; color:#94a3b8; font-size:18px;">
                                        Your cart is currently empty. <br> <br>
                                        <a href="cust_home.php" style="color:var(--accent-orange); text-decoration:none;"><i class="fas fa-arrow-right"></i> Browse Books</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-summary">
                    <h2 class="summary-title">Order Summary</h2>
                    <div class="summary-row">
                        <span>Subtotal (<?php echo $total_items; ?> items)</span>
                        <span>RM <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping Fee</span>
                        <span>RM <?php echo number_format($shipping_fee, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total Price</span>
                        <span>RM <?php echo number_format($total_price, 2); ?></span>
                    </div>
                    
                    <button class="checkout-btn" onclick="location.href='cust_payment.php'" <?php echo ($total_items == 0) ? 'disabled' : ''; ?>>
                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                    </button>
                </div>
            </div>
        </main>
    </div>

</body>
</html>