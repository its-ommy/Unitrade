<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'UniTrade';
$active_nav = $active_nav ?? '';

$is_logged_in = isset($_SESSION['user_id']);


if ($is_logged_in && isset($conn) && $conn instanceof mysqli) {
    $refresh_stmt = $conn->prepare(
        "SELECT verification_status, role
         FROM user
         WHERE user_id = ?"
    );

    $refresh_uid = (int) $_SESSION['user_id'];

    $refresh_stmt->bind_param(
        "i",
        $refresh_uid
    );

    $refresh_stmt->execute();

    $refresh_row = $refresh_stmt->get_result()->fetch_assoc();

    if ($refresh_row) {
        $_SESSION['verification_status'] = $refresh_row['verification_status'];
        $_SESSION['role'] = $refresh_row['role'];
    }
}

$is_admin = (
    $is_logged_in &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
);

$is_verified = (
    $is_logged_in &&
    isset($_SESSION['verification_status']) &&
    $_SESSION['verification_status'] === 'verified'
);


$unread_message_count = 0;
$pending_request_count = 0;

if (
    $is_logged_in &&
    $is_verified &&
    !$is_admin &&
    isset($conn) &&
    $conn instanceof mysqli
) {
    $nav_uid = (int) $_SESSION['user_id'];

    $msg_count_stmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM message
         WHERE receiver_id = ?
           AND is_read = 0"
    );

    $msg_count_stmt->bind_param(
        "i",
        $nav_uid
    );

    $msg_count_stmt->execute();

    $unread_message_count = (int) (
        $msg_count_stmt
            ->get_result()
            ->fetch_assoc()['c']
    );

    $req_count_stmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM purchase_request
         WHERE seller_id = ?
           AND status = 'pending'"
    );

    $req_count_stmt->bind_param(
        "i",
        $nav_uid
    );

    $req_count_stmt->execute();

    $pending_request_count = (int) (
        $req_count_stmt
            ->get_result()
            ->fetch_assoc()['c']
    );
}

$home_url = $is_logged_in
    ? 'dashboard.php'
    : 'index.php';

$container_class = !empty($narrow)
    ? 'container narrow'
    : 'container';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="<?php echo htmlspecialchars(csrf_token()); ?>"
    >

    <title>
        <?php echo htmlspecialchars($page_title); ?> - UniTrade
    </title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<nav class="navbar">
    <div class="navbar-inner">

        <a class="brand" href="<?php echo $home_url; ?>">
            <svg
                class="brand-icon"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <path
                    d="M12 3L1 8l11 5 9-4.09V17h2V8L12 3z"
                    fill="currentColor"
                />

                <path
                    d="M5 10.18V14c0 2.21 3.13 4 7 4s7-1.79 7-4v-3.82l-7 3.18-7-3.18z"
                    fill="currentColor"
                />
            </svg>

            <span class="brand-text">UniTrade</span>
        </a>

        <div class="nav-links">

            <?php if ($is_logged_in ): ?>

                <a
                    href="browse_listings.php"
                    class="<?php echo $active_nav === 'browse' ? 'active' : ''; ?>"
                >
                    Browse
                </a>

                <?php if (!$is_admin): ?>
                    <a
                        href="my_listings.php"
                        class="<?php echo $active_nav === 'my_listings' ? 'active' : ''; ?>"
                    >
                        My Listings
                    </a>
                <?php endif; ?>

                <?php if ($is_verified && !$is_admin): ?>

                    <a
                        href="messages.php"
                        class="<?php echo $active_nav === 'messages' ? 'active' : ''; ?>"
                    >
                        Messages

                        <span
                            class="nav-count"
                            data-notif="unread_messages"
                            style="<?php echo $unread_message_count > 0 ? '' : 'display:none;'; ?>"
                        >
                            <?php echo $unread_message_count; ?>
                        </span>
                    </a>

                    <a
                        href="requests.php"
                        class="<?php echo $active_nav === 'requests' ? 'active' : ''; ?>"
                    >
                        Requests

                        <span
                            class="nav-count"
                            data-notif="pending_requests"
                            style="<?php echo $pending_request_count > 0 ? '' : 'display:none;'; ?>"
                        >
                            <?php echo $pending_request_count; ?>
                        </span>
                    </a>

                <?php endif; ?>

                <a
                    href="profile.php"
                    class="<?php echo $active_nav === 'profile' ? 'active' : ''; ?>"
                >
                    Profile
                </a>

                <?php if ($is_admin): ?>
                    <a
                        href="admin_dashboard.php"
                        class="<?php echo $active_nav === 'admin' ? 'active' : ''; ?>"
                    >
                        Admin
                    </a>
                <?php endif; ?>

                <?php if ($is_verified && !$is_admin): ?>
                    <a
                        href="create_listing.php"
                        class="pill"
                    >
                        + Sell Item
                    </a>
                <?php endif; ?>

                <a href="logout.php">
                    Logout
                </a>

            <?php elseif (empty($minimal_nav)): ?>

                <a
                    href="login.php"
                    class="<?php echo $active_nav === 'login' ? 'active' : ''; ?>"
                >
                    Login
                </a>

                <a
                    href="Register.php"
                    class="pill <?php echo $active_nav === 'register' ? 'active' : ''; ?>"
                >
                    Register
                </a>

            <?php endif; ?>

        </div>
    </div>
</nav>

<div class="<?php echo $container_class; ?>">

<?php if ($is_verified && !$is_admin): ?>
    <script src="assets/notifications.js"></script>
<?php endif; ?>
