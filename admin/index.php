<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

session_boot();

if (is_admin_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Constant-time comparison to prevent timing attacks
    $userOk = hash_equals(ADMIN_USERNAME, $user);
    $passOk = hash_equals(ADMIN_PASSWORD_HASH, hash('sha256', $pass));

    if ($userOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        header('Location: /admin/dashboard.php');
        exit;
    } else {
        // Delay to slow brute force
        sleep(1);
        $error = 'Invalid credentials. Access denied.';
    }
}

$showErr = (int)($_GET['err'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin — Secure Access</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#06060e;--card:#0c0c18;--border:#1a1a28;--accent:#f97316;--text:#e8e8ff;--muted:#555570;--danger:#ef4444;--radius:12px}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;overflow:hidden}

/* Scanlines effect */
body::after{content:'';position:fixed;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.08) 2px,rgba(0,0,0,.08) 4px);pointer-events:none;z-index:0}

/* Grid */
.grid{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(249,115,22,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(249,115,22,.04) 1px,transparent 1px);background-size:40px 40px}

/* Corners glow */
.corner{position:fixed;width:300px;height:300px;pointer-events:none;z-index:0}
.corner-tl{top:0;left:0;background:radial-gradient(circle at 0 0,rgba(249,115,22,.15),transparent 70%)}
.corner-br{bottom:0;right:0;background:radial-gradient(circle at 100% 100%,rgba(124,58,237,.15),transparent 70%)}

/* Card */
.card{position:relative;z-index:1;width:100%;max-width:400px;background:rgba(12,12,24,.95);border:1px solid var(--border);border-radius:16px;padding:40px;box-shadow:0 0 0 1px rgba(249,115,22,.05) inset,0 40px 80px rgba(0,0,0,.7)}

/* Top badge */
.admin-badge{display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:32px}
.badge-icon{width:48px;height:48px;background:linear-gradient(135deg,rgba(249,115,22,.2),rgba(239,68,68,.2));border:1px solid rgba(249,115,22,.3);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px}
.badge-text{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:var(--muted);letter-spacing:.15em;text-transform:uppercase}

.card-title{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;text-align:center;margin-bottom:4px}
.card-sub{font-size:13px;color:var(--muted);text-align:center;margin-bottom:28px;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:.05em}

/* Terminal cursor blink */
.cursor{display:inline-block;width:8px;height:14px;background:var(--accent);animation:blink 1s step-end infinite;vertical-align:text-bottom;margin-left:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}

/* Form */
label{display:block;font-family:'DM Mono',monospace;font-size:11px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px}
.input-wrap{position:relative;margin-bottom:16px}
input{width:100%;padding:12px 16px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'DM Mono',monospace;font-size:14px;outline:none;transition:all .2s}
input:focus{border-color:var(--accent);background:rgba(249,115,22,.06);box-shadow:0 0 0 3px rgba(249,115,22,.12)}
input::placeholder{color:var(--muted)}

.btn{width:100%;padding:13px;background:linear-gradient(135deg,#f97316,#ea580c);border:none;border-radius:8px;color:#fff;font-family:'Syne',sans-serif;font-size:14px;font-weight:700;letter-spacing:.06em;cursor:pointer;transition:all .25s;margin-top:8px}
.btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(249,115,22,.45)}
.btn:active{transform:translateY(0)}

.alert-error{padding:12px 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#fca5a5;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px}

/* Security notice */
.security-bar{margin-top:20px;padding:10px 14px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;font-family:'DM Mono',monospace;font-size:10px;color:var(--muted);display:flex;align-items:center;gap:8px}
.sec-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;flex-shrink:0;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

.spinner{width:16px;height:16px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block;vertical-align:middle;margin-right:8px}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="grid"></div>
<div class="corner corner-tl"></div>
<div class="corner corner-br"></div>

<div class="card">
  <div class="admin-badge">
    <div class="badge-icon">🔐</div>
    <span class="badge-text">RESTRICTED</span>
  </div>

  <h1 class="card-title">Admin Access</h1>
  <div class="card-sub">SECURE PANEL / PW PORTAL<span class="cursor"></span></div>

  <?php if ($error): ?>
    <div class="alert-error">⛔ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($showErr): ?>
    <div class="alert-error">⛔ Session expired. Please login again.</div>
  <?php endif; ?>

  <form method="POST" onsubmit="showLoader(this)">
    <div class="input-wrap">
      <label>Username</label>
      <input type="text" name="username" placeholder="admin" required autocomplete="username"/>
    </div>
    <div class="input-wrap">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••••••" required autocomplete="current-password"/>
    </div>
    <button type="submit" class="btn">ACCESS PANEL →</button>
  </form>

  <div class="security-bar">
    <div class="sec-dot"></div>
    All activity on this panel is logged. Unauthorized access is prohibited.
  </div>
</div>

<script>
function showLoader(f){
  const btn = f.querySelector('button[type=submit]');
  btn.innerHTML = '<span class="spinner"></span>Authenticating...';
  btn.disabled = true;
}
// Security: disable right-click and devtools shortcuts on this page only
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key)) || (e.ctrlKey && e.key === 'U')) {
    e.preventDefault();
  }
});
</script>
</body>
</html>
