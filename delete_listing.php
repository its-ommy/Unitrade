<?php

require_once __DIR__ . '/auth.php';

require_login();
$user_id = (int) $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid listing ID.");
}

$listing_id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT title FROM listing WHERE listing_id = ? AND seller_id = ?");
$stmt->bind_param("ii", $listing_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Listing not found or you are not allowed to delete this listing.");
}
$listing = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $sql = "DELETE FROM listing WHERE listing_id = ? AND seller_id = ?";
    $del_stmt = $conn->prepare($sql);
    $del_stmt->bind_param("ii", $listing_id, $user_id);

    if ($del_stmt->execute()) {
        header("Location: my_listings.php");
        exit;
    } else {
        die("Failed to delete listing.");
    }
}

$page_title = 'Delete Listing';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px;margin:30px auto 0;">
    <h1>Delete this listing?</h1>
    <p class="muted">"<?php echo htmlspecialchars($listing['title']); ?>" will be permanently removed. This can't be undone.</p>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="btn-row">
            <button type="submit" class="btn btn-danger">Yes, delete it</button>
            <a class="btn btn-secondary" href="my_listings.php">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
