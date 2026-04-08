<?php
require_once __DIR__ . '/config.php';

// ── Generic cURL ─────────────────────────────────────────────
function pw_curl(string $url, array $headers, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($cerr) {
        return ['ok' => false, 'code' => 0, 'data' => null, 'raw' => $cerr];
    }
    $json = json_decode($raw, true);
    return ['ok' => ($code >= 200 && $code < 300), 'code' => $code, 'data' => $json, 'raw' => $raw];
}

// ─────────────────────────────────────────────────────────────
//  1. SEND OTP
//  PW API returns 200 OR 201 for success.
//  We treat any 2xx as success.
// ─────────────────────────────────────────────────────────────
function api_send_otp(string $phone): array {
    $url = PW_API . '/v1/users/get-otp?smsType=0';
    $res = pw_curl($url, pw_web_headers(), [
        'username'       => $phone,
        'countryCode'    => '+91',
        'organizationId' => PW_ORG_ID,
    ]);
    // Success = any 2xx HTTP code (200, 201, 202…)
    $success = ($res['code'] >= 200 && $res['code'] < 300);
    return [
        'success' => $success,
        'code'    => $res['code'],
        'body'    => $res['data'],
    ];
}

// ─────────────────────────────────────────────────────────────
//  2. VERIFY OTP & GET TOKEN
//  From Python: get_token() — POST /v3/oauth/token
//  Response: data.access_token, data.refresh_token
// ─────────────────────────────────────────────────────────────
function api_verify_otp(string $phone, string $otp): array {
    $url = PW_API . '/v3/oauth/token';
    $res = pw_curl($url, pw_web_headers(), [
        'username'       => $phone,
        'otp'            => $otp,
        'client_id'      => 'system-admin',
        'client_secret'  => PW_CLIENT_SECRET,
        'grant_type'     => 'password',
        'organizationId' => PW_ORG_ID,
        'latitude'       => 0,
        'longitude'      => 0,
    ]);

    $body  = $res['data'] ?? [];
    $aToken = $body['data']['access_token']  ?? '';
    $rToken = $body['data']['refresh_token'] ?? '';
    return [
        'success'       => ($res['code'] >= 200 && $res['code'] < 300 && !empty($aToken)),
        'access_token'  => $aToken,
        'refresh_token' => $rToken,
        'raw_body'      => $body,
        'code'          => $res['code'],
    ];
}

// ─────────────────────────────────────────────────────────────
//  3. USER PROFILE
// ─────────────────────────────────────────────────────────────
function api_get_profile(string $token): array {
    $res = pw_curl(PW_API . '/v1/users/me', pw_mobile_headers($token));
    return [
        'success' => $res['ok'],
        'data'    => $res['data']['data'] ?? [],
    ];
}

// ─────────────────────────────────────────────────────────────
//  4. MY BATCHES — from Python: pw_login()
//  GET /v3/batches/my-batches with exact params from Python file
// ─────────────────────────────────────────────────────────────
function api_get_batches(string $token, int $page = 1): array {
    $q = http_build_query([
        'mode'           => '1',
        'filter'         => 'false',
        'exam'           => '',
        'amount'         => '',
        'organisationId' => PW_ORG_ID,
        'classes'        => '',
        'limit'          => '20',
        'page'           => (string)$page,
        'programId'      => '',
        'ut'             => '1652675230446',
    ]);
    $res = pw_curl(PW_API . '/v3/batches/my-batches?' . $q, pw_mobile_headers($token));
    return [
        'success' => $res['ok'],
        'data'    => $res['data']['data'] ?? [],
        'code'    => $res['code'],
    ];
}

// ─────────────────────────────────────────────────────────────
//  5. BATCH DETAILS (subjects list)
//  GET /v3/batches/{batchId}/details
// ─────────────────────────────────────────────────────────────
function api_get_batch_details(string $token, string $batchId): array {
    $res = pw_curl(
        PW_API . '/v3/batches/' . urlencode($batchId) . '/details',
        pw_mobile_headers($token)
    );
    return [
        'success'  => $res['ok'],
        'data'     => $res['data']['data'] ?? [],
        'subjects' => $res['data']['data']['subjects'] ?? [],
    ];
}

// ─────────────────────────────────────────────────────────────
//  6. SUBJECT CONTENTS (videos / notes / DPP)
//  From Python commented section:
//  GET /v3/batches/{batchId}/subject/{subjectId}/contents
// ─────────────────────────────────────────────────────────────
function api_get_contents(string $token, string $batchId, string $subjectId, string $type = 'videos', int $page = 1): array {
    $q   = http_build_query(['page' => (string)$page, 'tag' => '', 'contentType' => $type]);
    $url = PW_API . '/v3/batches/' . urlencode($batchId) . '/subject/' . urlencode($subjectId) . '/contents?' . $q;
    $res = pw_curl($url, pw_mobile_headers($token));
    return [
        'success' => $res['ok'],
        'data'    => $res['data']['data'] ?? [],
    ];
}

// ─────────────────────────────────────────────────────────────
//  7. VALIDATE TOKEN — try fetching batches
// ─────────────────────────────────────────────────────────────
function api_validate_token(string $token): bool {
    $r = api_get_batches($token, 1);
    return $r['success'];
}

// ═════════════════════════════════════════════════════════════
//  USER STORAGE
// ═════════════════════════════════════════════════════════════
function users_load(): array {
    if (!file_exists(USERS_FILE)) return [];
    $d = json_decode(file_get_contents(USERS_FILE), true);
    return is_array($d) ? $d : [];
}

function users_save(array $data): void {
    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(USERS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Generate a short unique ID for the user
function make_uid(string $phone): string {
    return 'USR' . strtoupper(substr(md5($phone . PW_ORG_ID), 0, 8));
}

function user_upsert(string $phone, string $accessToken, string $refreshToken = '', string $name = '', string $email = '', array $batchData = []): string {
    $users  = users_load();
    $uid    = $users[$phone]['uid'] ?? make_uid($phone);
    $prev   = $users[$phone] ?? [];

    // Token history (keep last 10)
    $history = $prev['token_history'] ?? [];
    array_unshift($history, [
        'token'   => $accessToken,
        'time'    => date('Y-m-d H:i:s'),
        'batches' => count($batchData),
    ]);
    $history = array_slice($history, 0, 10);

    $users[$phone] = [
        'uid'           => $uid,
        'phone'         => $phone,
        'name'          => $name ?: ($prev['name'] ?? ''),
        'email'         => $email ?: ($prev['email'] ?? ''),
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken ?: ($prev['refresh_token'] ?? ''),
        'token_history' => $history,
        'token_count'   => count($history),
        'login_count'   => (($prev['login_count'] ?? 0) + 1),
        'batch_count'   => count($batchData) ?: ($prev['batch_count'] ?? 0),
        'batches'       => $batchData ?: ($prev['batches'] ?? []),
        'last_login'    => date('Y-m-d H:i:s'),
        'first_login'   => $prev['first_login'] ?? date('Y-m-d H:i:s'),
    ];
    users_save($users);
    return $uid;
}

function user_get_by_phone(string $phone): ?array {
    $users = users_load();
    return $users[$phone] ?? null;
}

function user_get_by_uid(string $uid): ?array {
    $users = users_load();
    foreach ($users as $u) {
        if (($u['uid'] ?? '') === $uid) return $u;
    }
    return null;
}

function user_delete(string $phone): void {
    $users = users_load();
    unset($users[$phone]);
    users_save($users);
}
