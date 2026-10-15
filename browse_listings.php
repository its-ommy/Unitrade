<?php

require_once __DIR__ . '/auth.php';
require_login(); // browsing requires an account, but not verification (FR-05)
$viewer_id = (int) $_SESSION['user_id'];

$condition = isset($_GET['condition']) ? trim($_GET['condition']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$limit = 12;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$college_id = isset($_GET['college_id']) ? (int) $_GET['college_id'] : 0; // FR-09: filter by college
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : 0;

// NFR-09: reported listings pending review are hidden from public browsing
$sql = "SELECT
            l.listing_id,
            l.title,
            l.description,
            l.price,
            l.condition_type,
            l.posted_at,
            u.full_name AS seller_name,
            c.name AS category_name,
            (SELECT photo_url FROM listing_photo lp WHERE lp.listing_id = l.listing_id ORDER BY lp.photo_id ASC LIMIT 1) AS thumb
        FROM listing l
        JOIN user u ON l.seller_id = u.user_id
        JOIN category c ON l.category_id = c.category_id
        WHERE l.status = 'active'
        AND l.seller_id != ?
        AND l.title LIKE ?
        AND (? = 0 OR l.category_id = ?)
        AND (? = 0 OR l.college_id = ?)
        AND (? = 0 OR l.price >= ?)
        AND (? = 0 OR l.price <= ?)
        AND (? = '' OR l.condition_type = ?)
        AND NOT EXISTS (SELECT 1 FROM report r WHERE r.listing_id = l.listing_id AND r.status = 'pending')
        ORDER BY l.posted_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$search_term = "%" . $search . "%";

$stmt->bind_param(
    "isiiiiddddssii",
    $viewer_id,
    $search_term,
    $category_id, $category_id,
    $college_id, $college_id,
    $min_price, $min_price,
    $max_price, $max_price,
    $condition, $condition,
    $limit, $offset
);
$stmt->execute();
$result = $stmt->get_result();

$count_sql = "SELECT COUNT(*) AS total
              FROM listing l
              WHERE l.status = 'active'
              AND l.seller_id != ?
              AND l.title LIKE ?
              AND (? = 0 OR l.category_id = ?)
              AND (? = 0 OR l.college_id = ?)
              AND (? = 0 OR l.price >= ?)
              AND (? = 0 OR l.price <= ?)
              AND (? = '' OR l.condition_type = ?)
              AND NOT EXISTS (SELECT 1 FROM report r WHERE r.listing_id = l.listing_id AND r.status = 'pending')";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param(
    "isiiiiddddss",
    $viewer_id,
    $search_term,
    $category_id, $category_id,
    $college_id, $college_id,
    $min_price, $min_price,
    $max_price, $max_price,
    $condition, $condition
);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_listings = $count_result->fetch_assoc()['total'];
$total_pages = max(1, (int) ceil($total_listings / $limit));

// Categories & colleges for the filter dropdowns
$categories = $conn->query("SELECT category_id, name FROM category ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$colleges = $conn->query("SELECT college_id, name FROM college ORDER BY name")->fetch_all(MYSQLI_ASSOC);

function qs($overrides = [])
{
    $params = array_merge($_GET, $overrides);
    return htmlspecialchars('?' . http_build_query($params));
}

$page_title = 'Browse Listings';
$active_nav = 'browse';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Browse Listings</h1>
</div>

<div class="filter-bar">
    <form method="GET">
        <div class="filter-grid">
            <div class="field">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search by title..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="field">
                <label>Category</label>
                <select name="category_id">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_id === (int) $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>College</label>
                <select name="college_id">
                    <option value="0">All Colleges</option>
                    <?php foreach ($colleges as $col): ?>
                        <option value="<?php echo $col['college_id']; ?>" <?php echo $college_id === (int) $col['college_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($col['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Condition</label>
                <select name="condition">
                    <option value="">Any</option>
                    <option value="new" <?php echo $condition === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="used" <?php echo $condition === 'used' ? 'selected' : ''; ?>>Used</option>
                </select>
            </div>
            <div class="field">
                <label>Min Price</label>
                <input type="number" name="min_price" min="0" value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>">
            </div>
            <div class="field">
                <label>Max Price</label>
                <input type="number" name="max_price" min="0" value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>">
            </div>
            <div class="field">
                <button type="submit" class="btn btn-primary btn-block">Search</button>
            </div>
        </div>
    </form>
</div>

<?php if ($result->num_rows > 0): ?>
    <div class="listing-grid">
        <?php while ($listing = $result->fetch_assoc()): ?>
            <a class="listing-card" href="listing_details.php?id=<?php echo $listing['listing_id']; ?>">
                <div class="listing-thumb" style="<?php echo $listing['thumb'] ? "background-image:url('" . htmlspecialchars($listing['thumb']) . "')" : ''; ?>">
                    <?php echo $listing['thumb'] ? '' : 'No photo'; ?>
                </div>
                <div class="listing-body">
                    <p class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></p>
                    <p class="listing-price">TZS <?php echo number_format($listing['price'], 2); ?></p>
                    <p class="listing-meta">
                        <?php echo htmlspecialchars($listing['category_name']); ?> ·
                        <?php echo htmlspecialchars(ucfirst($listing['condition_type'])); ?>
                    </p>
                    <p class="listing-meta">Seller: <?php echo htmlspecialchars($listing['seller_name']); ?></p>
                </div>
            </a>
        <?php endwhile; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?php echo qs(['page' => $page - 1]); ?>">‹ Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo qs(['page' => $i]); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?php echo qs(['page' => $page + 1]); ?>">Next ›</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="empty-state card">
        <div class="icon">🔍</div>
        <p>No listings match your filters.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
