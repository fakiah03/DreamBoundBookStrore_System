<?php
session_start();
require_once '../db.php';

// 1. SECURITY: customers only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Customer';

// 2. Auto-add format price columns if they don't exist yet (safe migration)
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS price_paperback DECIMAL(10,2) DEFAULT NULL");
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS price_hardcover DECIMAL(10,2) DEFAULT NULL");
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS price_ebook     DECIMAL(10,2) DEFAULT NULL");
// Auto-add format column to cart if not exists
$conn->query("ALTER TABLE cart ADD COLUMN IF NOT EXISTS format VARCHAR(20) DEFAULT 'paperback'");

// 3. PROCESS ADD TO CART / BUY NOW
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_to_cart']) || isset($_POST['buy_now']))) {
    $book_id  = intval($_POST['book_id']);
    $quantity = max(1, intval($_POST['quantity']));
    $format   = in_array($_POST['format'] ?? '', ['paperback','hardcover','ebook']) ? $_POST['format'] : 'paperback';

    // Check if same book + same format already in cart
    $check = $conn->query("SELECT id, quantity FROM cart WHERE user_id=$user_id AND book_id=$book_id AND format='$format'");
    if ($check && $check->num_rows > 0) {
        $row     = $check->fetch_assoc();
        $new_qty = $row['quantity'] + $quantity;
        $conn->query("UPDATE cart SET quantity=$new_qty WHERE id={$row['id']}");
    } else {
        $conn->query("INSERT INTO cart (user_id, book_id, quantity, format) VALUES ($user_id, $book_id, $quantity, '$format')");
    }

    if (isset($_POST['buy_now'])) {
        header("Location: cust_payment.php");
    } else {
        header("Location: cust_home.php?added=1&format=$format");
    }
    exit();
}


// 5. PROCESS REVIEW SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rev_book_id = intval($_POST['rev_book_id']);
    $rating      = max(1, min(5, intval($_POST['rating'])));
    $comment     = mysqli_real_escape_string($conn, trim($_POST['comment']));

    if ($rev_book_id > 0 && !empty($comment)) {
        // One review per user per book — update if exists, else insert
        $exists = $conn->query("SELECT id FROM reviews WHERE user_id=$user_id AND book_id=$rev_book_id LIMIT 1");
        if ($exists && $exists->num_rows > 0) {
            $rev_id = $exists->fetch_assoc()['id'];
            $conn->query("UPDATE reviews SET rating=$rating, comment='$comment', status='pending' WHERE id=$rev_id");
        } else {
            $conn->query("INSERT INTO reviews (user_id, book_id, rating, comment, status) VALUES ($user_id, $rev_book_id, $rating, '$comment', 'pending')");
        }
        $conn->query("INSERT INTO system_logs (log_message) VALUES ('Review submitted by user ID $user_id for book ID $rev_book_id')");
        header("Location: cust_home.php?reviewed=$rev_book_id");
        exit();
    }
}

// 6. FETCH ALL APPROVED REVIEWS grouped by book_id
$reviews_map = [];
$rev_q = $conn->query("
    SELECT r.book_id, r.rating, r.comment, r.created_at, u.fullname
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
");
if ($rev_q) {
    while ($r = $rev_q->fetch_assoc()) {
        $reviews_map[$r['book_id']][] = $r;
    }
}

// 7. CHECK which books the current user has already reviewed
$my_reviews = [];
$my_q = $conn->query("SELECT book_id, rating, comment FROM reviews WHERE user_id=$user_id");
if ($my_q) { while ($r = $my_q->fetch_assoc()) $my_reviews[$r['book_id']] = $r; }

// 4. FETCH BOOKS
$books_list  = [];
$books_query = $conn->query("SELECT * FROM books ORDER BY id DESC LIMIT 20");
if ($books_query && $books_query->num_rows > 0) {
    while ($row = $books_query->fetch_assoc()) {
        // Build format prices: if admin hasn't set them yet, auto-derive sensible defaults
        $base = (float)$row['price'];
        $row['price_paperback'] = $row['price_paperback'] ?? $base;
        $row['price_hardcover'] = $row['price_hardcover'] ?? round($base * 1.30, 2); // +30%
        $row['price_ebook']     = $row['price_ebook']     ?? round($base * 0.60, 2); // -40%
        $books_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue:#0A2647; --accent-orange:#F29400; --bg-gradient:linear-gradient(135deg,#0A2647 0%,#144272 100%); }
        *{ margin:0; padding:0; box-sizing:border-box; font-family:'Englebert',sans-serif; }
        body{ background:var(--bg-gradient); height:100vh; display:flex; overflow:hidden; padding:15px; }
        .dashboard{ display:flex; width:100%; height:100%; background:rgba(255,255,255,0.03); backdrop-filter:blur(10px); border-radius:24px; border:1px solid rgba(255,255,255,0.1); overflow:hidden; }

        /* ── Sidebar ── */
        .sidebar{ width:280px; background:rgba(10,38,71,0.7); display:flex; flex-direction:column; align-items:center; padding:40px 24px; color:white; border-right:1px solid rgba(255,255,255,0.05); }
        .profile{ text-align:center; width:100%; margin-bottom:40px; }
        .profile-circle{ width:85px; height:85px; background:linear-gradient(135deg,rgba(255,255,255,0.1),rgba(255,255,255,0.05)); border-radius:50%; border:2px solid var(--accent-orange); margin:0 auto 15px; display:flex; align-items:center; justify-content:center; }
        .profile-circle i{ font-size:32px; color:#fff; }
        .profile h2{ font-size:18px; font-weight:normal; color:#fff; text-transform:uppercase; letter-spacing:1.5px; }
        .menu{ width:100%; }
        .menu ul{ list-style:none; padding:0; margin:0; width:100%; }
        .menu li{ width:100%; }
        .menu-item{ display:flex; align-items:center; color:rgba(255,255,255,0.6); text-decoration:none; padding:18px 24px; margin-bottom:12px; border-radius:20px; font-size:22px; letter-spacing:0.5px; transition:all 0.4s; }
        .menu-item i{ margin-right:20px; font-size:22px; }
        .menu-item:hover{ color:#fff; background:rgba(255,255,255,0.05); }
        .menu-item.active{ background:#FC9D01; color:var(--primary-blue); font-weight:bold; }
        .logout-btn{ margin-top:auto; background:rgba(255,255,255,0.02); color:#ff6b6b; border:1px solid rgba(255,77,77,0.25); padding:16px 20px; cursor:pointer; border-radius:20px; width:100%; display:flex; align-items:center; justify-content:center; gap:12px; font-size:18px; transition:all 0.3s; }
        .logout-btn:hover{ background:#ff4d4d; color:white; }

        /* ── Content ── */
        .content{ flex:1; padding:50px; overflow-y:auto; background:#FC9D01; border-top-left-radius:24px; border-bottom-left-radius:24px; }
        .content::-webkit-scrollbar{ width:6px; }
        .content::-webkit-scrollbar-thumb{ background:rgba(0,0,0,0.1); border-radius:10px; }
        header h1{ font-size:46px; color:var(--primary-blue); margin-bottom:5px; }
        header p{ font-size:18px; color:#fff; margin-bottom:35px; }

        .search-container{ position:relative; width:100%; max-width:500px; margin-bottom:45px; display:flex; align-items:center; }
        .search-input{ width:100%; padding:14px 20px 14px 55px; border-radius:16px; border:2px solid transparent; outline:none; background:#fff; font-size:16px; color:var(--primary-blue); transition:all 0.3s; }
        .search-input:focus{ border-color:var(--primary-blue); }
        .search-icon{ position:absolute; left:22px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:16px; pointer-events:none; }

        /* ── Book Cards ── */
        .book-grid{ margin-top:20px; }
        .section-title{ font-size:32px; color:var(--primary-blue); margin-bottom:25px; }
        .book-list{ display:flex; flex-wrap:wrap; justify-content:flex-start; gap:30px; }
        .book-item{ background:#fff; padding:14px; border-radius:20px; width:220px; box-shadow:0 4px 20px rgba(0,0,0,0.02); transition:all 0.4s; display:flex; flex-direction:column; border:1px solid #f1f5f9; cursor:pointer; }
        .book-item:hover{ transform:translateY(-8px); box-shadow:0 20px 35px rgba(10,38,71,0.08); }
        .book-image-wrapper{ width:100%; height:260px; background:#f8fafc; border-radius:14px; overflow:hidden; margin-bottom:16px; }
        .book-item img{ width:100%; height:100%; object-fit:cover; }
        .book-item h3{ font-size:18px; color:var(--primary-blue); margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .book-item .author{ color:#94a3b8; font-size:15px; margin-bottom:14px; }
        .book-footer{ margin-top:auto; display:flex; align-items:center; justify-content:space-between; }
        .price{ font-size:18px; color:var(--primary-blue); font-weight:bold; }
        .action-btn{ background:var(--primary-blue); color:white; border:none; width:40px; height:40px; border-radius:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
        .action-btn:hover{ background:var(--accent-orange); transform:scale(1.05); }

        /* ── Modal ── */
        .product-modal{ position:absolute; top:0; right:0; bottom:0; left:0; background:rgba(10,38,71,0.5); backdrop-filter:blur(6px); display:none; justify-content:center; align-items:center; z-index:100; padding:20px; }
        .modal-card{ background:#fff; width:100%; max-width:720px; border-radius:24px; display:flex; overflow:hidden; box-shadow:0 20px 40px -10px rgba(0,0,0,0.3); position:relative; animation:slideUp 0.3s ease; }
        @keyframes slideUp{ from{transform:translateY(20px);opacity:0;} to{transform:translateY(0);opacity:1;} }
        .close-modal{ position:absolute; top:16px; right:16px; background:#f1f5f9; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--primary-blue); font-size:14px; transition:all 0.2s; z-index:10; }
        .close-modal:hover{ background:#e2e8f0; }

        .modal-gallery{ flex:0.9; background:#f8fafc; padding:25px; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .main-preview{ width:100%; max-width:200px; height:280px; border-radius:12px; box-shadow:0 10px 20px rgba(0,0,0,0.1); overflow:hidden; margin-bottom:15px; }
        .main-preview img{ width:100%; height:100%; object-fit:cover; }
        .thumb-row{ display:flex; gap:8px; }
        .thumb{ width:45px; height:60px; border-radius:6px; border:2px solid transparent; overflow:hidden; cursor:pointer; opacity:0.6; }
        .thumb.active,.thumb:hover{ border-color:var(--accent-orange); opacity:1; }
        .thumb img{ width:100%; height:100%; object-fit:cover; }

        .modal-details{ flex:1.1; padding:30px 35px 30px 25px; display:flex; flex-direction:column; justify-content:center; overflow-y:auto; }
        .brand-tag{ color:var(--accent-orange); font-size:13px; font-weight:bold; text-transform:uppercase; margin-bottom:4px; letter-spacing:0.8px; }
        .modal-details h2{ font-size:26px; color:var(--primary-blue); line-height:1.2; margin-bottom:4px; }
        .modal-author{ font-size:16px; color:#64748b; margin-bottom:8px; }
        .rating-row{ display:flex; align-items:center; gap:4px; color:#ffbc0b; font-size:12px; margin-bottom:12px; }
        .rating-row span{ color:#94a3b8; margin-left:4px; }
        .description-title{ font-size:14px; color:var(--primary-blue); font-weight:bold; margin-bottom:4px; }
        .description-text{ color:#475569; font-size:13px; line-height:1.4; margin-bottom:14px; }

        /* ── Format selector ── */
        .section-label{ font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase; margin-bottom:8px; }
        .format-options{ display:flex; gap:8px; margin-bottom:6px; flex-wrap:wrap; }
        .format-btn{
            padding:8px 16px; border:2px solid #cbd5e1; background:white; border-radius:10px;
            cursor:pointer; font-size:14px; color:var(--primary-blue); transition:all 0.2s;
            display:flex; flex-direction:column; align-items:center; gap:2px; min-width:88px;
            font-family:'Englebert',sans-serif;
        }
        .format-btn .fmt-label{ font-size:14px; font-weight:bold; }
        .format-btn .fmt-price{ font-size:12px; color:#64748b; transition:color 0.2s; }
        .format-btn.active{ background:var(--primary-blue); color:white; border-color:var(--primary-blue); }
        .format-btn.active .fmt-price{ color:rgba(255,255,255,0.8); }
        .format-btn:hover:not(.active){ background:#f1f5f9; }

        /* ── Price display ── */
        .modal-price{ font-size:28px; color:var(--primary-blue); font-weight:bold; margin:10px 0 16px; transition:all 0.2s; }

        /* ── Format badges ── */
        .format-badge{ display:inline-block; font-size:11px; padding:2px 8px; border-radius:20px; margin-bottom:10px; font-weight:bold; }
        .badge-paperback{ background:#e0f2fe; color:#0369a1; }
        .badge-hardcover{ background:#fef3c7; color:#92400e; }
        .badge-ebook    { background:#dcfce7; color:#166534; }

        /* ── Action row ── */
        .action-row{ display:flex; gap:10px; align-items:center; width:100%; margin-top:4px; }
        .qty-selector{ display:flex; align-items:center; border:1px solid #cbd5e1; border-radius:10px; padding:4px; }
        .qty-btn{ background:none; border:none; width:26px; height:26px; cursor:pointer; font-size:16px; color:var(--primary-blue); }
        .qty-val{ width:24px; text-align:center; font-size:15px; }
        .btn-buy-now{ flex:1; background:var(--primary-blue); color:white; border:none; padding:12px; border-radius:10px; font-size:15px; cursor:pointer; transition:all 0.2s; font-family:'Englebert',sans-serif; }
        .btn-buy-now:hover{ background:#144272; }
        .btn-add-cart{ flex:1; background:transparent; color:var(--primary-blue); border:2px solid var(--primary-blue); padding:10px; border-radius:10px; font-size:15px; cursor:pointer; transition:all 0.2s; font-family:'Englebert',sans-serif; }
        .btn-add-cart:hover{ background:#f1f5f9; }
        .btn-wishlist{ background:#f1f5f9; border:none; width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; color:#64748b; cursor:pointer; }
        .btn-wishlist:hover{ color:#ef4444; }


        /* ── Reviews ── */
        .modal-tabs{ display:flex; border-bottom:2px solid #f1f5f9; margin-bottom:16px; }
        .tab-btn{ background:none; border:none; padding:10px 18px; font-size:15px; color:#94a3b8; cursor:pointer; font-family:'Englebert',sans-serif; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all 0.2s; }
        .tab-btn.active{ color:var(--primary-blue); border-bottom-color:var(--primary-blue); }
        .tab-panel{ display:none; } .tab-panel.active{ display:block; }
        .review-list{ max-height:180px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; margin-bottom:12px; padding-right:4px; }
        .review-list::-webkit-scrollbar{ width:4px; } .review-list::-webkit-scrollbar-thumb{ background:#cbd5e1; border-radius:4px; }
        .review-card{ background:#f8fafc; border-radius:12px; padding:10px 14px; border:1px solid #e2e8f0; }
        .review-top{ display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
        .reviewer-name{ font-size:14px; font-weight:bold; color:var(--primary-blue); }
        .review-stars{ color:#ffbc0b; font-size:13px; letter-spacing:1px; }
        .review-date{ font-size:11px; color:#94a3b8; }
        .review-text{ font-size:13px; color:#475569; line-height:1.4; }
        .no-reviews{ text-align:center; padding:20px; color:#94a3b8; font-size:14px; }
        .write-review-form{ display:flex; flex-direction:column; gap:10px; }
        .star-picker{ display:flex; gap:6px; flex-direction:row-reverse; justify-content:flex-end; }
        .star-picker input{ display:none; }
        .star-picker label{ font-size:26px; color:#e2e8f0; cursor:pointer; transition:color 0.15s; }
        .star-picker input:checked ~ label,
        .star-picker label:hover,
        .star-picker label:hover ~ label{ color:#ffbc0b; }
        .review-textarea{ width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:'Englebert',sans-serif; resize:none; outline:none; }
        .review-textarea:focus{ border-color:var(--primary-blue); }
        .submit-review-btn{ background:var(--primary-blue); color:white; border:none; padding:10px; border-radius:10px; font-size:15px; cursor:pointer; font-family:'Englebert',sans-serif; transition:background 0.2s; }
        .submit-review-btn:hover{ background:#144272; }
        .pending-notice{ background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:8px 12px; border-radius:8px; font-size:13px; margin-bottom:8px; }

        /* ── Toast notification ── */
        .toast{
            position:fixed; bottom:30px; right:30px; background:var(--primary-blue); color:white;
            padding:14px 22px; border-radius:14px; font-size:16px; z-index:9999;
            display:flex; align-items:center; gap:10px; box-shadow:0 8px 24px rgba(0,0,0,0.2);
            transform:translateY(80px); opacity:0; transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1);
        }
        .toast.show{ transform:translateY(0); opacity:1; }
        .toast i{ color:#FC9D01; font-size:18px; }
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
                <li><a href="cust_home.php"     class="menu-item active"><i class="fas fa-th-large"></i> HOME</a></li>
                <li><a href="cust_cart.php"     class="menu-item"><i class="fas fa-shopping-bag"></i> CART</a></li>
                <li><a href="cust_orders.php"   class="menu-item"><i class="fas fa-receipt"></i> ORDERS</a></li>
                <li><a href="cust_settings.php" class="menu-item"><i class="fas fa-sliders-h"></i> SETTINGS</a></li>
            </ul>
        </nav>
        <button class="logout-btn" onclick="location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
    </aside>

    <main class="content">
        <header>
            <h1>Welcome Back!</h1>
            <p>Discover our carefully curated selection of books across all genres</p>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by title, author or genre..." class="search-input" oninput="filterBooks()">
                <i class="fas fa-search search-icon"></i>
            </div>
        </header>

        <section class="book-grid">
            <h2 class="section-title">Latest &amp; Featured Books</h2>
            <div class="book-list" id="bookList">
                <?php if (count($books_list) > 0): ?>
                    <?php foreach ($books_list as $book):
                        $book_id   = $book['id'];
                        $title     = addslashes(htmlspecialchars($book['title']));
                        $author    = addslashes(htmlspecialchars($book['author']));
                        $genre     = htmlspecialchars($book['genre']);
                        $p_paper   = number_format((float)$book['price_paperback'], 2);
                        $p_hard    = number_format((float)$book['price_hardcover'], 2);
                        $p_ebook   = number_format((float)$book['price_ebook'],     2);
                        $img_path  = !empty($book['book_img']) ? '../' . htmlspecialchars($book['book_img']) : '../img/book1.jpg';
                        $desc      = addslashes("Dive into the wonderful world of {$book['title']} by {$book['author']}. A masterpiece that will capture your imagination.");
                    ?>
                    <div class="book-item"
                         data-title="<?php echo strtolower($book['title']); ?>"
                         data-author="<?php echo strtolower($book['author']); ?>"
                         data-genre="<?php echo strtolower($book['genre']); ?>"
                         onclick="openModal(<?php echo $book_id; ?>, '<?php echo $title; ?>', '<?php echo $author; ?>', '<?php echo $p_paper; ?>', '<?php echo $p_hard; ?>', '<?php echo $p_ebook; ?>', '<?php echo $img_path; ?>', '<?php echo $desc; ?>')">
                        <div class="book-image-wrapper">
                            <img src="<?php echo $img_path; ?>" alt="<?php echo $title; ?>" onerror="this.src='../img/book1.jpg'">
                        </div>
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                        <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
                        <div class="book-footer">
                            <span class="price">RM <?php echo $p_paper; ?></span>
                            <button class="action-btn" title="View Details"
                                onclick="event.stopPropagation(); openModal(<?php echo $book_id; ?>, '<?php echo $title; ?>', '<?php echo $author; ?>', '<?php echo $p_paper; ?>', '<?php echo $p_hard; ?>', '<?php echo $p_ebook; ?>', '<?php echo $img_path; ?>', '<?php echo $desc; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:white;font-size:18px;">No books have been added yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- ── MODAL ── -->
    <div class="product-modal" id="productModal" onclick="closeIfOutside(event)">
        <div class="modal-card">
            <button class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></button>

            <div class="modal-gallery">
                <div class="main-preview"><img id="modalImg" src="../img/book1.jpg" alt="Preview"></div>
                <div class="thumb-row">
                    <div class="thumb active" onclick="setThumb(this)"><img id="thumb1" src="../img/book1.jpg" alt="T1"></div>
                    <div class="thumb"        onclick="setThumb(this)"><img id="thumb2" src="../img/book1.jpg" alt="T2"></div>
                    <div class="thumb"        onclick="setThumb(this)"><img id="thumb3" src="../img/book1.jpg" alt="T3"></div>
                </div>
            </div>

            <div class="modal-details">
                <div class="brand-tag">Dreambound Selection</div>
                <h2 id="modalTitle">Book Title</h2>
                <div class="modal-author" id="modalAuthor">Author</div>

                <div class="rating-row">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    <i class="fas fa-star"></i><i class="fas fa-star"></i>
                    <span>(15k+ Reviews)</span>
                </div>


                <!-- Tabs: Details | Reviews -->
                <div class="modal-tabs">
                    <button class="tab-btn active" onclick="switchTab('details', this)">Details</button>
                    <button class="tab-btn"        onclick="switchTab('reviews', this)">Reviews <span id="reviewCountBadge" style="font-size:12px;background:#f1f5f9;padding:1px 6px;border-radius:10px;margin-left:4px;">0</span></button>
                </div>

                <!-- Tab: Details -->
                <div class="tab-panel active" id="tab-details">
                <div class="description-title">Product Description</div>
                <p class="description-text" id="modalDesc">Description here.</p>

                <!-- Format selector -->
                <div class="section-label">Choose Format</div>
                <div class="format-options">
                    <button type="button" class="format-btn active" id="btn-paperback" onclick="selectFormat('paperback')">
                        <span class="fmt-label">📖 Paperback</span>
                        <span class="fmt-price" id="price-paperback">RM 0.00</span>
                    </button>
                    <button type="button" class="format-btn" id="btn-hardcover" onclick="selectFormat('hardcover')">
                        <span class="fmt-label">📚 Hardcover</span>
                        <span class="fmt-price" id="price-hardcover">RM 0.00</span>
                    </button>
                    <button type="button" class="format-btn" id="btn-ebook" onclick="selectFormat('ebook')">
                        <span class="fmt-label">💻 E-Book</span>
                        <span class="fmt-price" id="price-ebook">RM 0.00</span>
                    </button>
                </div>

                <div class="modal-price" id="modalPrice">RM 0.00</div>


                </div><!-- /tab-details -->

                <!-- Tab: Reviews -->
                <div class="tab-panel" id="tab-reviews">
                    <div class="review-list" id="reviewList">
                        <div class="no-reviews" id="noReviewsMsg"><i class="far fa-comment-dots" style="font-size:28px;display:block;margin-bottom:8px;"></i>No reviews yet. Be the first!</div>
                    </div>

                    <!-- Write / Edit Review -->
                    <div id="pendingNotice" class="pending-notice" style="display:none;">
                        <i class="fas fa-clock"></i> Your review is pending admin approval.
                    </div>
                    <form method="POST" action="cust_home.php" class="write-review-form" id="reviewForm">
                        <input type="hidden" name="submit_review" value="1">
                        <input type="hidden" name="rev_book_id" id="revBookId" value="">
                        <div class="section-label">Your Rating</div>
                        <div class="star-picker">
                            <input type="radio" name="rating" id="s5" value="5"><label for="s5">★</label>
                            <input type="radio" name="rating" id="s4" value="4"><label for="s4">★</label>
                            <input type="radio" name="rating" id="s3" value="3" checked><label for="s3">★</label>
                            <input type="radio" name="rating" id="s2" value="2"><label for="s2">★</label>
                            <input type="radio" name="rating" id="s1" value="1"><label for="s1">★</label>
                        </div>
                        <textarea name="comment" id="reviewComment" class="review-textarea" rows="3" placeholder="Share your thoughts about this book..." required></textarea>
                        <button type="submit" class="submit-review-btn"><i class="fas fa-paper-plane"></i> Submit Review</button>
                    </form>
                </div><!-- /tab-reviews -->

                <form method="POST" action="cust_home.php" style="width:100%;">
                    <input type="hidden" name="book_id"  id="modalBookId" value="">
                    <input type="hidden" name="quantity" id="formQtyVal"  value="1">
                    <input type="hidden" name="format"   id="formFormat"  value="paperback">

                    <div class="action-row">
                        <div class="qty-selector">
                            <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                            <div class="qty-val" id="qtyVal">1</div>
                            <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                        </div>
                        <button type="submit" name="buy_now"      class="btn-buy-now">Buy Now</button>
                        <button type="submit" name="add_to_cart"  class="btn-add-cart">Add To Cart</button>
                        <button type="button" class="btn-wishlist" id="wishBtn" onclick="toggleWish(this)"><i class="far fa-heart"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"><i class="fas fa-check-circle"></i> <span id="toastMsg">Added to cart!</span></div>

<script>

    // Reviews data from PHP
    const reviewsMap = <?php echo json_encode($reviews_map); ?>;
    const myReviews  = <?php echo json_encode($my_reviews);  ?>;

    // Per-book price data injected from PHP
    const bookPrices = {
        <?php foreach ($books_list as $b): ?>
        <?php echo $b['id']; ?>: {
            paperback: <?php echo number_format((float)$b['price_paperback'],2,'.',''); ?>,
            hardcover: <?php echo number_format((float)$b['price_hardcover'],2,'.',''); ?>,
            ebook:     <?php echo number_format((float)$b['price_ebook'],    2,'.',''); ?>
        },
        <?php endforeach; ?>
    };

    let currentQty    = 1;
    let currentFormat = 'paperback';
    let currentBookId = null;


    function switchTab(tab, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    }

    function loadReviews(bookId) {
        const list    = document.getElementById('reviewList');
        const noMsg   = document.getElementById('noReviewsMsg');
        const pending = document.getElementById('pendingNotice');
        const badge   = document.getElementById('reviewCountBadge');

        list.innerHTML = '';
        const revs = reviewsMap[bookId] || [];
        badge.textContent = revs.length;

        if (revs.length === 0) {
            list.appendChild(noMsg);
            noMsg.style.display = 'block';
        } else {
            noMsg.style.display = 'none';
            revs.forEach(r => {
                const stars = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating);
                const date  = r.created_at ? r.created_at.substring(0,10) : '';
                list.innerHTML += `
                <div class="review-card">
                    <div class="review-top">
                        <span class="reviewer-name">${r.fullname}</span>
                        <span class="review-stars">${stars}</span>
                    </div>
                    <div class="review-date">${date}</div>
                    <div class="review-text">${r.comment}</div>
                </div>`;
            });
        }

        // Pre-fill own review if exists
        const myRev = myReviews[bookId];
        if (myRev) {
            pending.style.display = 'block';
            document.getElementById('reviewComment').value = myRev.comment;
            const starInput = document.getElementById('s' + myRev.rating);
            if (starInput) starInput.checked = true;
        } else {
            pending.style.display = 'none';
            document.getElementById('reviewComment').value = '';
        }

        document.getElementById('revBookId').value = bookId;
    }

    // ── Open Modal ──────────────────────────────────────────────────────────
    function openModal(id, title, author, pPaper, pHard, pEbook, img, desc) {
        currentBookId = id;
        document.getElementById('modalTitle').innerText  = title;
        document.getElementById('modalAuthor').innerText = 'By ' + author;
        document.getElementById('modalDesc').innerText   = desc;
        document.getElementById('modalBookId').value     = id;

        // Set prices on buttons
        document.getElementById('price-paperback').innerText = 'RM ' + parseFloat(pPaper).toFixed(2);
        document.getElementById('price-hardcover').innerText = 'RM ' + parseFloat(pHard).toFixed(2);
        document.getElementById('price-ebook').innerText     = 'RM ' + parseFloat(pEbook).toFixed(2);

        // Reset to paperback
        selectFormat('paperback');

        // Images
        ['modalImg','thumb1','thumb2','thumb3'].forEach(id => document.getElementById(id).src = img);

        // Reset qty
        currentQty = 1;
        document.getElementById('qtyVal').innerText   = 1;
        document.getElementById('formQtyVal').value   = 1;

        // Reset wishlist heart
        const wb = document.getElementById('wishBtn');
        wb.innerHTML = '<i class="far fa-heart"></i>';
        wb.style.color = '#64748b';

        // Reset to Details tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('tab-details').classList.add('active');
        loadReviews(id);

        document.getElementById('productModal').style.display = 'flex';
    }

    function closeModal() { document.getElementById('productModal').style.display = 'none'; }
    function closeIfOutside(e) { if (e.target === document.getElementById('productModal')) closeModal(); }

    // ── Format selection ────────────────────────────────────────────────────
    function selectFormat(fmt) {
        currentFormat = fmt;
        document.getElementById('formFormat').value = fmt;

        // Update button styles
        ['paperback','hardcover','ebook'].forEach(f => {
            document.getElementById('btn-' + f).classList.toggle('active', f === fmt);
        });

        // Update big price display
        if (currentBookId && bookPrices[currentBookId]) {
            const price = bookPrices[currentBookId][fmt];
            document.getElementById('modalPrice').innerText = 'RM ' + price.toFixed(2);
        }
    }

    // ── Qty ─────────────────────────────────────────────────────────────────
    function changeQty(delta) {
        currentQty = Math.max(1, currentQty + delta);
        document.getElementById('qtyVal').innerText   = currentQty;
        document.getElementById('formQtyVal').value   = currentQty;
    }

    // ── Wishlist heart toggle ────────────────────────────────────────────────
    function toggleWish(btn) {
        const liked = btn.querySelector('i').classList.contains('fas');
        btn.innerHTML = liked ? '<i class="far fa-heart"></i>' : '<i class="fas fa-heart"></i>';
        btn.style.color = liked ? '#64748b' : '#ef4444';
    }

    // ── Thumbnail click ─────────────────────────────────────────────────────
    function setThumb(el) {
        document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('modalImg').src = el.querySelector('img').src;
    }

    // ── Search / filter ─────────────────────────────────────────────────────
    function filterBooks() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        document.querySelectorAll('.book-item').forEach(card => {
            const match = card.dataset.title.includes(q) || card.dataset.author.includes(q) || card.dataset.genre.includes(q);
            card.style.display = match ? '' : 'none';
        });
    }

    // ── Toast on add-to-cart redirect ───────────────────────────────────────
    <?php if (isset($_GET['reviewed'])): ?>
    window.addEventListener('load', function() {
        showToast('Your review has been submitted for approval!');
        window.history.replaceState(null, null, window.location.pathname);
    });
    <?php endif; ?>

    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    window.addEventListener('load', function() {
        const fmt   = '<?php echo htmlspecialchars($_GET['format'] ?? 'paperback'); ?>';
        const label = fmt === 'ebook' ? 'E-Book' : (fmt === 'hardcover' ? 'Hardcover' : 'Paperback');
        showToast('Book (' + label + ') added to your cart!');
        window.history.replaceState(null, null, window.location.pathname);
    });
    <?php endif; ?>

    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toastMsg').innerText = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }
</script>
</body>
</html>
