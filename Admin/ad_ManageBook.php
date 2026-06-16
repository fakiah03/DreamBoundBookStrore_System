<?php
session_start();
require_once '../db.php'; 

// 1. SECURITY RESTRICTION: Ensure only authorized Admins can enter this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

$alert_message = "";

// 2. Function to add a book (POST request)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_book'])) {
    $title  = mysqli_real_escape_string($conn, trim($_POST['title']));
    $author = mysqli_real_escape_string($conn, trim($_POST['author']));
    $genre  = mysqli_real_escape_string($conn, trim($_POST['genre']));
    $price  = floatval($_POST['price']);
    $stock  = intval($_POST['stock']);
    
    // For the image, we'll use default.jpg as a placeholder if no upload is provided
    $book_img = 'img/default-book.jpg';
    if(isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0){
        $book_img = 'img/' . basename($_FILES['cover_image']['name']);
        // The actual code will use move_uploaded_file to move the uploaded file to the ../img/ folder
    }

    $sql = "INSERT INTO books (title, author, genre, price, stock, book_img) VALUES ('$title', '$author', '$genre', '$price', '$stock', '$book_img')";
    
    if ($conn->query($sql) === TRUE) {
        $conn->query("INSERT INTO system_logs (log_message) VALUES ('Admin menambah buku baharu: $title')");
        $alert_message = "Success!\\n\\\"$title\\\" has been recorded into the Dreambound Bookstore local inventory.";
    }
}

// 3. Function to update book price only (following the original style)
if (isset($_GET['edit_id']) && isset($_GET['new_price'])) {
    $id = intval($_GET['edit_id']);
    $new_price = floatval($_GET['new_price']);
    
    $conn->query("UPDATE books SET price = '$new_price' WHERE id = $id");
    $conn->query("INSERT INTO system_logs (log_message) VALUES ('Admin menukar harga buku ID: $id')");
    $alert_message = "Inventory Updated!\\nNew price is set to RM " . number_format($new_price, 2);
}

// 4. Function to delete a book
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM books WHERE id = $id");
    $conn->query("INSERT INTO system_logs (log_message) VALUES ('Admin memadam buku ID: $id')");
    $alert_message = "Book has been removed permanently.";
}

// 5. Retrieve statistics for the dashboard widget.
$stat_books = $conn->query("SELECT COUNT(id) as total FROM books")->fetch_assoc()['total'];
$stat_critical = $conn->query("SELECT COUNT(id) as total FROM books WHERE stock <= 5")->fetch_assoc()['total'];
$stat_genres = $conn->query("SELECT COUNT(DISTINCT genre) as total FROM books")->fetch_assoc()['total'];

// 6. Retrieve the list of books
$books = $conn->query("SELECT * FROM books ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Manage Book</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    
    <style>
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Englebert', 
            cursive, sans-serif; }

        body { 
            background-color: #FC9D01; 
            display: flex; 
            height: 100vh; 
            overflow: hidden; }

        .container { 
            display: flex; 
            width: 100%; 
            height: 100vh; }

        .sidebar { 
            width: 280px; 
            background-color: #0E2C46; 
            color: white; 
            display: flex; 
            flex-direction: column; 
            padding: 30px 0; 
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.2); 
            flex-shrink: 0; }

        .profile-section { 
            text-align: center; 
            padding: 0 20px 25px 20px; 
            border-bottom: 2px solid rgba(255, 255, 255, 0.1); }

        .logo-img { 
            width: 150px; 
            margin-bottom: 10px; }

        .profile-section h3 { 
            font-size: 1.4rem; 
            letter-spacing: 1px; 
            color: #ffffff; }

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
            margin-bottom: 8px; }

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
            background: rgba(255, 255, 255, 0.05); }

        .nav-links li.active a, .nav-links li a:hover { 
            background: #FC9D01; 
            color: #0E2C46; 
            font-weight: bold; 
            transform: translateX(5px); }

        .logout-container { 
            padding: 0 15px; }
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
            justify-content: center; g
            ap: 10px; }

        .btn-logout:hover { 
            background: #FC9D01; 
            color: #0E2C46; }

        .main-content { 
            flex-grow: 1; 
            background-color: #FC9D01; 
            padding: 30px; 
            overflow-y: auto; }

        .page-title { 
            font-size: 2.5rem; 
            color: #0E2C46; 
            margin-bottom: 20px; 
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5); }

        .upper-management-grid { 
            display: grid; 
            grid-template-columns: 1.2fr 1fr; 
            gap: 25px; 
            margin-bottom: 25px; }

        .panel-box { 
            background: #FDF5E6; 
            border: 2px solid #0E2C46; 
            border-radius: 15px; 
            padding: 20px; 
            color: #0E2C46; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        .panel-box h3 { 
            font-size: 1.4rem; 
            margin-bottom: 15px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            border-bottom: 2px solid #0E2C46;
            padding-bottom: 5px; }

        .book-form { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 12px; }

        .form-group { 
            display: flex; 
            flex-direction: column; 
            gap: 5px; }

        .form-group.full-width { 
            grid-column: span 2; }

        .form-group label { 
            font-size: 1rem; 
            font-weight: bold; }

        .form-group input, .form-group select { 
            padding: 8px 12px; 
            border: 2px solid #0E2C46; 
            border-radius: 6px; 
            font-size: 0.95rem; 
            outline: none; 
            background: white; }

        .btn-submit { 
            grid-column: span 2; b
            ackground: #0E2C46; 
            color: white; 
            border: none; 
            padding: 10px; 
            font-size: 1.1rem; 
            font-weight: bold; 
            border-radius: 6px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
            margin-top: 5px; 
            transition: background 0.2s; }

        .btn-submit:hover { 
            background: #1a446c; }

        .mini-stats-stack { 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
            height: 100%; }

        .mini-stat-card { 
            background: white; 
            border: 2px solid #0E2C46; 
            border-radius: 10px; 
            padding: 15px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; }

        .mini-stat-card i { 
            font-size: 2rem; 
            color: #FC9D01; 
            background: #0E2C46; 
            padding: 10px; 
            border-radius: 8px; }

        .mini-stat-info h4 { 
            font-size: 0.95rem; 
            color: #555; }

        .mini-stat-info p { 
            font-size: 1.5rem; 
            font-weight: bold; }

        .stock-critical {
            color: #ef4444;}

        .stock-icon {
            background: #ef4444;color: white;}

        .reminder-box { 
            background: #0E2C46; 
            color: white; 
            border: 2px solid #0E2C46; 
            border-radius: 10px; 
            padding: 20px; 
            flex-grow: 1; 
            display: flex; 
            flex-direction: column; 
            gap: 10px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        .reminder-box h4 { 
            color: #FC9D01; 
            font-size: 1.2rem; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            border-bottom: 1px solid rgba(255,255,255,0.2); p
            adding-bottom: 5px; }

        .reminder-box ul { 
            list-style: none; 
            font-size: 0.95rem; 
            display: flex; 
            flex-direction: column; 
            gap: 8px; }

        .reminder-box ul li { 
            display: flex; 
            align-items: flex-start; 
            gap: 8px; 
            line-height: 1.4; }

        .reminder-box ul li i { 
            color: #FC9D01; 
            margin-top: 3px; 
            font-size: 0.8rem; }

        .table-panel { 
            background-color: #FDF5E6; 
            border: 2px solid #0E2C46; 
            border-radius: 15px; 
            padding: 20px; 
            box-shadow: 0 6px 12px rgba(0,0,0,0.1); 
            color: #0E2C46; }

        .table-header-control { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; }

        .search-box { 
            display: flex; 
            background: white; 
            border: 2px solid #0E2C46; 
            border-radius: 25px; 
            overflow: hidden; 
            padding: 2px; 
            width: 100%; 
            max-width: 350px; }

        .search-box input { 
            border: none; 
            padding: 8px 15px; 
            outline: none; 
            flex-grow: 1; 
            font-size: 0.95rem; }

        .search-box button { 
            background: #0E2C46; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            order-radius: 23px; 
            cursor: pointer; }

        .book-table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
            font-size: 1.05rem; }

        .book-table th { 
            background-color: #0E2C46; 
            color: white; 
            padding: 12px 15px; 
            font-size: 1.1rem; }

        .book-table td { 
            padding: 10px 15px; 
            border-bottom: 1px solid rgba(14, 44, 70, 0.2); 
            background-color: rgba(255, 255, 255, 0.3); 
            vertical-align: middle; }

        .book-table tr:hover td { 
            background-color: rgba(252, 157, 1, 0.1); }

        .book-cover-cell { 
            width: 50px; 
            height: auto; 
            border-radius: 4px; 
            border: 1px solid #0E2C46; }

        .stock-indicator { 
            font-weight: bold; 
            padding: 2px 6px; 
            border-radius: 4px; }

        .stock-indicator.low { 
            background-color: #fecaca; 
            color: #991b1b; }

        .stock-indicator.good { 
            background-color: #bbf7d0; 
            color: #166534; }

        .action-cell { 
            display: flex; 
            gap: 8px; }

        .btn-table { 
            padding: 6px 12px; 
            border: 1px solid #0E2C46; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            font-weight: bold; 
            transition: all 0.2s; 
            text-decoration: none; }

        .btn-table.edit { 
            background-color: #FC9D01; 
            color: #0E2C46; }

        .btn-table.edit:hover { 
            background-color: #e08b00; }

        .btn-table.delete { 
            background-color: #ef4444; 
            color: white; 
            border-color: #ef4444; }

        .btn-table.delete:hover { 
            background-color: #dc2626; }
            
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
                    <p><?php echo isset($_SESSION['fullname']) ? htmlspecialchars(strtoupper($_SESSION['fullname'])) : 'JAMES BUBUYA'; ?></p>
                </div>
            </div>

            <ul class="nav-links">
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_DashBoard.php"><i class="fas fa-chart-line"></i> DASHBOARD STATUS</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_OrderInfo.php"><i class="fas fa-shopping-cart"></i> ORDER INFORMATION</a></li>
                <li class="active"><a href="/DreamBoundBookStrore_system/Admin/ad_ManageBook.php"><i class="fas fa-book"></i> MANAGE BOOK</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_CustomerInfo.php"><i class="fas fa-users"></i> CUSTOMER INFORMATION</a></li>
                <li><a href="/DreamBoundBookStrore_system/Admin/ad_settings.php"><i class="fas fa-sliders-h"></i> SETTING</a></li>
            </ul>

            <div class="logout-container">
                <button class="btn-logout" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
            </div>
        </nav>

        <main class="main-content">
            <h1 class="page-title">Book Inventory Management</h1>

            <section class="upper-management-grid">
                <div class="panel-box">
                    <h3><i class="fas fa-plus-circle"></i> Register New Book Product</h3>
                    <form class="book-form" method="POST" action="/DreamBoundBookStrore_system/Admin/ad_ManageBook.php" enctype="multipart/form-data">
                        <div class="form-group full-width">
                            <label>Book Title</label>
                            <input type="text" name="title" id="bookTitle" placeholder="Enter full literary title..." required>
                        </div>
                        <div class="form-group">
                            <label>Author / Writer</label>
                            <input type="text" name="author" id="bookAuthor" placeholder="Author name" required>
                        </div>
                        <div class="form-group">
                            <label for="bookGenre">Book Genre/Category</label>
                            <select name="genre" id="bookGenre">
                                <option>Fiction</option>
                                <option>Self-Help</option>
                                <option>Classic</option>
                                <option>Drama</option>
                                <option>Academic</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Retail Price (RM)</label>
                            <input type="number" name="price" id="bookPrice" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label>Initial Stock Quantity</label>
                            <input type="number" name="stock" id="bookStock" placeholder="Units count" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="bookCover">Upload Book Cover Image</label>
                            <input type="file" name="cover_image" id="bookCover" accept="image/*">
                        </div>
                        <button type="submit" name="add_book" class="btn-submit"><i class="fas fa-save"></i> Save Book to Inventory</button>
                    </form>
                </div>

                <div class="mini-stats-stack">
                    <div class="mini-stat-card">
                        <div class="mini-stat-info">
                            <h4>Total Book Titles</h4>
                            <p><?php echo number_format($stat_books); ?> Titles</p>
                        </div>
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="mini-stat-card">
                        <div class="mini-stat-info">
                            <h4>Out of Stock Alert</h4>
                            <p class="stock-critical"><?php echo number_format($stat_critical); ?> Titles Critical</p>
                        </div>
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    </div>
                    <div class="mini-stat-card">
                        <div class="mini-stat-info">
                            <h4>Active Categories</h4>
                            <p><?php echo number_format($stat_genres); ?> Literary Genres</p>
                        </div>
                        <i class="fas fa-th-list"></i>
                    </div>

                    <div class="reminder-box">
                        <h4><i class="fas fa-bell"></i> System Operations Guide</h4>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Check restock requests from logistics every Monday.</li>
                            <li><i class="fas fa-check-circle"></i> Ensure book cover images are under 2MB before upload.</li>
                            <li><i class="fas fa-check-circle"></i> Delete function is permanent; use with absolute caution.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="table-panel">
                <div class="table-header-control">
                    <h3><i class="fas fa-boxes"></i> Existing Book Repository</h3>
                    <div class="search-box">
                        <input type="text" placeholder="Search Title, Author or ISBN...">
                        <button title="Search"><i class="fas fa-search"></i></button>
                    </div>
                </div>

                <table class="book-table">
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Genre</th>
                            <th>Price</th>
                            <th>Stock Status</th>
                            <th>Inventory Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($books && $books->num_rows > 0): ?>
                            <?php while($row = $books->fetch_assoc()): ?>
                                <tr>
                                    <td><img src="../<?php echo !empty($row['book_img']) ? $row['book_img'] : 'book1.jpg'; ?>" alt="Cover" class="book-cover-cell" onerror="this.src='../img/logo1.png'"></td>
                                    <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['author']); ?></td>
                                    <td><?php echo htmlspecialchars($row['genre']); ?></td>
                                    <td>RM <?php echo number_format($row['price'], 2); ?></td>
                                    <td>
                                        <?php if($row['stock'] <= 5): ?>
                                            <span class="stock-indicator low"><?php echo $row['stock']; ?> Units Left</span>
                                        <?php else: ?>
                                            <span class="stock-indicator good"><?php echo $row['stock']; ?> Units</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-cell">
                                        <button class="btn-table edit" onclick="triggerEdit(<?php echo $row['id']; ?>, '<?php echo addslashes($row['title']); ?>', <?php echo $row['price']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                        
                                        <a href="ad_ManageBook.php?delete_id=<?php echo $row['id']; ?>" class="btn-table delete" onclick="return confirm('Are you sure you want to permanently delete \'<?php echo addslashes($row['title']); ?>\' from the store repository?');"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No books currently in inventory.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script>
        // PHP alert messages are rendered through JavaScript, maintaining the same popup appearance as in the original HTML implementation.
        <?php if(!empty($alert_message)): ?>
            alert("<?php echo $alert_message; ?>");
        <?php endif; ?>

        // Book information update operation (Only the book price is updated while retaining the original PROMPT).
        function triggerEdit(bookId, bookName, currentPrice) {
            let newPrice = prompt("Update price for \"" + bookName + "\":", currentPrice);
            if (newPrice != null && !isNaN(newPrice)) {
                // Transmits the update command to the PHP script through a URL request.
                window.location.href = "ad_ManageBook.php?edit_id=" + bookId + "&new_price=" + newPrice;
            }
        }

        function confirmLogout() {
            if (confirm("Are you sure you want to log out from the admin platform?")) {
                window.location.href = "../logout.php";
            }
        }
    </script>
</body>
</html>