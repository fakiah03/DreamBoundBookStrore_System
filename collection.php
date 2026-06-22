<?php
session_start();

require_once 'db.php'; 

// Check login status for JS use later
$is_logged_in = isset($_SESSION['user_id']) ? 'true' : 'false';

// 2. SEARCH & FILTER LOGIC (Dynamically capturing form inputs)
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$genre = isset($_GET['genre']) ? mysqli_real_escape_string($conn, $_GET['genre']) : 'all';

// 3. BUILD SQL QUERY DYNAMICALLY
//// Display in-stock books only
$query = "SELECT * FROM books WHERE stock > 0";

if (!empty($search)) {
    $query .= " AND (title LIKE '%$search%' OR author LIKE '%$search%')";
}

if ($genre !== 'all') {
    $query .= " AND genre = '$genre'";
}

$query .= " ORDER BY id DESC";
$books_result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound Bookstore - Browse Collection</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">

    <style>
      
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Englebert', cursive, sans-serif;
        }

        body {
            background-color: #FC9D01; 
            color: #0E2C46; 
            line-height: 1.5;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: #0E2C46; 
            border-bottom: 3px solid #FC9D01;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .logo-nav-wrapper {
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .logo {
            font-size: 1.6rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
            text-decoration: none;
            letter-spacing: 1px;
        }

        .logo img { width: 45px; }
        .logo span { color: #FC9D01; }

        .main-nav { display: flex; gap: 20px; }
        .main-nav a { text-decoration: none; color: #ffffff; font-size: 1.1rem; transition: color 0.2s; }
        .main-nav a:hover, .main-nav a.active { color: #FC9D01; font-weight: bold; }

        .auth-actions { display: flex; align-items: center; gap: 20px; }

        .cart-btn {
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #ffffff;
            transition: color 0.2s;
            position: relative;
            display: flex;
            align-items: center;
        }
        .cart-btn:hover { color: #FC9D01; }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: #ef4444;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 50%;
            font-weight: bold;
            border: 1px solid #0E2C46;
        }

        .sign-in { text-decoration: none; color: #ffffff; font-size: 1.1rem; transition: color 0.2s; }
        .sign-in:hover { color: #FC9D01; }

        .sign-up-btn {
            background-color: #FC9D01;
            color: #0E2C46;
            border: 2px solid #FC9D01;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 1.05rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .sign-up-btn:hover { background-color: transparent; color: #FC9D01; }

        main { max-width: 1400px; margin: 0 auto; padding: 40px; }
        .hero { margin-bottom: 30px; text-align: center; }
        .hero h1 { font-size: 3rem; margin-bottom: 10px; color: #0E2C46; text-shadow: 1px 1px 2px rgba(255,255,255,0.6); }
        .hero p { color: #ffffff; font-size: 1.3rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.2); }

     
        .search-filter-container {
            display: flex;
            align-items: center;
            background: #FDF5E6; 
            border: 2px solid #0E2C46;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 40px;
            gap: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .search-box {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 12px;
            color: #0E2C46;
            border-right: 2px solid rgba(14, 44, 70, 0.2);
            padding-right: 20px;
        }
        .search-box i { font-size: 1.2rem; }
        .search-box input { border: none; outline: none; background: transparent; width: 100%; font-size: 1.1rem; color: #0E2C46; }

        .filter-box { display: flex; align-items: center; gap: 10px; color: #0E2C46; }
        .filter-box label { font-size: 1.1rem; font-weight: bold; display: flex; align-items: center; gap: 5px; }
        .filter-box select {
            border: 2px solid #0E2C46;
            padding: 6px 35px 6px 12px;
            border-radius: 8px;
            font-size: 1rem;
            color: #0E2C46;
            appearance: none;
            background: white;
            cursor: pointer;
            outline: none;
            font-weight: bold;
        }
        
        .select-wrapper { position: relative; display: flex; align-items: center; gap: 10px; }
        .select-wrapper::after {
            content: '\f107'; 
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 12px;
            pointer-events: none;
            color: #0E2C46;
            font-size: 0.9rem;
        }

       
        .btn-search-submit {
            background-color: #0E2C46;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-search-submit:hover { background-color: #1a446c; }

        .book-list {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 25px;
            justify-content: center;
        }

        .book-item {
            background-color: #FDF5E6; 
            border: 2px solid #0E2C46;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .book-item:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        .book-item img { width: 100%; height: 210px; object-fit: cover; border-radius: 6px; border: 1px solid #0E2C46; background-color: #eee; }
        .book-details { margin-top: 10px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .book-title { font-size: 1.25rem; color: #0E2C46; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
        .book-author { color: #666; font-size: 0.95rem; margin-bottom: 5px; }
        .book-price { font-size: 1.2rem; font-weight: bold; color: #0E2C46; margin-bottom: 8px; }
        
        .btn-add-cart {
            background-color: #0E2C46;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-add-cart:hover { background-color: #1a446c; }

        @media (max-width: 1200px) { .book-list { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 950px) {
            .book-list { grid-template-columns: repeat(3, 1fr); }
            .search-filter-container { flex-direction: column; align-items: stretch; gap: 15px; }
            .search-box { border-right: none; border-bottom: 2px solid rgba(14, 44, 70, 0.1); padding-bottom: 15px; padding-right: 0;}
            .select-wrapper { width: 100%; justify-content: space-between; }
            .filter-box { width: 100%; }
        }
        @media (max-width: 650px) { .book-list { grid-template-columns: repeat(2, 1fr); } header { flex-direction: column; gap: 15px; text-align: center; } .logo-nav-wrapper { flex-direction: column; gap: 15px; } }
        @media (max-width: 450px) { .book-list { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <header>
        <div class="logo-nav-wrapper">
            <a href="index.php" class="logo">
                <img src="img/logo1.png" alt="Dreambound Logo">
                DREAMBOUND <span>BOOKSTORE</span>
            </a>
            <nav class="main-nav">
                <a href="index.php">Home</a>
                <a href="collection.php" class="active">Browse Collection</a>
            </nav>
        </div>
        <div class="auth-actions">
            <button class="cart-btn" onclick="viewCartAlert()">
                <i class="fa-solid fa-shopping-cart"></i>
                <span class="cart-badge" id="cartCount">0</span>
            </button>
            <?php if(isset($_SESSION['user_id'])): ?>
                <span style="color: white; font-weight: bold;">Hai, <?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                <a href="logout.php" class="sign-in" onclick="return confirm('Are you sure you want to log out?')">Log Out</a>
            <?php else: ?>
                <a href="Auth/login.php" class="sign-in">Sign In</a>
                <button class="sign-up-btn" onclick="window.location.href='Auth/signup.php'">Sign Up</button>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <section class="hero">
            <h1>Browse Collection</h1>
            <p>Discover our carefully curated selection of books across all genres.</p>
        </section>

        <form method="GET" action="collection.php" class="search-filter-container">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" id="searchInput" placeholder="Search by title or author..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-box">
                <div class="select-wrapper">
                    <label for="genreFilter"><i class="fa-solid fa-sliders"></i> Genre Filter:</label>
                    <select id="genreFilter" name="genre" onchange="this.form.submit()">
                        <option value="all" <?php if($genre == 'all') echo 'selected'; ?>>All Genres</option>
                        <option value="Fiction" <?php if($genre == 'Fiction') echo 'selected'; ?>>Fiction</option>
                        <option value="Self-Help" <?php if($genre == 'Self-Help') echo 'selected'; ?>>Self-Help</option>
                        <option value="Classic" <?php if($genre == 'Classic') echo 'selected'; ?>>Classic</option>
                        <option value="Academic" <?php if($genre == 'Academic') echo 'selected'; ?>>Academic</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-search-submit">Filter</button>
        </form>

        <div class="book-list">
            <?php if ($books_result && $books_result->num_rows > 0): ?>
                <?php while($book = $books_result->fetch_assoc()): ?>
                    
                    <div class="book-item" data-genre="<?php echo htmlspecialchars($book['genre']); ?>">
                        <img src="<?php echo !empty($book['book_img']) ? $book['book_img'] : 'img/default-book.jpg'; ?>" alt="Cover">
                        
                        <div class="book-details">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                            <p class="book-price">RM <?php echo number_format($book['price'], 2); ?></p>
                            
                            <form method="POST" action="add_to_cart.php" id="form-book-<?php echo $book['id']; ?>">
                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                <button type="button" class="btn-add-cart" onclick="handleAddToCart(<?php echo $book['id']; ?>)">
                                    <i class="fa-solid fa-cart-plus"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>

                <?php endwhile; ?>
            <?php else: ?>
                <p style="grid-column: 1/-1; text-align: center; font-size: 1.3rem; color: #0E2C46; font-weight: bold; background: #FDF5E6; padding: 20px; border-radius: 12px; border: 2px solid #0E2C46;">
                    No books found matching your criteria.
                </p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Get login status variable from PHP
        const isLoggedIn = <?php echo $is_logged_in; ?>;

        function handleAddToCart(bookId) {
            if (!isLoggedIn) {
                // Not logged in: send them to login instead of faking a cart
                alert("Please sign in to add books to your shopping cart.");
                window.location.href = "Auth/login.php";
            } else {
                // Logged in: actually submit the form to add_to_cart.php so it saves to the database
                document.getElementById('form-book-' + bookId).submit();
            }
        }

        function viewCartAlert() {
            if (!isLoggedIn) {
                alert("Please log in to view your shopping cart.");
                window.location.href = "Auth/login.php";
            } else {
                // Logged in: go to the real cart page
                window.location.href = "Customer/cust_cart.php";
            }
        }
    </script>
</body>
</html>