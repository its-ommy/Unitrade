<?php

require_once __DIR__ . '/auth.php';

require_login();
$user_id = (int) $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid listing ID.");
}

$listing_id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT title, status FROM listing WHERE listing_id = ? AND seller_id = ?");
$stmt->bind_param("ii", $listing_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Listing not found or you are not allowed to modify this listing.");
}
$listing = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $sql = "UPDATE listing SET status = 'sold' WHERE listing_id = ? AND seller_id = ?";
    $upd_stmt = $conn->prepare($sql);
    $upd_stmt->bind_param("ii", $listing_id, $user_id);

    if ($upd_stmt->execute()) {
        header("Location: my_listings.php");
        exit;
    } else {
        die("Failed to mark listing as sold.");
    }
}

$page_title = 'Mark as Sold';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px;margin:30px auto 0;">
    <h1>Mark as sold?</h1>
    <p class="muted">"<?php echo htmlspecialchars($listing['title']); ?>" will be hidden from active search results. Buyers who already bought from you will be able to leave a rating once it's marked sold.</p>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Yes, mark as sold</button>
            <a class="btn btn-secondary" href="my_listings.php">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
