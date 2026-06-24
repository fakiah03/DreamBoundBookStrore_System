<?php
session_start();
require_once '../db.php'; 

// 1. SECURITY RESTRICTION: Ensure only authorized Admins can view this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

// 2. Validate incoming Order ID
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    die("Error: Order ID not specified.");
}

$order_id = intval($_GET['order_id']);

// 3. Retrieve order details and customer data
$sql_order = "
    SELECT o.id as order_id, o.created_at, o.total_amount, o.status, u.fullname, u.email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = $order_id
";
$order_result = $conn->query($sql_order);

if (!$order_result || $order_result->num_rows == 0) {
    die("Error: Invoice records for ID #$order_id could not be found.");
}

$order = $order_result->fetch_assoc();
$display_id = "DB-" . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT);

// 4. Retrieve purchased book details for this order
$sql_items = "
    SELECT b.title, oi.quantity, b.price 
    FROM order_items oi 
    JOIN books b ON oi.book_id = b.id 
    WHERE oi.order_id = $order_id
";
$items_result = $conn->query($sql_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $display_id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            color: #333;
            margin: 0;
            padding: 40px 0;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            background: #fff;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .header-table td {
            padding: 0;
            vertical-align: top;
        }
        .title h1 {
            margin: 0;
            color: #0E2C46;
            font-size: 2rem;
            letter-spacing: 1px;
        }
        .title p {
            margin: 5px 0;
            color: #777;
            font-size: 0.9rem;
        }
        .invoice-details {
            text-align: right;
        }
        .invoice-details h2 {
            margin: 0 0 10px 0;
            color: #FC9D01;
        }
        .info-section {
            margin-bottom: 30px;
            border-top: 2px solid #0E2C46;
            border-bottom: 2px solid #0E2C46;
            padding: 15px 0;
        }
        .info-section table {
            width: 100%;
        }
        .info-section td {
            width: 50%;
            vertical-align: top;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #0E2C46;
            color: white;
            padding: 12px;
            font-size: 1rem;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .total-row {
            text-align: right;
            font-size: 1.2rem;
            font-weight: bold;
            color: #0E2C46;
            padding-top: 20px;
        }
        .no-print-zone {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
        }
        .btn {
            background-color: #0E2C46;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn:hover {
            background-color: #1a446c;
        }
        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
        }
        .btn-secondary:hover {
            background-color: #d1d5db;
        }
        
        /* CSS MEDIA QUERY SPECIFICALLY FOR PRINT MODE */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .invoice-box {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .no-print-zone {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-zone">
        <a href="javascript:window.close();" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Close Window</a>
        <button onclick="window.print();" class="btn"><i class="fas fa-print"></i> Print Invoice / Save PDF</button>
    </div>

    <div class="invoice-box">
        <table class="header-table">
            <tr>
                <td class="title">
                    <h1>DREAMBOUND BOOKSTORE</h1>
                    <p>Digital Book Management & Inventory System</p>
                    <p>Email: support@dreambound.com</p>
                </td>
                <td class="invoice-details">
                    <h2>INVOICE</h2>
                    <p><strong>Invoice No:</strong> #<?php echo $display_id; ?></p>
                    <p><strong>Date Ordered:</strong> <?php echo date("d F Y", strtotime($order['created_at'])); ?></p>
                    <p><strong>Logistics Status:</strong> <?php echo strtoupper($order['status']); ?></p>
                </td>
            </tr>
        </table>

        <div class="info-section">
            <table>
                <tr>
                    <td>
                        <strong>Bill To (Customer Details):</strong><br>
                        Name: <?php echo htmlspecialchars($order['fullname'] ?? 'Unknown Buyer'); ?><br>
                        Email: <?php echo htmlspecialchars($order['email'] ?? '-'); ?>
                    </td>
                    <td>
                        <strong>Issued By:</strong><br>
                        Dreambound Bookstore Portal<br>
                        Verification Status: **ADMIN VERIFIED**
                    </td>
                </tr>
            </table>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Book Title</th>
                    <th>Unit Price</th>
                    <th style="text-align: center;">Quantity</th>
                    <th style="text-align: right;">Total Price</th>
                </tr>
            </thead>
            <tbody>
                <!--Variable Setup & Data Validation-->
                <?php 
                $count = 1;
                if ($items_result && $items_result->num_rows > 0): 
                    while($item = $items_result->fetch_assoc()):
                        $subtotal = $item['price'] * $item['quantity'];
                ?>
                <tr>
                    <!-- Item Row Display (Within Loop) -->
                    <td><?php echo $count++; ?></td>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td>RM <?php echo number_format($item['price'], 2); ?></td>
                    <td style="text-align: center;">x<?php echo $item['quantity']; ?></td>
                    <td style="text-align: right;">RM <?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <?php 
                    endwhile;
                else: 
                ?>
                <tr>
                    <!--Loop Closure & Condition When No Data Is Available (Fallback)-->
                    <td colspan="5" style="text-align: center;">No registered items found for this invoice.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="total-row">
            GRAND TOTAL: RM <?php echo number_format($order['total_amount'], 2); ?>
        </div>
    </div>

</body>
</html>