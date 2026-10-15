<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

function set_auth_session($user)
{
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['campus_email'] = $user['campus_email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['verification_status'] = $user['verification_status'];
}

function restore_remembered_session($conn)
{
    if (isset($_SESSION['user_id']) || empty($_COOKIE['unitrade_remember'])) {
        return;
    }

    $parts = explode(':', $_COOKIE['unitrade_remember'], 2);
    if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
        return;
    }

    $selector = $parts[0];
    $token = $parts[1];
    $stmt = $conn->prepare("SELECT rt.user_id, rt.token_hash, u.full_name, u.campus_email, u.role, u.verification_status FROM remember_tokens rt JOIN user u ON u.user_id = rt.user_id WHERE rt.selector = ? AND rt.expires_at > NOW() LIMIT 1");
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $token))) {
        setcookie('unitrade_remember', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
        return;
    }

    set_auth_session($row);
    session_regenerate_id(true);
}

restore_remembered_session($conn);

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function get_current_user_data($conn)
{
    require_login();

    $user_id = (int) $_SESSION['user_id'];

    $sql = "SELECT
                user_id,
                full_name,
                campus_email,
                verification_status,
                role,
                college_id
            FROM user
            WHERE user_id = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        http_response_code(500 );
        exit("Database query failed.");
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        http_response_code(403 );
        exit("User account not found.");
    }

    return $result->fetch_assoc();
}

function require_verified_user($conn)
{
    $user = get_current_user_data($conn);

    if ($user['verification_status'] !== 'verified') {
        http_response_code(403 );
        exit("Your account must be verified to perform this action.");
    }

    return $user;
}

function require_admin($conn)
{
    $user = get_current_user_data($conn);

    if ($user['role'] !== 'admin') {
        http_response_code(403 );
        exit("Administrator access required.");
    }

    return $user;
}

// Admin accounts are moderators only, not marketplace participants: they
// don't sell, buy, or message. This blocks the underlying pages even if
// someone reaches them directly by URL, not just hides the nav links.
function require_non_admin($user)
{
    if ($user['role'] === 'admin') {
        http_response_code(403);
        exit("Administrator accounts can't buy, sell, or message on UniTrade. Log in with a student account to use this feature.");
    }

    return $user;
}

// --- CSRF helpers -----------------------------------------------------
// Basic per-session CSRF token used on state-changing forms (register,
// login, password change, listing create/edit/delete, admin actions, etc).
function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $submitted = $_POST['csrf_token'] ?? '';

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        exit("Your session has expired or the request could not be verified. Please go back and try again.");
    }
}

// AJAX endpoints can't submit a hidden form field, so they send the
// token as a header instead (set by assets/js/*.js on every fetch()
// call). Same check, same session token, just a different source.
function csrf_verify_ajax()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(["error" => "Your session has expired. Please refresh the page."]);
        exit;
    }
}