<?php
// ================================================================
//  PW PORTAL v3 — CONFIG
// ================================================================
define('PW_API',           'https://api.penpencil.co');
define('PW_CLIENT_ID',     '5eb393ee95fab7468a79d189');
define('PW_CLIENT_SECRET', 'KjPXuAVfC5xbmgreETNMaL7z');
define('PW_ORG_ID',        '5eb393ee95fab7468a79d189');

// Admin credentials — change here
define('ADMIN_USER',      'raj');
define('ADMIN_PASS_HASH', hash('sha256', 'rajtoken'));

// Storage
define('USERS_FILE', __DIR__ . '/../data/users.json');
define('SESSION_TTL', 86400); // 24 hours

// ── WEB headers (OTP + token fetch)
function pw_web_headers(): array {
    return [
        'Content-Type: application/json',
        'Client-Id: ' . PW_CLIENT_ID,
        'Client-Type: WEB',
        'Client-Version: 2.6.12',
        'Integration-With: Origin',
        'Randomid: ' . bin2hex(random_bytes(16)),
        'Referer: https://www.pw.live/',
        'Sec-Ch-Ua: "Not A(Brand";v="99", "Microsoft Edge";v="121", "Chromium";v="121"',
        'Sec-Ch-Ua-Mobile: ?0',
        'Sec-Ch-Ua-Platform: "Windows"',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
    ];
}

// ── MOBILE headers (batches, subjects, contents) — from Python file
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
