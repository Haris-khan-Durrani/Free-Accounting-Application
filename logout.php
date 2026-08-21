<?php
require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    global $pdo;
    if ($pdo) {
        try {
            $st = $pdo->prepare("UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id = ?");
            $st->execute([(int)$_SESSION['user_id']]);
        } catch (\Throwable $t) {}
    }
}

session_unset();
session_destroy();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

redirect('login.php');
