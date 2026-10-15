<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

if (!empty($_COOKIE['unitrade_remember'])) {
    $parts = explode(':', $_COOKIE['unitrade_remember'], 2);
    if (count($parts) === 2) {
        $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->bind_param('s', $parts[0]);
        $stmt->execute();
    }
}

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie('unitrade_remember', '', time() - 3600, '/', '', $secure, true);

session_unset();
session_destroy();

header("Location: login.php");
exit;