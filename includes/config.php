<?php
// ============================================================
//  PW WEBSITE - SECURE CONFIGURATION
//  This file should NEVER be served directly to browser.
//  Place it above public root in production.
// ============================================================

define('PW_API_BASE',        'https://api.penpencil.co');
define('PW_CLIENT_ID',       '5eb393ee95fab7468a79d189');
define('PW_CLIENT_SECRET',   'KjPXuAVfC5xbmgreETNMaL7z');
define('PW_ORG_ID',          '5eb393ee95fab7468a79d189');
define('PW_CLIENT_VERSION',  '2.6.12');
define('PW_GRANT_TYPE',      'password');

// -------  ADMIN CREDENTIALS  (change these!) -------
define('ADMIN_USERNAME', 'admin');
// Stored as SHA-256 hash — never plain text in code
define('ADMIN_PASSWORD_HASH', hash('sha256', 'Admin@PW#2024'));   // change 'Admin@PW#2024'

// -------  SESSION / SECURITY  -------
define('SESSION_LIFETIME', 3600);   // 1 hour
define('ADMIN_SESSION_LIFETIME', 1800);

// -------  USER DATA STORAGE  -------
// Users are stored in a JSON file (no DB required)
define('USERS_FILE', __DIR__ . '/../data/users.json');

// -------  PW API HEADERS (common)  -------
function pw_base_headers(): array {
    return [
        'Content-Type: application/json',
        'Client-Id: ' . PW_CLIENT_ID,
        'Client-Type: WEB',
        'Client-Version: ' . PW_CLIENT_VERSION,
        'Integration-With: Origin',
        'Randomid: ' . bin2hex(random_bytes(8)),
        'Referer: https://www.pw.live/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
    ];
}

function pw_auth_headers(string $token): array {
    return [
        'Content-Type: application/json; charset=UTF-8',
        'authorization: Bearer ' . $token,
        'client-id: ' . PW_CLIENT_ID,
        'client-version: 12.84',
        'user-agent: Android',
        'randomid: ' . bin2hex(random_bytes(8)),
        'client-type: MOBILE',
        'device-meta: {APP_VERSION:12.84,DEVICE_MAKE:Asus,DEVICE_MODEL:ASUS_X00TD,OS_VERSION:6,PACKAGE_NAME:xyz.penpencil.physicswalb}',
    ];
}
