<?php
// ============================================================
//  PW PORTAL v2 — CONFIGURATION
//  Keep this file ABOVE web root or protected via .htaccess
// ============================================================

// ── PW API ──────────────────────────────────────────────────
define('PW_API',            'https://api.penpencil.co');
define('PW_CLIENT_ID',      '5eb393ee95fab7468a79d189');
define('PW_CLIENT_SECRET',  'KjPXuAVfC5xbmgreETNMaL7z');
define('PW_ORG_ID',         '5eb393ee95fab7468a79d189');

// ── ADMIN CREDENTIALS ───────────────────────────────────────
// Change 'Admin@PW#Secure2024' to your own password
define('ADMIN_USER',        'admin');
define('ADMIN_PASS_HASH',   hash('sha256', 'Admin@PW#Secure2024'));

// ── STORAGE ─────────────────────────────────────────────────
define('USERS_FILE',   __DIR__ . '/../data/users.json');
define('SESSION_TTL',  7200);   // 2 hours user session
define('ADMIN_TTL',    3600);   // 1 hour admin session

// ── WEB HEADERS (OTP + Token request) ───────────────────────
function pw_web_headers(): array {
    return [
        'Content-Type: application/json',
        'Client-Id: ' . PW_CLIENT_ID,
        'Client-Type: WEB',
        'Client-Version: 2.6.12',
        'Integration-With: Origin',
        'Randomid: ' . sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)),
        'Referer: https://www.pw.live/',
        'Sec-Ch-Ua: "Not A(Brand";v="99", "Microsoft Edge";v="121", "Chromium";v="121"',
        'Sec-Ch-Ua-Mobile: ?0',
        'Sec-Ch-Ua-Platform: "Windows"',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
    ];
}

// ── MOBILE HEADERS (Batches, Subjects, Topics, Contents) ────
// Exact replica from Python file pw__2_.py
function pw_mobile_headers(string $token): array {
    return [
        'Host: api.penpencil.co',
        'authorization: Bearer ' . $token,
        'client-id: ' . PW_CLIENT_ID,
        'client-version: 12.84',
        'user-agent: Android',
        'randomid: ' . bin2hex(random_bytes(8)),
        'client-type: MOBILE',
        'device-meta: {APP_VERSION:12.84,DEVICE_MAKE:Asus,DEVICE_MODEL:ASUS_X00TD,OS_VERSION:6,PACKAGE_NAME:xyz.penpencil.physicswalb}',
        'content-type: application/json; charset=UTF-8',
    ];
}
