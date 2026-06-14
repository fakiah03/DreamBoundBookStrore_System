<?php
session_start();

// Verify whether the user has an active login session.
$isLoggedIn = false;
$dashboardURL = 'Auth/login.php'; 

if (isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    // Redirect users according to their assigned role.
    if ($_SESSION['role'] === 'admin') {
        $dashboardURL = 'Admin/ad_Dashboard.php'; 
    } else {
        $dashboardURL = 'Customer/cust_home.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DREAMBOUND BOOKSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    
    <style>
        html {
            scroll-behavior: smooth;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Englebert', cursive, sans-serif;
        }

        body {
            background-color: #FC9D01; 
            color: #0E2C46;
            line-height: 1.6;
        }

        .navbar {
            background-color: #0E2C46; 
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            border-bottom: 3px solid #FC9D01;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #logo {
            height: 55px;
            width: auto;
            object-fit: contain;
        }

        #titlen {
            font-size: 1.6rem;
            letter-spacing: 1px;
            color: #ffffff;
        }

        #titlen span {
            color: #FC9D01;
        }

        .menu ul {
            list-style: none;
            display: flex;
            gap: 25px;
        }

        .menu a {
            text-decoration: none;
            color: white;
            font-size: 1.15rem;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .menu a:hover {
            background-color: #FC9D01;
            color: #0E2C46;
        }

        .hero {
            padding: 50px 20px; 
            background-color: #FC9D01; 
            background-image: radial-gradient(rgba(14, 44, 70, 0.15) 15%, transparent 16%); 
            background-size: 16px 16px;
            border-bottom: 3px solid #0E2C46;
            display: flex;
            justify-content: center;
        }

        .welcome {
            width: 100%;
            max-width: 850px; 
            background-color: #FDF5E6; 
            border: 3px solid #0E2C46; 
            padding: 45px 40px; 
            border-radius: 20px;
            box-shadow: 10px 10px 0px #0E2C46; 
            text-align: center; 
        }

        #t1 {
            font-size: 2.5rem; 
            margin-bottom: 15px;
            color: #0E2C46;
            letter-spacing: 1px;
            line-height: 1.3;
        }

        .subtitle {
            font-size: 1.2rem; 
            color: #444444;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .button-group {
            display: flex;
            justify-content: center; 
            gap: 20px;
        }

        .btn {
            padding: 12px 30px; 
            font-size: 1.15rem;
            font-weight: bold;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        #browseBtn {
            background-color: #0E2C46;
            color: white;
            border: 2px solid #0E2C46;
        }

        #browseBtn:hover {
            background-color: #FC9D01;
            color: #0E2C46;
            transform: translateY(-2px);
        }

        #joinBtn {
            background-color: transparent;
            color: #0E2C46;
            border: 2px solid #0E2C46;
        }

        #joinBtn:hover {
            background-color: #0E2C46;
            color: white;
            transform: translateY(-2px);
        }

        #featured-books {
            padding: 60px 40px;
            max-width: 1300px;
            margin: 0 auto;
            text-align: center;
        }

        #featured-books h2 {
            font-size: 2.5rem;
            color: #0E2C46;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5);
        }

        .book-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .book-card {
            background-color: #FDF5E6; 
            border: 2px solid #0E2C46;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }

        .book-card img {
            width: 100%;
            height: 230px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #0E2C46;
            margin-bottom: 12px;
        }

        .book-card h3 {
            font-size: 1.25rem;
            color: #0E2C46;
            margin-bottom: 4px;
        }

        .book-card p {
            color: #555;
            font-size: 0.95rem;
        }

        /* --- View More Container --- */
        .view-more-container {
            margin-top: 40px;
            display: flex;
            justify-content: center;
        }

        #viewMoreBtn {
            background-color: #0E2C46;
            color: white;
            border: 3px solid #0E2C46;
            padding: 12px 35px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 30px;
            cursor: pointer;
            box-shadow: 5px 5px 0px #FDF5E6;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        #viewMoreBtn:hover {
            background-color: #FDF5E6;
            color: #0E2C46;
            transform: translateY(-3px);
            box-shadow: 7px 7px 0px #0E2C46;
        }

        /* --- About Us Section --- */
        #about-us {
            background-color: #FDF5E6; 
            padding: 60px 40px;
            border-top: 2px solid #0E2C46;
            border-bottom: 2px solid #0E2C46;
        }

        .about-container {
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }

        #about-us h2 {
            font-size: 2.5rem;
            color: #0E2C46;
            margin-bottom: 20px;
        }

        #about-us p {
            font-size: 1.2rem;
            color: #333;
            line-height: 1.8;
        }

        /* --- Contact Information Section --- */
        #contact-info {
            padding: 60px 40px;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        #t6 {
            font-size: 2.5rem;
            color: #0E2C46;
            margin-bottom: 25px;
        }

        .contact-panel {
            background-color: #FDF5E6;
            border: 2px solid #0E2C46;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .contact-panel p {
            font-size: 1.2rem;
            color: #0E2C46;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .contact-panel i {
            font-size: 1.3rem;
            color: #FC9D01;
            -webkit-text-stroke: 1px #0E2C46;
        }

        /* --- Footer --- */
        footer {
            background-color: #0E2C46;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 1rem;
            border-top: 3px solid #FC9D01;
        }

        /* --- RESPONSIVE ADJUSTMENTS --- */
        @media (max-width: 992px) {
            .book-grid { grid-template-columns: repeat(2, 1fr); }
            #t1 { font-size: 2.2rem; }
        }
        
        @media (max-width: 600px) {
            .navbar { flex-direction: column; gap: 15px; text-align: center; }
            .menu ul { gap: 10px; }
            .book-grid { grid-template-columns: 1fr; }
            #t1 { font-size: 1.9rem; }
            .button-group { flex-direction: column; align-items: center; gap: 12px; }
            .btn { width: 100%; max-width: 280px; }
        }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="logo">
            <img id="logo" src="img/logo1.png" alt="Dreambound Logo">
            <h1 id="titlen">DREAMBOUND <span>BOOKSTORE</span></h1>
        </div>
        
        <nav class="menu">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="#featured-books">Books</a></li>
                <li><a href="#about-us">About Us</a></li>
                <li><a href="#contact-info">Contact</a></li>
                <?php if ($isLoggedIn): ?>
                    <li><a href="<?php echo $dashboardURL; ?>" style="background-color: #FC9D01; color: #0E2C46;">Dashboard</a></li>
                <?php else: ?>
                    <li><a href="Auth/login.php">Sign In</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="welcome">
                <h1 id="t1">WELCOME TO DREAMBOUND BOOKSTORE</h1>
                <p class="subtitle">Discover your next favorite read in our handpicked collection of literary fiction, compelling non-fiction, and timeless classics.</p>
                <div class="button-group">
                    <button class="btn" id="browseBtn">Browse Collection</button>
                    
                    <?php if ($isLoggedIn): ?>
                        <button class="btn" id="joinBtn" onclick="window.location.href='<?php echo $dashboardURL; ?>'"><i class="fas fa-arrow-right"></i> My Dashboard</button>
                    <?php else: ?>
                        <button class="btn" id="joinBtn" onclick="window.location.href='Auth/login.php'">Join Us</button>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="featured-books">
            <h2>Featured Books</h2>
            <div class="book-grid">
                <div class="book-card">
                    <img src="img/book1.jpg" alt="The Great Gatsby">
                    <h3>The Great Gatsby</h3>
                    <p>F. Scott Fitzgerald</p>
                </div>
                <div class="book-card">
                    <img src="img/book2.jpg" alt="Atomic Habits">
                    <h3>Atomic Habits</h3>
                    <p>James Clear</p>
                </div>
                <div class="book-card">
                    <img src="img/book3.jpg" alt="To Kill A Mockingbird">
                    <h3>To Kill A Mockingbird</h3>
                    <p>Harper Lee</p>
                </div>
                <div class="book-card">
                    <img src="img/book4.jpg" alt="Brave New World">
                    <h3>Brave New World</h3>
                    <p>Aldous Huxley</p>
                </div>
            </div>

            <div class="view-more-container">
                <button id="viewMoreBtn">See More Collection <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </section>

        <section id="about-us">
            <div class="about-container">
                <h2>About Us</h2>
                <p>Welcome to Dreambound Bookstore, your ultimate gateway to a world of endless imagination and knowledge. Founded with a deep passion for literature, we strive to bring readers closer to the books that inspire, challenge, and entertain them. Our cozy space is dedicated to cultivating a love for reading across all generations.</p>
            </div>
        </section>

        <section id="contact-info">
            <h2 id="t6">Contact Information</h2>
            <div class="contact-panel">
                <p><i class="fa-solid fa-envelope"></i> <strong>Email:</strong> support@dreambound.com</p>
                <p><i class="fa-solid fa-phone"></i> <strong>Phone:</strong> +60 9-123 4567</p>
                <p><i class="fa-solid fa-location-dot"></i> <strong>Location:</strong> Machang, Kelantan, Malaysia</p>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 Dreambound Bookstore. All rights reserved.</p>
    </footer>

    <script>
        document.querySelector('a[href="#featured-books"]').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('#featured-books').scrollIntoView({ behavior: 'smooth' });
        });

        document.querySelector('a[href="#about-us"]').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('#about-us').scrollIntoView({ behavior: 'smooth' });
        });

        document.querySelector('a[href="#contact-info"]').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('#contact-info').scrollIntoView({ behavior: 'smooth' });
        });

        // Logic for determining the navigation of the Hero button.
        document.getElementById('browseBtn').addEventListener('click', function() {
            // Redirect users to the customer store if logged in; otherwise, direct them to the standard listing page.
            <?php if ($isLoggedIn): ?>
                window.location.href = 'Customer/cust_home.php'; 
            <?php else: ?>
                window.location.href = 'collection.php'; 
            <?php endif; ?>
        });

        // Navigation logic for the button below Featured Books.
        document.getElementById('viewMoreBtn').addEventListener('click', function() {
            <?php if ($isLoggedIn): ?>
                window.location.href = 'Customer/cust_home.php'; 
            <?php else: ?>
                window.location.href = 'collection.php'; 
            <?php endif; ?>
        });
    </script>
</body>
</html>