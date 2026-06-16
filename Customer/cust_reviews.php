<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Customer';

// ── SUBMIT NEW REVIEW ───────────────────────────────────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $book_id = intval($_POST['book_id']);
    $rating  = intval($_POST['rating']);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error_msg = "Please select a star rating.";
    } elseif (empty($comment)) {
        $error_msg = "Please write a comment before submitting.";
    } elseif (strlen($comment) > 1000) {
        $error_msg = "Comment is too long (max 1000 characters).";
    } else {
        // Safe Check: Customer actually bought this book using Prepared Statements
        $stmt = $conn->prepare("
            SELECT oi.id FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.user_id = ? AND oi.book_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $user_id, $book_id);
        $stmt->execute();
        $bought = $stmt->get_result();

        if (!$bought || $bought->num_rows === 0) {
            $error_msg = "You can only review books you have purchased.";
        } else {
            // Check not already reviewed this book
            $stmt2 = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND book_id = ? LIMIT 1");
            $stmt2->bind_param("ii", $user_id, $book_id);
            $stmt2->execute();
            $already = $stmt2->get_result();

            if ($already && $already->num_rows > 0) {
                $error_msg = "You have already submitted a review for this book.";
            } else {
                // Insert New Review (No need for mysqli_real_escape_string when using Prepared Statements)
                $stmt3 = $conn->prepare("INSERT INTO reviews (user_id, book_id, rating, comment, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt3->bind_param("iiis", $user_id, $book_id, $rating, $comment);
                $stmt3->execute();

                // System Log
                $log_message = "User ID $user_id submitted a review for book ID $book_id (rating: $rating)";
                $stmt4 = $conn->prepare("INSERT INTO system_logs (log_message) VALUES (?)");
                $stmt4->bind_param("s", $log_message);
                $stmt4->execute();

                $success_msg = "Your review has been submitted and is awaiting approval. Thank you!";
            }
        }
    }
}

// ── DELETE OWN REVIEW (only if pending) ─────────────────────────────────────
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $rid = intval($_GET['delete']);
    $stmt5 = $conn->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt5->bind_param("ii", $rid, $user_id);
    $stmt5->execute();
    
    header("Location: cust_reviews.php?deleted=1");
    exit();
}

// ── LOAD: Books this user has purchased (eligible to review) ────────────────
$purchased_books = [];
$stmt6 = $conn->prepare("
    SELECT DISTINCT b.id, b.title, b.author, b.book_img
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN books b  ON oi.book_id  = b.id
    WHERE o.user_id = ?
    ORDER BY b.title ASC
");
$stmt6->bind_param("i", $user_id);
$stmt6->execute();
$pb_q = $stmt6->get_result();

if ($pb_q && $pb_q->num_rows > 0) {
    while ($r = $pb_q->fetch_assoc()) {
        $purchased_books[] = $r;
    }
}

// ── LOAD: This user's own reviews ───────────────────────────────────────────
$my_reviews = [];
$stmt7 = $conn->prepare("
    SELECT r.*, b.title, b.author, b.book_img
    FROM reviews r
    JOIN books b ON r.book_id = b.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt7->bind_param("i", $user_id);
$stmt7->execute();
$mr_q = $stmt7->get_result();

if ($mr_q && $mr_q->num_rows > 0) {
    while ($r = $mr_q->fetch_assoc()) {
        $my_reviews[] = $r;
    }
}
$already_reviewed_ids = array_column($my_reviews, 'book_id');

// ── LOAD: Approved reviews for all books (community view) ───────────────────
$all_reviews = [];
$ar_q = $conn->query("
    SELECT r.*, u.fullname, b.title AS book_title, b.author, b.book_img
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN books b ON r.book_id = b.id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 50
");
if ($ar_q && $ar_q->num_rows > 0) {
    while ($r = $ar_q->fetch_assoc()) {
        $all_reviews[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dreambound – Reviews</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Englebert&display=swap" rel="stylesheet">
    <style>
        :root{ --blue:#0A2647; --orange:#FC9D01; }
        *{ margin:0; padding:0; box-sizing:border-box; font-family:'Englebert',sans-serif; }
        body{ background:linear-gradient(135deg,#0A2647,#144272); height:100vh; display:flex; overflow:hidden; padding:15px; }
        .dashboard{ display:flex; width:100%; height:100%; background:rgba(255,255,255,0.03); backdrop-filter:blur(10px); border-radius:24px; border:1px solid rgba(255,255,255,0.1); overflow:hidden; }
        
        /* sidebar */
        .sidebar{ width:280px; background:rgba(10,38,71,0.7); display:flex; flex-direction:column; align-items:center; padding:40px 24px; color:white; border-right:1px solid rgba(255,255,255,0.05); flex-shrink:0; }
        .profile{ text-align:center; width:100%; margin-bottom:40px; }
        .profile-circle{ width:85px;height:85px;background:linear-gradient(135deg,rgba(255,255,255,.1),rgba(255,255,255,.05));border-radius:50%;border:2px solid var(--orange);margin:0 auto 15px;display:flex;align-items:center;justify-content:center; }
        .profile-circle i{ font-size:32px;color:#fff; }
        .profile h2{ font-size:18px;font-weight:normal;color:#fff;text-transform:uppercase;letter-spacing:1.5px; }
        .menu{ width:100%; }
        .menu ul{ list-style:none;padding:0;margin:0;width:100%; }
        .menu-item{ display:flex;align-items:center;color:rgba(255,255,255,.6);text-decoration:none;padding:18px 24px;margin-bottom:12px;border-radius:20px;font-size:22px;letter-spacing:.5px;transition:all .4s; }
        .menu-item i{ margin-right:20px;font-size:22px; }
        .menu-item:hover{ color:#fff;background:rgba(255,255,255,.05); }
        .menu-item.active{ background:var(--orange);color:var(--blue);font-weight:bold; }
        .logout-btn{ margin-top:auto;background:rgba(255,255,255,.02);color:#ff6b6b;border:1px solid rgba(255,77,77,.25);padding:16px 20px;cursor:pointer;border-radius:20px;width:100%;display:flex;align-items:center;justify-content:center;gap:12px;font-size:18px;transition:all .3s; }
        .logout-btn:hover{ background:#ff4d4d;color:white; }
        
        /* content */
        .content{ flex:1;padding:40px 50px;overflow-y:auto;background:var(--orange);border-top-left-radius:24px;border-bottom-left-radius:24px; }
        .content::-webkit-scrollbar{ width:6px; }
        .content::-webkit-scrollbar-thumb{ background:rgba(0,0,0,.1);border-radius:10px; }
        header h1{ font-size:42px;color:var(--blue);margin-bottom:4px; }
        header p{ font-size:17px;color:#fff;margin-bottom:28px; }
        
        /* tabs */
        .tabs{ display:flex;gap:10px;margin-bottom:28px;flex-wrap:wrap; }
        .tab-btn{ padding:10px 24px;border:none;border-radius:12px;font-size:17px;cursor:pointer;font-family:'Englebert',sans-serif;transition:all .2s;background:rgba(255,255,255,.3);color:var(--blue); }
        .tab-btn.active{ background:var(--blue);color:var(--orange); }
        
        /* cards */
        .card{ background:#fff;border-radius:20px;padding:28px;margin-bottom:22px;box-shadow:0 6px 20px rgba(0,0,0,.06); }
        .card-title{ font-size:22px;color:var(--blue);margin-bottom:18px;display:flex;align-items:center;gap:10px; }
        .card-title i{ color:var(--orange); }
        
        /* write review form & responsive grid */
        .book-select-grid{ display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:20px; }
        .book-opt{ border:2px solid #e2e8f0;border-radius:14px;padding:10px;cursor:pointer;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center; }
        .book-opt:hover{ border-color:var(--orange); }
        .book-opt.selected{ border-color:var(--blue);background:#eff6ff; }
        .book-opt img{ width:60px;height:80px;object-fit:cover;border-radius:8px; }
        .book-opt .opt-title{ font-size:13px;color:var(--blue);font-weight:bold;line-height:1.3; }
        .book-opt .opt-author{ font-size:12px;color:#94a3b8; }
        
        /* star rating */
        .star-rating{ display:flex;flex-direction:row-reverse;gap:4px;margin-bottom:16px;width:fit-content; }
        .star-rating input{ display:none; }
        .star-rating label{ font-size:32px;color:#d1d5db;cursor:pointer;transition:color .15s; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label{ color:#fbbf24; }
        textarea.form-input{ width:100%;padding:14px;border:1.5px solid #e2e8f0;border-radius:14px;font-size:16px;font-family:'Englebert',sans-serif;resize:vertical;min-height:120px;outline:none;color:var(--blue);transition:border .2s; }
        textarea.form-input:focus{ border-color:var(--blue); }
        .char-count{ font-size:13px;color:#94a3b8;text-align:right;margin-top:4px; }
        .submit-btn{ background:var(--blue);color:var(--orange);border:none;padding:13px 30px;border-radius:14px;font-size:18px;cursor:pointer;font-family:'Englebert',sans-serif;transition:all .2s;display:flex;align-items:center;gap:10px;margin-top:16px; }
        .submit-btn:hover{ background:#071c35; }
        
        /* alerts */
        .alert{ padding:14px 18px;border-radius:12px;font-size:16px;margin-bottom:18px;display:flex;align-items:center;gap:12px; }
        .alert-success{ background:#ecfdf5;border:1.5px solid #6ee7b7;color:#065f46; }
        .alert-error  { background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626; }
        
        /* my reviews list */
        .review-card{ border:1.5px solid #f1f5f9;border-radius:16px;padding:18px;margin-bottom:14px;display:flex;gap:16px;align-items:flex-start;position:relative; }
        .rev-book-img{ width:55px;height:72px;object-fit:cover;border-radius:8px;flex-shrink:0; }
        .rev-body{ flex:1; }
        .rev-book-title{ font-size:17px;color:var(--blue);font-weight:bold; }
        .rev-author{ font-size:14px;color:#94a3b8;margin-bottom:6px; }
        .rev-stars{ color:#fbbf24;font-size:16px;margin-bottom:6px; }
        .rev-comment{ font-size:15px;color:#475569;line-height:1.5; }
        .rev-date{ font-size:12px;color:#94a3b8;margin-top:6px; }
        .status-pill{ font-size:11px;font-weight:bold;padding:3px 10px;border-radius:20px;position:absolute;top:14px;right:14px; }
        .pill-pending { background:#fef3c7;color:#92400e; }
        .pill-approved{ background:#dcfce7;color:#166534; }
        .pill-rejected{ background:#fee2e2;color:#991b1b; }
        .del-btn{ position:absolute;bottom:14px;right:14px;background:none;border:1.5px solid #fca5a5;color:#dc2626;padding:4px 12px;border-radius:8px;font-size:13px;cursor:pointer;font-family:'Englebert',sans-serif;transition:all .2s; }
        .del-btn:hover{ background:#fee2e2; }
        
        /* community reviews */
        .community-card{ background:#f8fafc;border-radius:16px;padding:16px;margin-bottom:12px;display:flex;gap:14px;align-items:flex-start; }
        .comm-avatar{ width:40px;height:40px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--orange);font-size:17px;flex-shrink:0; }
        .comm-body{ flex:1; }
        .comm-name{ font-size:16px;color:var(--blue);font-weight:bold; }
        .comm-book{ font-size:13px;color:#64748b;margin-bottom:4px; }
        .comm-stars{ color:#fbbf24;font-size:14px;margin-bottom:5px; }
        .comm-comment{ font-size:14px;color:#475569;line-height:1.5; }
        .comm-date{ font-size:12px;color:#94a3b8;margin-top:4px; }
        .empty-state{ text-align:center;padding:40px;color:#64748b; }
        .empty-state i{ font-size:48px;color:#cbd5e1;margin-bottom:12px; }
        
        /* tab panes */
        .tab-pane{ display:none; }
        .tab-pane.active{ display:block; }
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
                <li><a href="cust_home.php"     class="menu-item"><i class="fas fa-th-large"></i> HOME</a></li>
                <li><a href="cust_cart.php"     class="menu-item"><i class="fas fa-shopping-bag"></i> CART</a></li>
                <li><a href="cust_orders.php"   class="menu-item"><i class="fas fa-receipt"></i> ORDERS</a></li>
                <li><a href="cust_reviews.php"  class="menu-item active"><i class="fas fa-star"></i> REVIEWS</a></li>
                <li><a href="cust_settings.php" class="menu-item"><i class="fas fa-sliders-h"></i> SETTINGS</a></li>
            </ul>
        </nav>
        <button class="logout-btn" onclick="location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> LOG OUT</button>
    </aside>

    <main class="content">
        <header>
            <h1>Book Reviews</h1>
            <p>Share your thoughts and read what others say</p>
        </header>

        <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success"><i class="fas fa-trash"></i> Your review has been deleted.</div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('write',this)"><i class="fas fa-pen"></i> Write a Review</button>
            <button class="tab-btn"        onclick="switchTab('mine', this)"><i class="fas fa-user"></i> My Reviews <?php if(count($my_reviews)>0) echo '<span style="background:var(--orange);color:var(--blue);border-radius:20px;padding:1px 8px;font-size:13px;margin-left:4px;">'.count($my_reviews).'</span>'; ?></button>
            <button class="tab-btn"        onclick="switchTab('all',  this)"><i class="fas fa-comments"></i> Community Reviews <?php if(count($all_reviews)>0) echo '<span style="background:var(--orange);color:var(--blue);border-radius:20px;padding:1px 8px;font-size:13px;margin-left:4px;">'.count($all_reviews).'</span>'; ?></button>
        </div>

        <div class="tab-pane active" id="pane-write">
            <div class="card">
                <div class="card-title"><i class="fas fa-pen-nib"></i> Write a Review</div>
                <?php if (empty($purchased_books)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <p style="font-size:17px;margin-bottom:8px;">No purchased books yet</p>
                    <p style="font-size:14px;">You can only review books you have purchased. <a href="cust_home.php" style="color:var(--blue);font-weight:bold;">Browse books →</a></p>
                </div>
                <?php else: ?>
                <form method="POST">
                    <p style="font-size:15px;color:#64748b;margin-bottom:14px;">Select a book you purchased:</p>
                    <div class="book-select-grid" id="bookGrid">
                        <?php foreach ($purchased_books as $pb):
                            $already = in_array($pb['id'], $already_reviewed_ids);
                            $img = !empty($pb['book_img']) ? '../' . htmlspecialchars($pb['book_img']) : '../img/book1.png';
                        ?>
                        <div class="book-opt <?php echo $already ? 'opacity-50' : ''; ?>" 
                             style="<?php echo $already ? 'opacity:.5;cursor:not-allowed;' : ''; ?>" 
                             onclick="<?php echo $already ? 'void(0)' : 'selectBook(this,'.$pb['id'].')'; ?>" 
                             title="<?php echo $already ? 'Already reviewed' : ''; ?>">
                            <img src="<?php echo $img; ?>" onerror="this.src='../img/book1.png'" alt="Cover">
                            <div class="opt-title"><?php echo htmlspecialchars($pb['title']); ?></div>
                            <div class="opt-author"><?php echo htmlspecialchars($pb['author']); ?></div>
                            <?php if ($already): ?>
                            <span style="font-size:11px;color:#10b981;font-weight:bold;">✓ Reviewed</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="book_id" id="selectedBookId" value="">
                    
                    <div id="reviewForm" style="display:none;">
                        <hr style="border:none;border-top:1px solid #f1f5f9;margin:20px 0;">
                        <p style="font-size:15px;color:#64748b;margin-bottom:8px;">Your Rating:</p>
                        <div class="star-rating" id="starRating">
                            <input type="radio" name="rating" id="s5" value="5"><label for="s5">★</label>
                            <input type="radio" name="rating" id="s4" value="4"><label for="s4">★</label>
                            <input type="radio" name="rating" id="s3" value="3"><label for="s3">★</label>
                            <input type="radio" name="rating" id="s2" value="2"><label for="s2">★</label>
                            <input type="radio" name="rating" id="s1" value="1"><label for="s1">★</label>
                        </div>
                        
                        <p style="font-size:15px;color:#64748b;margin-bottom:8px;">Your Comment:</p>
                        <textarea class="form-input" name="comment" id="commentBox" maxlength="1000"
                            placeholder="Share your thoughts about this book..." oninput="updateChar()"></textarea>
                        <div class="char-count"><span id="charCount">0</span> / 1000</div>
                        
                        <button type="submit" name="submit_review" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Submit Review
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane" id="pane-mine">
            <div class="card">
                <div class="card-title"><i class="fas fa-user-edit"></i> My Reviews</div>
                <?php if (empty($my_reviews)): ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <p style="font-size:17px;">You haven't written any reviews yet.</p>
                </div>
                <?php else: ?>
                <?php foreach ($my_reviews as $rev):
                    $img = !empty($rev['book_img']) ? '../'.$rev['book_img'] : '../img/book1.png';
                    $pill_class = 'pill-'.$rev['status'];
                ?>
                <div class="review-card">
                    <img src="<?php echo htmlspecialchars($img); ?>" class="rev-book-img" onerror="this.src='../img/book1.png'" alt="Cover">
                    <div class="rev-body">
                        <div class="rev-book-title"><?php echo htmlspecialchars($rev['title']); ?></div>
                        <div class="rev-author">by <?php echo htmlspecialchars($rev['author']); ?></div>
                        <div class="rev-stars"><?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5-$rev['rating']); ?></div>
                        <div class="rev-comment">"<?php echo htmlspecialchars($rev['comment']); ?>"</div>
                        <div class="rev-date"><i class="far fa-clock"></i> <?php echo date('d M Y', strtotime($rev['created_at'])); ?></div>
                    </div>
                    <span class="status-pill <?php echo $pill_class; ?>"><?php echo ucfirst($rev['status']); ?></span>
                    <?php if ($rev['status'] === 'pending'): ?>
                    <a href="cust_reviews.php?delete=<?php echo $rev['id']; ?>"
                       class="del-btn"
                       onclick="return confirm('Delete this review?')">
                        <i class="fas fa-trash-alt"></i> Delete
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane" id="pane-all">
            <div class="card">
                <div class="card-title"><i class="fas fa-comments"></i> Community Reviews</div>
                <div style="position:relative;max-width:380px;margin-bottom:20px;">
                    <i class="fas fa-search" style="position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;"></i>
                    <input type="text" id="communitySearch" placeholder="Filter by book title..."
                        oninput="filterCommunity()"
                        style="width:100%;padding:11px 16px 11px 44px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:16px;font-family:'Englebert',sans-serif;outline:none;color:var(--blue);">
                </div>

                <?php if (empty($all_reviews)): ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p style="font-size:17px;">No approved reviews yet. Be the first!</p>
                </div>
                <?php else: ?>
                <div id="communityList">
                <?php foreach ($all_reviews as $rev):
                    $initials = strtoupper(substr($rev['fullname'],0,1));
                    $anonymous = ($rev['user_id'] == $user_id) ? 'You' : substr($rev['fullname'],0,1).str_repeat('*', max(0,strlen($rev['fullname'])-2)).substr($rev['fullname'],-1);
                ?>
                <div class="community-card" data-title="<?php echo strtolower(htmlspecialchars($rev['book_title'])); ?>">
                    <div class="comm-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="comm-body">
                        <div class="comm-name"><?php echo htmlspecialchars($anonymous); ?></div>
                        <div class="comm-book"><i class="fas fa-book" style="color:var(--orange);"></i> <?php echo htmlspecialchars($rev['book_title']); ?> <span style="color:#94a3b8;">· by <?php echo htmlspecialchars($rev['author']); ?></span></div>
                        <div class="comm-stars"><?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5-$rev['rating']); ?></div>
                        <div class="comm-comment">"<?php echo htmlspecialchars($rev['comment']); ?>"</div>
                        <div class="comm-date"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // ── Tab switching ────────────────────────────────────────────────────────
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('pane-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // ── Book selection ───────────────────────────────────────────────────────
    function selectBook(el, id) {
        document.querySelectorAll('.book-opt').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('selectedBookId').value = id;
        document.getElementById('reviewForm').style.display = 'block';
        document.getElementById('reviewForm').scrollIntoView({ behavior:'smooth', block:'nearest' });
    }

    // ── Character counter ────────────────────────────────────────────────────
    function updateChar() {
        document.getElementById('charCount').textContent = document.getElementById('commentBox').value.length;
    }

    // ── Community filter ─────────────────────────────────────────────────────
    function filterCommunity() {
        const q = document.getElementById('communitySearch').value.toLowerCase();
        document.querySelectorAll('#communityList .community-card').forEach(c => {
            c.style.display = c.dataset.title.includes(q) ? '' : 'none';
        });
    }

    // ── Safe Combined Load Initialization ────────────────────────────────────
    window.addEventListener('load', function() {
        // Auto open "My Reviews" tab if redirected after delete
        <?php if (isset($_GET['deleted'])): ?>
        switchTab('mine', document.querySelectorAll('.tab-btn')[1]);
        <?php endif; ?>

        // Auto open form + highlight book if redirected with ?book_id
        <?php if (isset($_GET['book_id'])): ?>
        const id = <?php echo intval($_GET['book_id']); ?>;
        document.querySelectorAll('.book-opt').forEach(el => {
            if (el.onclick && el.onclick.toString().includes('selectBook')) {
                const match = el.onclick.toString().match(/selectBook\(this,(\d+)\)/);
                if (match && parseInt(match[1]) === id) {
                    selectBook(el, id);
                }
            }
        });
        <?php endif; ?>
    });
</script>
</body>
</html>