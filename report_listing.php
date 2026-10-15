<?php
require_once __DIR__ . '/auth.php';
$current_user = require_non_admin(get_current_user_data($conn));
$current_user_id = (int) $current_user['user_id'];


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid listing ID.");
}

$listing_id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT listing_id, title, seller_id FROM listing WHERE listing_id = ?");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Listing not found.");
}
$listing = $result->fetch_assoc();

if ((int) $listing['seller_id'] === $current_user_id) {
    die("You can't report your own listing.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $reason = trim($_POST['reason']);

    if ($reason === '') {
        $error = "Please describe why you're reporting this listing.";
    } else {

        $insert = $conn->prepare("INSERT INTO report (listing_id, reporter_id, reason) VALUES (?, ?, ?)");
        $insert->bind_param("iis", $listing_id, $current_user_id, $reason);

        if ($insert->execute()) {
            header("Location: listing_details.php?id=" . $listing_id . "&reported=1");
            exit;
        } else {
            $error = "Failed to submit report. Please try again.";
        }
    }
}

$page_title = 'Report Listing';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:520px;margin:30px auto 0;">
    <h1>Report Listing</h1>
    <p class="muted">Reporting "<strong><?php echo htmlspecialchars($listing['title']); ?></strong>". It will be hidden from public browsing while an administrator reviews it.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="field">
            <label>Why are you reporting this listing?</label>
            <textarea name="reason" rows="4" required placeholder="e.g. suspected fraud, prohibited item, misleading description..."><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-danger">Submit Report</button>
            <a class="btn btn-secondary" href="listing_details.php?id=<?php echo $listing_id; ?>">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
