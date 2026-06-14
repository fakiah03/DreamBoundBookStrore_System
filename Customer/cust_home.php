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

// 2. PROCESS "ADD TO CART" OR "BUY NOW" ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_to_cart']) || isset($_POST['buy_now']))) {
    $book_id = intval($_POST['book_id']);
    $quantity = intval($_POST['quantity']);

    // check if the book is already in the cart for this user
    $check_cart = $conn->query("SELECT id, quantity FROM cart WHERE user_id = $user_id AND book_id = $book_id");
    
    if ($check_cart && $check_cart->num_rows > 0) {
        // if already exists, update the quantity
        $cart_row = $check_cart->fetch_assoc();
        $new_qty = $cart_row['quantity'] + $quantity;
        $conn->query("UPDATE cart SET quantity = $new_qty WHERE id = " . $cart_row['id']);
    } else {
        // if not in cart, insert new entry
        $conn->query("INSERT INTO cart (user_id, book_id, quantity) VALUES ($user_id, $book_id, $quantity)");
    }

    // If "Buy Now" is clicked, redirect to cart. If "Add to Cart" is clicked, stay on this page.
    if (isset($_POST['buy_now'])) {
        header("Location: cust_cart.php");
    } else {
        header("Location: cust_home.php?added=1");
    }
    exit();
}

// 3. TAKE BOOK LIST FROM DATABASE
$books_list = [];
$books_query = $conn->query("SELECT * FROM books ORDER BY id DESC LIMIT 20");
if ($books_query && $books_query->num_rows > 0) {
    while ($row = $books_query->fetch_assoc()) {
        $books_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">

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
            list-style: none; 
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

        .search-container { 
            position: relative; width: 100%; max-width: 500px;
            margin-bottom: 45px; display: flex; align-items: center;
        }

        .search-input { 
            width: 100%; padding: 14px 20px 14px 55px; border-radius: 16px; 
            border: 2px solid transparent; outline: none; background-color: #ffffff; 
            font-size: 16px; color: var(--primary-blue); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-input:focus {
            border-color: var(--primary-blue); box-shadow: 0 12px 24px rgba(10, 38, 71, 0.15); transform: translateY(-1px);
        }

        .search-input::placeholder { color: #94a3b8; opacity: 0.9; }

        .search-icon { 
            position: absolute; left: 22px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 16px; pointer-events: none; transition: color 0.3s ease;
        }

        .search-input:focus + .search-icon { color: var(--primary-blue); }

        .book-grid { margin-top: 20px; }
        .section-title { font-size: 32px; color: var(--primary-blue); margin-bottom: 25px; position: relative; }
        .book-list { display: flex; flex-wrap: wrap; justify-content: flex-start; gap: 30px; }

        .book-item {
            background-color: white; padding: 14px; border-radius: 20px; width: 220px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02); transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            display: flex; flex-direction: column; border: 1px solid #f1f5f9; cursor: pointer;
        }

        .book-item:hover { transform: translateY(-8px); box-shadow: 0 20px 35px rgba(10, 38, 71, 0.08); }

        .book-image-wrapper {
            width: 100%; height: 260px; background-color: #f8fafc; border-radius: 14px;
            overflow: hidden; margin-bottom: 16px;
        }

        .book-item img { width: 100%; height: 100%; object-fit: cover; }

        .book-item h3 {
            font-size: 18px; color: var(--primary-blue); margin-bottom: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .book-item .author { color: #94a3b8; font-size: 15px; margin-bottom: 14px; }
        .book-footer { margin-top: auto; display: flex; align-items: center; justify-content: space-between; }
        .price { font-size: 18px; color: var(--primary-blue); font-weight: bold; }

        .action-btn {
            background-color: var(--primary-blue); color: white; border: none; width: 40px; height: 40px;
            border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .action-btn:hover { background-color: var(--accent-orange); transform: scale(1.05); }

        /* --- POP-UP MODAL OVERLAY --- */
        .product-modal {
            position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            background: rgba(10, 38, 71, 0.5); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
            display: none; justify-content: center; align-items: center; z-index: 100; padding: 20px;
        }

        .modal-card {
            background: #ffffff; width: 100%; max-width: 720px; border-radius: 24px;
            display: flex; overflow: hidden; box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3);
            position: relative; animation: slideUp 0.3s ease;
        }

        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .close-modal {
            position: absolute; top: 16px; right: 16px; background: #f1f5f9; border: none;
            width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; 
            align-items: center; justify-content: center; color: var(--primary-blue); font-size: 14px;
            transition: all 0.2s; z-index: 10;
        }
        .close-modal:hover { background: #e2e8f0; }

        .modal-gallery {
            flex: 0.9; background: #f8fafc; padding: 25px; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }
        .main-preview {
            width: 100%; max-width: 200px; height: 280px; border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 15px;
        }
        .main-preview img { width: 100%; height: 100%; object-fit: cover; }
        .thumb-row { display: flex; gap: 8px; }
        .thumb {
            width: 45px; height: 60px; border-radius: 6px; border: 2px solid transparent;
            overflow: hidden; cursor: pointer; opacity: 0.6;
        }
        .thumb.active, .thumb:hover { border-color: var(--accent-orange); opacity: 1; }
        .thumb img { width: 100%; height: 100%; object-fit: cover; }

        .modal-details {
            flex: 1.1; padding: 30px 35px 30px 25px; display: flex; flex-direction: column; justify-content: center;
        }
        .brand-tag {
            color: var(--accent-orange); font-size: 13px; font-weight: bold; text-transform: uppercase; 
            margin-bottom: 4px; letter-spacing: 0.8px;
        }
        .modal-details h2 { font-size: 26px; color: var(--primary-blue); line-height: 1.2; margin-bottom: 4px; }
        .modal-author { font-size: 16px; color: #64748b; margin-bottom: 8px; }
        .rating-row { display: flex; align-items: center; gap: 4px; color: #ffbc0b; font-size: 12px; margin-bottom: 15px; }
        .rating-row span { color: #94a3b8; margin-left: 4px; }
        .description-title { font-size: 14px; color: var(--primary-blue); font-weight: bold; margin-bottom: 4px; }
        .description-text { color: #475569; font-size: 13px; line-height: 1.4; margin-bottom: 18px; }
        .modal-price { font-size: 28px; color: var(--primary-blue); font-weight: bold; margin-bottom: 18px; }

        .section-label { font-size: 12px; color: #64748b; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; }
        .format-options { display: flex; gap: 8px; margin-bottom: 20px; }
        .format-btn {
            padding: 6px 14px; border: 1px solid #cbd5e1; background: white; border-radius: 8px;
            cursor: pointer; font-size: 14px; color: var(--primary-blue); transition: all 0.2s;
        }
        .format-btn.active, .format-btn:hover { background: var(--primary-blue); color: white; border-color: var(--primary-blue); }

        .action-row { display: flex; gap: 10px; align-items: center; width: 100%; }
        .qty-selector {
            display: flex; align-items: center; border: 1px solid #cbd5e1; border-radius: 10px; padding: 4px;
        }
        .qty-btn { background: none; border: none; width: 26px; height: 26px; cursor: pointer; font-size: 16px; color: var(--primary-blue); }
        .qty-val { width: 24px; text-align: center; font-size: 15px; }

        .btn-buy-now {
            flex: 1; background: var(--primary-blue); color: white; border: none; padding: 12px; border-radius: 10px;
            font-size: 15px; cursor: pointer; transition: all 0.2s; font-weight: bold; text-align: center;
        }
        .btn-buy-now:hover { background: #144272; }

        .btn-add-cart {
            flex: 1; background: transparent; color: var(--primary-blue); border: 2px solid var(--primary-blue); 
            padding: 10px; border-radius: 10px; font-size: 15px; cursor: pointer; transition: all 0.2s;
            font-weight: bold; text-align: center;
        }
        .btn-add-cart:hover { background: #f1f5f9; }

        .btn-wishlist {
            background: #f1f5f9; border: none; width: 42px; height: 42px; border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; font-size: 16px; color: #64748b; cursor: pointer;
        }
        .btn-wishlist:hover { color: #ef4444; }
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
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_home.php" class="menu-item active"><i class="fas fa-th-large"></i> HOME</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_cart.php" class="menu-item "><i class="fas fa-shopping-bag"></i> CART</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_orders.php" class="menu-item "><i class="fas fa-receipt"></i> ORDERS</a></li>
                    <li><a href="/DreamBoundBookStrore_system/Customer/cust_settings.php" class="menu-item"><i class="fas fa-sliders-h"></i> SETTINGS</a></li>
                </ul>
            </nav>

            <button class="logout-btn" onclick="location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
        </aside>

        <main class="content">
            <header>
                <h1>Welcome Back!</h1>
                <p>Discover our carefully curated selection of books across all genres</p>
                
                <div class="search-container">
                    <input type="text" placeholder="Search by title, author or genre..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </header>

            <section class="book-grid">
                <h2 class="section-title">Latest & Featured Books</h2>
                
                <div class="book-list">
                    <?php if (count($books_list) > 0): ?>
                        <?php foreach ($books_list as $book): 
                            // provide default values and sanitization for book details
                            $book_id = $book['id'];
                            $title = addslashes(htmlspecialchars($book['title']));
                            $author = addslashes(htmlspecialchars($book['author']));
                            $price_num = number_format($book['price'], 2);
                            $price_str = 'RM ' . $price_num;
                            $img_path = !empty($book['book_img']) ? '../' . htmlspecialchars($book['book_img']) : '../img/book1.jpg';
                            
                            // if description is empty, create a default one using title and author
                            $desc = addslashes("Dive into the wonderful world of " . $title . " by " . $author . ". A masterpiece that will capture your imagination.");
                        ?>
                        
                        <div class="book-item" onclick="openProductDetails(<?php echo $book_id; ?>, '<?php echo $title; ?>', '<?php echo $author; ?>', '<?php echo $price_str; ?>', '<?php echo $img_path; ?>', '<?php echo $desc; ?>')">
                            <div class="book-image-wrapper">
                                <img src="<?php echo $img_path; ?>" alt="<?php echo $title; ?>">
                            </div>
                            <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
                            <div class="book-footer">
                                <span class="price"><?php echo $price_str; ?></span>
                                <button class="action-btn" title="View Details" onclick="openProductDetailsFromBtn(event, <?php echo $book_id; ?>, '<?php echo $title; ?>', '<?php echo $author; ?>', '<?php echo $price_str; ?>', '<?php echo $img_path; ?>', '<?php echo $desc; ?>')">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: white; font-size: 18px;">There are currently no books added by the administrator..</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <div class="product-modal" id="productModal">
            <div class="modal-card">
                <button class="close-modal" onclick="closeProductDetails()"><i class="fas fa-times"></i></button>
                
                <div class="modal-gallery">
                    <div class="main-preview">
                        <img id="modalImg" src="../img/book1.jpg" alt="Preview Image">
                    </div>
                    <div class="thumb-row">
                        <div class="thumb active"><img id="thumb1" src="../img/book1.jpg" alt="Thumb 1"></div>
                        <div class="thumb"><img id="thumb2" src="../img/book1.jpg" alt="Thumb 2"></div>
                        <div class="thumb"><img id="thumb3" src="../img/book1.jpg" alt="Thumb 3"></div>
                    </div>
                </div>

                <div class="modal-details">
                    <div class="brand-tag">Dreambound Selection</div>
                    <h2 id="modalTitle">Book Title Here</h2>
                    <div class="modal-author" id="modalAuthor">Author Name</div>
                    
                    <div class="rating-row">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <span>(15k+ Reviews)</span>
                    </div>

                    <div class="description-title">Product Description</div>
                    <p class="description-text" id="modalDesc">This is a dynamic structural placeholder for your beautiful book summaries.</p>

                    <div class="modal-price" id="modalPrice">RM 00.00</div>

                    <div class="section-label">Choose Format</div>
                    <div class="format-options">
                        <button type="button" class="format-btn active">Paperback</button>
                        <button type="button" class="format-btn">Hardcover</button>
                        <button type="button" class="format-btn">E-Book</button>
                    </div>

                    <form method="POST" action="cust_home.php" style="width:100%;">
                        <input type="hidden" name="book_id" id="modalBookId" value="">
                        <input type="hidden" name="quantity" id="formQtyVal" value="1">

                        <div class="action-row">
                            <div class="qty-selector">
                                <button type="button" class="qty-btn" onclick="changeQty(-1)">-</button>
                                <div class="qty-val" id="qtyVal">1</div>
                                <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                            </div>
                            
                            <button type="submit" name="buy_now" class="btn-buy-now">Buy Now</button>
                            <button type="submit" name="add_to_cart" class="btn-add-cart">Add To Cart</button>
                            
                            <button type="button" class="btn-wishlist"><i class="far fa-heart"></i></button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    <script>
        window.onload = function() {
            alert("Buku berjaya ditambah ke dalam Troli anda!");
            // Remove the '?added=1' parameter from the URL to prevent the alert from being displayed again when the page is refreshed.
            window.history.replaceState(null, null, window.location.pathname);
        };
    </script>
    <?php endif; ?>

    <script>
        let currentQty = 1;
        
        function changeQty(amount) {
            currentQty += amount;
            if(currentQty < 1) currentQty = 1;
            
            // Update screen value
            document.getElementById('qtyVal').innerText = currentQty;
            // Update the value that will be submitted to PHP
            document.getElementById('formQtyVal').value = currentQty;
        }

        function openProductDetailsFromBtn(event, id, title, author, price, imgPath, description) {
            event.stopPropagation(); // avoid triggering the parent book-item click event
            openProductDetails(id, title, author, price, imgPath, description);
        }

        function openProductDetails(id, title, author, price, imgPath, description) {
            // set pop-up content based on the clicked book item
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalAuthor').innerText = "By " + author;
            document.getElementById('modalPrice').innerText = price;
            document.getElementById('modalDesc').innerText = description;
            
            // set book id in hidden input for form submission
            document.getElementById('modalBookId').value = id;
            
            // Set picture in main preview and thumbnails (for demo, using the same image)
            document.getElementById('modalImg').src = imgPath;
            document.getElementById('thumb1').src = imgPath;
            document.getElementById('thumb2').src = imgPath;
            document.getElementById('thumb3').src = imgPath;
            
            // Reset quantity to 1 whenever a new product is opened
            currentQty = 1;
            document.getElementById('qtyVal').innerText = currentQty;
            document.getElementById('formQtyVal').value = currentQty;
            
            // Display pop-up modal
            document.getElementById('productModal').style.display = 'flex';
        }

        function closeProductDetails() {
            document.getElementById('productModal').style.display = 'none';
        }

        // effect for format selection buttons in the product details pop-up
        const formatBtns = document.querySelectorAll('.format-btn');
        formatBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                formatBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>