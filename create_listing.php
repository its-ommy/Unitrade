<?php

require_once __DIR__ . '/auth.php';

$current_user = require_verified_user($conn);
$current_user = require_non_admin($current_user);

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $seller_id = (int) $current_user['user_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $condition_type = $_POST['condition_type'] ?? '';
    $category_id = (int) ($_POST['category_id'] ?? 0);

    $photos = [];

    if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        foreach ($_FILES['photos']['name'] as $index => $name) {
            if ($_FILES['photos']['error'][$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $photos[] = [
                'name' => $_FILES['photos']['name'][$index],
                'tmp_name' => $_FILES['photos']['tmp_name'][$index],
                'error' => $_FILES['photos']['error'][$index],
                'size' => (int) $_FILES['photos']['size'][$index]
            ];
        }
    }

    if (
        $title === '' ||
        $price < 0 ||
        !in_array($condition_type, ['new', 'used'], true) ||
        $category_id <= 0
    ) {
        $message = "Please fill in all required fields correctly.";

    } elseif (count($photos) < 1) {
        $message = "Please upload at least one product photo.";

    } elseif (count($photos) > 8) {
        $message = "You can upload a maximum of 8 photos.";

    } else {

        $allowed_types = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        $upload_directory = __DIR__ . '/uploads/listings/';
        $saved_files = [];
        $upload_error = '';

        if (!is_dir($upload_directory)) {
            mkdir($upload_directory, 0755, true);
        }

        foreach ($photos as $photo) {

            if ($photo['error'] !== UPLOAD_ERR_OK) {
                $upload_error = "One of the photos could not be uploaded.";
                break;
            }

            if ($photo['size'] > 5 * 1024 * 1024) {
                $upload_error = "Each photo must not exceed 5 MB.";
                break;
            }

            $detected_type = mime_content_type($photo['tmp_name']);

            if (!isset($allowed_types[$detected_type])) {
                $upload_error = "Only JPG, PNG or WEBP photos are allowed.";
                break;
            }
        }

        if ($upload_error !== '') {
            $message = $upload_error;

        } else {

            $college_sql = "SELECT college_id FROM user WHERE user_id = ?";
            $college_stmt = $conn->prepare($college_sql);
            $college_stmt->bind_param("i", $seller_id);
            $college_stmt->execute();

            $seller = $college_stmt->get_result()->fetch_assoc();

            if (!$seller) {
                $message = "Seller account not found.";

            } else {

                $college_id = (int) $seller['college_id'];

                $insert_sql = "INSERT INTO listing
                    (seller_id, college_id, category_id, title,
                     description, price, condition_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

                $insert_stmt = $conn->prepare($insert_sql);

                $insert_stmt->bind_param(
                    "iiissds",
                    $seller_id,
                    $college_id,
                    $category_id,
                    $title,
                    $description,
                    $price,
                    $condition_type
                );

                if (!$insert_stmt->execute()) {
                    $message = "Failed to create listing.";

                } else {

                    $listing_id = $conn->insert_id;

                    $photo_stmt = $conn->prepare(
                        "INSERT INTO listing_photo
                        (listing_id, photo_url)
                        VALUES (?, ?)"
                    );

                    $failed = false;

                    foreach ($photos as $photo) {

                        $detected_type = mime_content_type($photo['tmp_name']);
                        $extension = $allowed_types[$detected_type];

                        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

                        $photo_path = $upload_directory . $filename;
                        $photo_url = 'uploads/listings/' . $filename;

                        if (!move_uploaded_file($photo['tmp_name'], $photo_path)) {
                            $failed = true;
                            break;
                        }

                        $saved_files[] = $photo_path;

                        $photo_stmt->bind_param(
                            "is",
                            $listing_id,
                            $photo_url
                        );

                        if (!$photo_stmt->execute()) {
                            $failed = true;
                            break;
                        }
                    }

                    if ($failed) {

                        foreach ($saved_files as $saved_file) {
                            if (is_file($saved_file)) {
                                unlink($saved_file);
                            }
                        }

                        $delete_stmt = $conn->prepare(
                            "DELETE FROM listing
                             WHERE listing_id = ? AND seller_id = ?"
                        );

                        $delete_stmt->bind_param(
                            "ii",
                            $listing_id,
                            $seller_id
                        );

                        $delete_stmt->execute();

                        $message = "Listing creation failed while saving photos.";

                    } else {

                        header(
                            "Location: listing_details.php?id="
                            . $listing_id
                            . "&created=1"
                        );

                        exit;
                    }
                }
            }
        }
    }
}
$category_sql = "SELECT category_id, name FROM category ORDER BY name";
$category_result = $conn->query($category_sql);

$page_title = 'Create Listing';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Create New Listing</h1>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card" style="max-width:560px;">
    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        <div class="field">
            <label>Title</label>
            <input type="text" name="title" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
        </div>

        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
        </div>

        <div class="field-row">
            <div class="field">
                <label>Price (TZS)</label>
                <input type="number" name="price" step="0.01" min="0" required value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
            </div>
            <div class="field">
                <label>Condition</label>
                <select name="condition_type" required>
                    <option value="used">Used</option>
                    <option value="new">New</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label>Category</label>
            <select name="category_id" required>
                <option value="">-- Select Category --</option>
                <?php while ($category = $category_result->fetch_assoc()): ?>
                    <option value="<?php echo $category['category_id']; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="field">
            <label>Product Photo</label>
            <input
                type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required
            >
            <small class="muted">
                You can upload up to 8 photos, maximum 5 MB each.
            </small>

        </div>

        <button type="submit" class="btn btn-primary btn-block">Create Listing</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>