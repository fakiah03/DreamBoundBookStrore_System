<?php
// 1. KAWALAN SESI (SESSION) & KESELAMATAN
session_start();

// Semak jika pengguna belum log masuk, tendang ke laman login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}

// Ambil nama penuh daripada session. Jika tiada, letak 'Guest'
$fullname = $_SESSION['fullname'] ?? 'Guest';

// 2. SAMBUNGAN PANGKALAN DATA (DATABASE)
// Sila pastikan path fail db_connect.php ini betul mengikut struktur folder anda
include('../db.php'); 

// 3. PROSES FUNGSI CARIAN (SEARCH)
$search = '';
if (isset($_GET['search'])) {
    // Tapis input carian untuk keselamatan SQL Injection
    $search = mysqli_real_escape_string($conn, $_GET['search']);
}

// Bina query SQL berdasarkan sama ada pengguna membuat carian atau tidak
if ($search != '') {
    $query = "SELECT * FROM books WHERE title LIKE '%$search%' OR author LIKE '%$search%' ORDER BY id DESC";
} else {
    $query = "SELECT * FROM books ORDER BY id DESC";
}

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Shop</title>
    
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

        /* SIDEBAR STYLE */
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
            padding: 14px 20px; 
            margin-bottom: 10px; 
            border-radius: 14px; 
            font-size: 18px; 
            letter-spacing: 0.5px;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .menu-item i {
            margin-right: 15px;
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .menu-item:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }

        .menu-item:hover i {
            transform: scale(1.2);
        }

        .menu-item.active { 
            background: var(--accent-orange); 
            color: #0A2647;
            box-shadow: 0 8px 20px rgba(242, 148, 0, 0.3);
        }

        .logout-btn { 
            margin-top: auto; 
            background: rgba(255, 255, 255, 0.05);
            color: #ff6b6b; 
            border: 1px solid rgba(255, 77, 77, 0.2); 
            padding: 14px 20px; 
            cursor: pointer; 
            border-radius: 14px; 
            width: 100%; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff4d4d;
            color: white;
            box-shadow: 0 8px 20px rgba(255, 77, 77, 0.2);
            border-color: transparent;
        }

        /* MAIN CONTENT STYLE */
        .content { 
            flex: 1; 
            padding: 50px; 
            overflow-y: auto; 
            background: #FC9D01;
            border-top-left-radius: 24px; 
            border-bottom-left-radius: 24px;
        }

        .content::-webkit-scrollbar {
            width: 6px;
        }
        .content::-webkit-scrollbar-thumb {
            background-color: rgba(0,0,0,0.1);
            border-radius: 10px;
        }

        header h1 { 
            font-size: 46px; 
            color: var(--primary-blue); 
            margin-bottom: 5px;
        }

        header p { 
            font-size: 18px; 
            color: #ffffff; 
            margin-bottom: 35px; 
        }

        /* SEARCH BAR */
        .search-container { 
            position: relative; 
            width: 100%; 
            max-width: 500px;
            margin-bottom: 45px;
        }

        .search-input { 
            width: 100%; 
            padding: 12px 20px 12px 52px; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            outline: none; 
            background-color: #f8fafc; 
            font-size: 18px; 
            color: var(--primary-blue);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--accent-orange);
            background-color: #ffffff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
        }

        .search-icon { 
            position: absolute; 
            left: 20px; 
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8; 
            font-size: 18px;
        }

        /* BOOK GRID SECTION */
        .book-grid {
            margin-top: 20px;
        }

        .section-title {
            font-size: 32px;
            color: var(--primary-blue);
            margin-bottom: 25px;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--accent-orange);
            border-radius: 2px;
        }

        .book-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 30px;
        }

        /* BOOK CARD DESIGN */
        .book-item {
            background-color: white;
            padding: 14px;
            border-radius: 20px;
            width: 220px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            display: flex;
            flex-direction: column;
            border: 1px solid #f1f5f9;
            position: relative;
        }

        .book-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 35px rgba(10, 38, 71, 0.08);
            border-color: rgba(242, 148, 0, 0.2);
        }

        .book-image-wrapper {
            width: 100%;
            height: 260px;
            background-color: #f8fafc;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 8px 15px rgba(0,0,0,0.03);
        }

        .book-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .book-item:hover img {
            transform: scale(1.05);
        }

        .book-item h3 {
            font-size: 18px;
            color: var(--primary-blue);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 2px;
        }

        .book-item .author {
            color: #94a3b8;
            font-size: 15px;
            margin-bottom: 14px;
            padding: 0 2px;
        }

        .book-footer {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2px;
        }

        .price {
            font-size: 18px;
            color: var(--primary-blue);
            font-weight: bold;
        }

        .action-btn {
            background-color: var(--primary-blue);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .action-btn i {
            font-size: 14px;
        }

        .action-btn:hover {
            background-color: var(--accent-orange);
            color: var(--primary-blue);
            transform: rotate(-10deg);
            box-shadow: 0 6px 15px rgba(242, 148, 0, 0.3);
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
                <h2><?php echo htmlspecialchars($fullname); ?></h2>
            </div>

            <nav class="menu">
                <a href="../Customer/cust_home.php" class="menu-item active"><i class="fas fa-th-large"></i> HOME</a>
                <a href="../Customer/cust_cart.php" class="menu-item"><i class="fas fa-shopping-bag"></i> CART</a>
                <a href="../Customer/cust_orders.php" class="menu-item"><i class="fas fa-receipt"></i> ORDERS</a>
                <a href="../Customer/cust_settings.php" class="menu-item"><i class="fas fa-sliders-h"></i> SETTINGS</a>
            </nav>

            <button class="logout-btn" onclick="location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
        </aside>

        <main class="content">
            <header>
                <h1>Welcome Back!</h1>
                <p>Discover our carefully curated selection of books across all genres</p>
                
                <form action="../Customer/cust_home.php" method="GET" class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" placeholder="Search by title, author or genre..." class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </header>

            <section class="book-grid">
                <h2 class="section-title">
                    <?php echo ($search != '') ? "Search Results for '" . htmlspecialchars($search) . "'" : "Latest & Featured Books"; ?>
                </h2>
                
                <div class="book-list">
                    <?php
                    // Jika data buku dijumpai dalam pangkalan data
                    if (mysqli_num_rows($result) > 0) {
                        while ($book = mysqli_fetch_assoc($result)) {
                            ?>
                            <div class="book-item">
                                <div class="book-image-wrapper">
                                    <img src="../<?php echo !empty($book['book_img']) ? $book['book_img'] : 'img/default-book.jpg'; ?>" alt="Cover">
                                </div>
                                <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="book-footer">
                                    <span class="price">RM <?php echo number_format($book['price'], 2); ?></span>
                                    
                                    <form action="../Customer/add_to_cart.php" method="POST" style="margin: 0; padding: 0;">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" class="action-btn" title="Add to Cart">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        // Mesej paparan jika tiada buku padan dengan carian atau pangkalan data kosong
                        echo "<p style='color: #0A2647; font-size: 20px;'>No books found. Try looking for something else!</p>";
                    }
                    ?>
                </div>
            </section>
        </main>
    </div>

</body>
</html>