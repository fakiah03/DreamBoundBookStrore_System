<?php
session_start();
require_once 'db.php';

// Security: only logged-in customers can add to cart
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: Auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $user_id  = intval($_SESSION['user_id']);
    $book_id  = intval($_POST['book_id']);
    $format   = in_array($_POST['format'] ?? '', ['paperback','hardcover','ebook']) ? $_POST['format'] : 'paperback';

    // Confirm the book exists and has stock before adding
    $check = $conn->query("SELECT stock FROM books WHERE id = $book_id");
    if ($check && $check->num_rows > 0) {
        $book = $check->fetch_assoc();

        if ($book['stock'] > 0) {
            // If this book + format is already in the cart, increase quantity instead of duplicating
            $existing = $conn->query("SELECT id, quantity FROM cart WHERE user_id=$user_id AND book_id=$book_id AND format='$format'");
            if ($existing && $existing->num_rows > 0) {
                $row = $existing->fetch_assoc();
                $conn->query("UPDATE cart SET quantity = quantity + 1 WHERE id = {$row['id']}");
            } else {
                $conn->query("INSERT INTO cart (user_id, book_id, quantity, format) VALUES ($user_id, $book_id, 1, '$format')");
            }
        }
    }
}

// Send the customer to their real cart page to see the result
header("Location: Customer/cust_cart.php");
exit();
?>
