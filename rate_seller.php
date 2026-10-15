<?php

require_once __DIR__ . '/auth.php';
require_login();
$buyer_id = (int) $_SESSION['user_id'];

if (!isset($_GET['listing_id']) || !is_numeric($_GET['listing_id'])) {
    die("Invalid listing ID.");
}

$listing_id = (int) $_GET['listing_id'];

$stmt = $conn->prepare("SELECT listing_id, title, seller_id, status FROM listing WHERE listing_id = ?");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Listing not found.");
}
$listing = $result->fetch_assoc();
$seller_id = (int) $listing['seller_id'];

if ($seller_id === $buyer_id) {
    die("You can't rate yourself.");
}

// FR-14: a rating can only be left after a completed transaction (the
// listing has been marked sold by its seller).
if ($listing['status'] !== 'sold') {
    die("You can only rate a seller once the listing has been marked as sold.");
}

$dupe = $conn->prepare("SELECT rating_id FROM rating WHERE listing_id = ? AND buyer_id = ?");
$dupe->bind_param("ii", $listing_id, $buyer_id);
$dupe->execute();
if ($dupe->get_result()->num_rows > 0) {
    die("You've already rated this seller for this listing.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $score = (int) $_POST['score'];
    $review = trim($_POST['review']);

    if ($score < 1 || $score > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } else {

        $insert = $conn->prepare("INSERT INTO rating (listing_id, buyer_id, seller_id, score, review) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("iiiis", $listing_id, $buyer_id, $seller_id, $score, $review);

        if ($insert->execute()) {
            header("Location: listing_details.php?id=" . $listing_id . "&rated=1");
            exit;
        } else {
            $error = "You've already rated this transaction, or something went wrong.";
        }
    }
}

$page_title = 'Rate Seller';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:520px;margin:30px auto 0;">
    <h1>Rate this Seller</h1>
    <p class="muted">For "<strong><?php echo htmlspecialchars($listing['title']); ?></strong>"</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>

        <div class="field">
            <label>Score</label>
            <select name="score" required>
                <option value="">-- Select --</option>
                <option value="5">★★★★★ Excellent</option>
                <option value="4">★★★★☆ Good</option>
                <option value="3">★★★☆☆ Okay</option>
                <option value="2">★★☆☆☆ Poor</option>
                <option value="1">★☆☆☆☆ Terrible</option>
            </select>
        </div>

        <div class="field">
            <label>Review (optional)</label>
            <textarea name="review" rows="4" placeholder="How was your experience with this seller?"></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Submit Rating</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
