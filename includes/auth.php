<?php
require_once __DIR__ . '/config.php';

function session_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_TTL,
            'path'     => '/',
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

function is_admin(): bool {
    session_boot();
    return !empty($_SESSION['admin_ok']) && $_SESSION['admin_ok'] === true;
}

function require_login(string $redirect = '/index.php'): void {
    if (!is_logged_in()) { header('Location: ' . $redirect); exit; }
}

function require_admin(): void {
    if (!is_admin()) { header('Location: /admin/index.php?err=1'); exit; }
}

function login_user(string $phone, string $token, string $name = ''): void {
    session_boot();
    session_regenerate_id(true);
    $_SESSION['user_phone']  = $phone;
    $_SESSION['user_token']  = $token;
    $_SESSION['user_name']   = $name;
    $_SESSION['login_at']    = time();
}

function logout_user(): void {
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function login_admin(): void {
    session_boot();
    session_regenerate_id(true);
    $_SESSION['admin_ok'] = true;
    $_SESSION['admin_at'] = time();
}

function logout_admin(): void {
    session_boot();
    unset($_SESSION['admin_ok'], $_SESSION['admin_at']);
}
