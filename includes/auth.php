<?php
require_once __DIR__ . '/config.php';

function session_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,   // set true on HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    session_boot();
    return !empty($_SESSION['user_token']) && !empty($_SESSION['user_phone']);
}

function is_admin_logged_in(): bool {
    session_boot();
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /index.php?msg=Please+login+first');
        exit;
    }
}

function require_admin(): void {
    if (!is_admin_logged_in()) {
        header('Location: /admin/index.php?err=1');
        exit;
    }
}

function login_user(string $phone, string $token, string $name = ''): void {
    session_boot();
    session_regenerate_id(true);
    $_SESSION['user_token'] = $token;
    $_SESSION['user_phone'] = $phone;
    $_SESSION['user_name']  = $name;
    $_SESSION['login_time'] = time();
}

function logout_user(): void {
    session_boot();
    $_SESSION = [];
    session_destroy();
}
