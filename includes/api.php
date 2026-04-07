<?php
require_once __DIR__ . '/config.php';

// ── Generic cURL ─────────────────────────────────────────────
function pw_curl(string $url, array $headers, ?array $post = null, string $method = 'GET'): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'code' => 0, 'data' => null, 'raw' => $err];

    $json = json_decode($raw, true);
    return ['ok' => ($code >= 200 && $code < 300), 'code' => $code, 'data' => $json, 'raw' => $raw];
}

// ── 1. SEND OTP ───────────────────────────────────────────────
// From Python: get_otp() — POST /v1/users/get-otp?smsType=0
function api_send_otp(string $phone): array {
    $url  = PW_API . '/v1/users/get-otp?smsType=0';
    $res  = pw_curl($url, pw_web_headers(), [
        'username'       => $phone,
        'countryCode'    => '+91',
        'organizationId' => PW_ORG_ID,
    ], 'POST');

    // PW API returns 200 even on success with various status fields.
    // We consider it a success if HTTP 200 AND no error key with false
    $body = $res['data'] ?? [];
    $success = $res['code'] === 200 && empty($body['error']) && ($body['success'] ?? true) !== false;
    return ['success' => $success, 'code' => $res['code'], 'body' => $body, 'raw' => $res['raw']];
}

// ── 2. VERIFY OTP & GET TOKEN ─────────────────────────────────
// From Python: get_token() — POST /v3/oauth/token
// Returns: access_token + refresh_token
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
    ], 'POST');

    $body         = $res['data'] ?? [];
    $accessToken  = $body['data']['access_token']  ?? '';
    $refreshToken = $body['data']['refresh_token'] ?? '';
    $success      = $res['code'] === 200 && !empty($accessToken);
    return [
        'success'       => $success,
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken,
        'body'          => $body,
        'code'          => $res['code'],
    ];
}

// ── 3. GET USER PROFILE ───────────────────────────────────────
function api_get_profile(string $token): array {
    $res  = pw_curl(PW_API . '/v1/users/me', pw_mobile_headers($token));
    $data = $res['data']['data'] ?? [];
    return ['success' => $res['ok'], 'data' => $data];
}

// ── 4. MY BATCHES ─────────────────────────────────────────────
// From Python: pw_login() — GET /v3/batches/my-batches
function api_get_batches(string $token, int $page = 1): array {
    $params = http_build_query([
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
    $res = pw_curl(PW_API . '/v3/batches/my-batches?' . $params, pw_mobile_headers($token));
    return ['success' => $res['ok'], 'data' => $res['data']['data'] ?? [], 'code' => $res['code']];
}

// ── 5. BATCH DETAILS (subjects) ───────────────────────────────
// From Python: /v3/batches/{batchId}/details
function api_get_batch_details(string $token, string $batchId): array {
    $res = pw_curl(PW_API . '/v3/batches/' . urlencode($batchId) . '/details', pw_mobile_headers($token));
    return ['success' => $res['ok'], 'data' => $res['data']['data'] ?? []];
}

// ── 6. SUBJECT TOPICS ─────────────────────────────────────────
// From Python: /v3/batches/{batchId}/subject/{subjectId}/topics
function api_get_topics(string $token, string $batchId, string $subjectId, int $page = 1): array {
    $params = http_build_query(['page' => (string)$page]);
    $url    = PW_API . '/v3/batches/' . urlencode($batchId) . '/subject/' . urlencode($subjectId) . '/topics?' . $params;
    $res    = pw_curl($url, pw_mobile_headers($token));
    return ['success' => $res['ok'], 'data' => $res['data']['data'] ?? []];
}

// ── 7. SUBJECT CONTENTS (videos, notes, DPP) ─────────────────
// From Python commented section: /v3/batches/{batchId}/subject/{subjectId}/contents
function api_get_contents(string $token, string $batchId, string $subjectId, string $type = 'videos', int $page = 1): array {
    $params = http_build_query(['page' => (string)$page, 'tag' => '', 'contentType' => $type]);
    $url    = PW_API . '/v3/batches/' . urlencode($batchId) . '/subject/' . urlencode($subjectId) . '/contents?' . $params;
    $res    = pw_curl($url, pw_mobile_headers($token));
    return ['success' => $res['ok'], 'data' => $res['data']['data'] ?? []];
}

// ── 8. VALIDATE TOKEN (check if token works) ─────────────────
function api_validate_token(string $token): bool {
    $r = api_get_batches($token, 1);
    return $r['success'];
}

// ════════════════════════════════════════════════════════════
//  USER STORAGE (JSON file)
// ════════════════════════════════════════════════════════════

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

function user_upsert(string $phone, string $accessToken, string $refreshToken = '', string $name = '', string $email = ''): void {
    $users  = users_load();
    $prev   = $users[$phone] ?? [];
    $tokens = $prev['tokens'] ?? [];

    // Store token history (latest first, max 10)
    array_unshift($tokens, [
        'token'      => $accessToken,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $tokens = array_slice($tokens, 0, 10);

    $users[$phone] = [
        'phone'         => $phone,
        'name'          => $name  ?: ($prev['name']  ?? ''),
        'email'         => $email ?: ($prev['email'] ?? ''),
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken ?: ($prev['refresh_token'] ?? ''),
        'tokens'        => $tokens,
        'token_count'   => count($tokens),
        'login_count'   => (($prev['login_count'] ?? 0) + 1),
        'last_login'    => date('Y-m-d H:i:s'),
        'first_login'   => $prev['first_login'] ?? date('Y-m-d H:i:s'),
        'batch_count'   => $prev['batch_count'] ?? 0,
    ];
    users_save($users);
}

function user_update_batches(string $phone, int $count): void {
    $users = users_load();
    if (isset($users[$phone])) {
        $users[$phone]['batch_count'] = $count;
        users_save($users);
    }
}

function user_get(string $phone): ?array {
    $users = users_load();
    return $users[$phone] ?? null;
}

function user_delete(string $phone): void {
    $users = users_load();
    unset($users[$phone]);
    users_save($users);
}
