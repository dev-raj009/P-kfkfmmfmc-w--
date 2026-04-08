<?php
require_once __DIR__ . '/config.php';

function session_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['lifetime' => SESSION_TTL, 'path' => '/', 'httponly' => true]);
        session_start();
    }
}

function is_logged_in(): bool {
    session_boot();
    return !empty($_SESSION['u_token']) && !empty($_SESSION['u_phone']);
}

function is_admin(): bool {
    session_boot();
    return !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function require_login(): void {
    if (!is_logged_in()) { header('Location: /index.php'); exit; }
}

function require_admin(): void {
    if (!is_admin()) { header('Location: /admin/index.php?e=1'); exit; }
}

function do_login(string $phone, string $token, string $name = ''): void {
    session_boot();
    session_regenerate_id(true);
    $_SESSION['u_phone'] = $phone;
    $_SESSION['u_token'] = $token;
    $_SESSION['u_name']  = $name;
    $_SESSION['u_at']    = time();
}

function do_logout(): void {
    session_boot();
    $_SESSION = [];
    session_destroy();
}

function do_admin_login(): void {
    session_boot();
    session_regenerate_id(true);
    $_SESSION['admin']    = true;
    $_SESSION['admin_at'] = time();
}

function do_admin_logout(): void {
    session_boot();
    unset($_SESSION['admin'], $_SESSION['admin_at']);
    session_destroy();
}
