<?php

require_once __DIR__ . '/auth.php';

require_login();
$user_id = (int) $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid listing ID.");
}

$listing_id = (int) $_GET['id'];

$sql = "SELECT listing_id, title, description, price, condition_type, category_id
        FROM listing
        WHERE listing_id = ? AND seller_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $listing_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Listing not found or you are not allowed to edit this listing.");
}

$listing = $result->fetch_assoc();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = $_POST['price'];
    $category_id = (int) $_POST['category_id'];
    $condition_type = $_POST['condition_type'];

    if ($title === '' || $description === '') {
        $error = "Title and description are required.";
    } elseif (!is_numeric($price) || $price < 0) {
        $error = "Invalid price.";
    } elseif (!in_array($condition_type, ['new', 'used'], true)) {
        $error = "Invalid condition.";
    } else {

        $update_sql = "UPDATE listing
                       SET title = ?, description = ?, price = ?, category_id = ?, condition_type = ?
                       WHERE listing_id = ? AND seller_id = ?";

        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param(
            "ssdissi",
            $title, $description, $price, $category_id, $condition_type, $listing_id, $user_id
        );

        if ($update_stmt->execute()) {
            header("Location: my_listings.php");
            exit;
        } else {
            $error = "Failed to update listing.";
        }

        // Keep the form pre-filled with the attempted values on error
        $listing['title'] = $title;
        $listing['description'] = $description;
        $listing['price'] = $price;
        $listing['category_id'] = $category_id;
        $listing['condition_type'] = $condition_type;
    }
}

$category_result = $conn->query("SELECT category_id, name FROM category ORDER BY name ASC");

$page_title = 'Edit Listing';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Edit Listing</h1>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card" style="max-width:560px;">
    <form method="POST">
        <?php echo csrf_field(); ?>

        <div class="field">
            <label>Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($listing['title']); ?>" required>
        </div>

        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="5" required><?php echo htmlspecialchars($listing['description']); ?></textarea>
        </div>

        <div class="field-row">
            <div class="field">
                <label>Price (TZS)</label>
                <input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($listing['price']); ?>" required>
            </div>
            <div class="field">
                <label>Condition</label>
                <select name="condition_type" required>
                    <option value="new" <?php echo $listing['condition_type'] === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="used" <?php echo $listing['condition_type'] === 'used' ? 'selected' : ''; ?>>Used</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label>Category</label>
            <select name="category_id" required>
                <?php while ($category = $category_result->fetch_assoc()): ?>
                    <option value="<?php echo $category['category_id']; ?>" <?php echo ((int) $category['category_id'] === (int) $listing['category_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Update Listing</button>
            <a class="btn btn-secondary" href="my_listings.php">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
