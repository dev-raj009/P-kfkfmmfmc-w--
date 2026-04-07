<?php
require_once __DIR__ . '/config.php';

// ── Generic cURL helper ──────────────────────────────────────
function pw_curl(string $url, array $headers, ?array $postData = null, string $method = 'GET'): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($body, true);
    return ['code' => $httpCode, 'body' => $decoded, 'raw' => $body];
}

// ── Send OTP ─────────────────────────────────────────────────
function pw_send_otp(string $phone): array {
    $url     = PW_API_BASE . '/v1/users/get-otp?smsType=0';
    $headers = pw_base_headers();
    $payload = [
        'username'       => $phone,
        'countryCode'    => '+91',
        'organizationId' => PW_ORG_ID,
    ];
    return pw_curl($url, $headers, $payload, 'POST');
}

// ── Verify OTP & get token ────────────────────────────────────
function pw_verify_otp(string $phone, string $otp): array {
    $url     = PW_API_BASE . '/v3/oauth/token';
    $headers = pw_base_headers();
    $payload = [
        'username'       => $phone,
        'otp'            => $otp,
        'client_id'      => 'system-admin',
        'client_secret'  => PW_CLIENT_SECRET,
        'grant_type'     => PW_GRANT_TYPE,
        'organizationId' => PW_ORG_ID,
        'latitude'       => 0,
        'longitude'      => 0,
    ];
    return pw_curl($url, $headers, $payload, 'POST');
}

// ── Validate raw token by calling my-batches ─────────────────
function pw_validate_token(string $token): array {
    $url     = PW_API_BASE . '/v3/batches/my-batches?mode=1&filter=false&exam=&amount=&organisationId=' . PW_ORG_ID . '&classes=&limit=1&page=1&programId=&ut=1652675230446';
    $headers = pw_auth_headers($token);
    return pw_curl($url, $headers);
}

// ── Fetch user profile ────────────────────────────────────────
function pw_get_profile(string $token): array {
    $url     = PW_API_BASE . '/v1/users/me';
    $headers = pw_auth_headers($token);
    return pw_curl($url, $headers);
}

// ── Fetch all purchased batches ───────────────────────────────
function pw_get_batches(string $token, int $page = 1): array {
    $url     = PW_API_BASE . '/v3/batches/my-batches?mode=1&filter=false&exam=&amount=&organisationId=' . PW_ORG_ID . '&classes=&limit=20&page=' . $page . '&programId=&ut=1652675230446';
    $headers = pw_auth_headers($token);
    return pw_curl($url, $headers);
}

// ── Fetch batch details ───────────────────────────────────────
function pw_get_batch_details(string $token, string $batchId): array {
    $url     = PW_API_BASE . '/v3/batches/' . $batchId . '/details';
    $headers = pw_auth_headers($token);
    return pw_curl($url, $headers);
}

// ─────────────────────────────────────────────────────────────
//  USER STORAGE  (JSON file — no DB needed)
// ─────────────────────────────────────────────────────────────
function users_load(): array {
    $file = USERS_FILE;
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function users_save(array $data): void {
    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function user_upsert(string $phone, string $accessToken, string $refreshToken = '', string $name = '', array $extra = []): void {
    $users = users_load();
    $users[$phone] = array_merge($users[$phone] ?? [], [
        'phone'         => $phone,
        'name'          => $name ?: ($users[$phone]['name'] ?? ''),
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken ?: ($users[$phone]['refresh_token'] ?? ''),
        'last_login'    => date('Y-m-d H:i:s'),
        'login_count'   => (($users[$phone]['login_count'] ?? 0) + 1),
        'extra'         => $extra,
    ]);
    users_save($users);
}

function user_get(string $phone): ?array {
    $users = users_load();
    return $users[$phone] ?? null;
}
