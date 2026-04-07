<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot();

if (is_logged_in()) { header('Location: /dashboard.php'); exit; }

$error   = '';
$success = '';
$step    = $_SESSION['login_step']  ?? 'start';
$phone   = $_SESSION['login_phone'] ?? '';

// ── Handle all POST actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── SEND OTP ─────────────────────────────────────────────
    if ($action === 'send_otp') {
        $raw   = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $phone = ltrim($raw, '0');
        if (strlen($phone) !== 10) {
            $error = 'Please enter a valid 10-digit mobile number.';
        } else {
            $r = api_send_otp($phone);
            // FIX: PW API returns 200 even for success. We check HTTP 200 only.
            // We do NOT fail if response body says anything weird — OTP IS sent.
            if ($r['code'] === 200) {
                $_SESSION['login_step']  = 'otp';
                $_SESSION['login_phone'] = $phone;
                $step    = 'otp';
                $success = 'OTP sent successfully to +91 ' . $phone;
            } else {
                $error = 'Could not reach PW server (HTTP ' . $r['code'] . '). Please try again.';
            }
        }
    }

    // ── VERIFY OTP ───────────────────────────────────────────
    if ($action === 'verify_otp') {
        $otp   = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        $phone = $_SESSION['login_phone'] ?? '';
        if (!$phone || strlen($otp) < 4) {
            $error = 'Invalid OTP or session expired.';
            $step  = 'start';
        } else {
            $r = api_verify_otp($phone, $otp);
            if ($r['success']) {
                $token = $r['access_token'];
                $rTok  = $r['refresh_token'];
                // Get profile for name/email
                $prof  = api_get_profile($token);
                $name  = $prof['data']['name']  ?? '';
                $email = $prof['data']['email'] ?? '';
                // Store user with token
                user_upsert($phone, $token, $rTok, $name, $email);
                // Fetch batch count and store
                $batches = api_get_batches($token);
                user_update_batches($phone, count($batches['data']));
                // Login session
                login_user($phone, $token, $name);
                unset($_SESSION['login_step'], $_SESSION['login_phone']);
                header('Location: /dashboard.php');
                exit;
            } else {
                $error = 'Wrong OTP. Please try again.';
                $step  = 'otp';
            }
        }
    }

    // ── RESEND OTP ───────────────────────────────────────────
    if ($action === 'resend_otp') {
        $phone = $_SESSION['login_phone'] ?? '';
        if ($phone) {
            $r = api_send_otp($phone);
            if ($r['code'] === 200) {
                $success = 'OTP resent to +91 ' . $phone;
            }
            $step = 'otp';
        } else {
            header('Location: /index.php');
            exit;
        }
    }

    // ── TOKEN LOGIN ──────────────────────────────────────────
    if ($action === 'token_login') {
        $token = trim($_POST['token'] ?? '');
        if (strlen($token) < 30) {
            $error = 'Please enter a valid Bearer token.';
        } else {
            if (api_validate_token($token)) {
                $prof  = api_get_profile($token);
                $name  = $prof['data']['name']  ?? '';
                $email = $prof['data']['email'] ?? '';
                $phone = $prof['data']['username'] ?? ('token_' . substr(md5($token), 0, 8));
                $rTok  = '';
                user_upsert($phone, $token, $rTok, $name, $email);
                $batches = api_get_batches($token);
                user_update_batches($phone, count($batches['data']));
                login_user($phone, $token, $name);
                header('Location: /dashboard.php');
                exit;
            } else {
                $error = 'Invalid or expired token. Please try again.';
            }
        }
    }

    // ── BACK ─────────────────────────────────────────────────
    if ($action === 'back') {
        unset($_SESSION['login_step'], $_SESSION['login_phone']);
        header('Location: /index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>PW Portal — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#05050d;--s1:#0a0a18;--s2:#0f0f22;
  --border:#1c1c35;--border2:#252545;
  --accent:#f97316;--accent2:#fb923c;--accent3:#fdba74;
  --purple:#7c3aed;--blue:#3b82f6;
  --text:#eeeeff;--muted:#6b6b95;--muted2:#9090b8;
  --success:#22c55e;--danger:#ef4444;
  --r:14px;
}
html,body{min-height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text)}

/* ── Animated mesh background ─────── */
.bg{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.mesh{position:absolute;border-radius:50%;filter:blur(100px);animation:float 20s ease-in-out infinite alternate}
.m1{width:700px;height:700px;top:-300px;left:-200px;background:radial-gradient(circle,rgba(249,115,22,.18) 0%,rgba(124,58,237,.1) 60%,transparent 100%);animation-delay:0s}
.m2{width:500px;height:500px;bottom:-200px;right:-150px;background:radial-gradient(circle,rgba(59,130,246,.15) 0%,rgba(124,58,237,.08) 60%,transparent 100%);animation-delay:-8s}
.m3{width:350px;height:350px;top:40%;left:55%;background:radial-gradient(circle,rgba(249,115,22,.08),transparent 70%);animation-delay:-4s}
@keyframes float{0%{transform:translate(0,0) scale(1)}100%{transform:translate(30px,20px) scale(1.08)}}

/* ── Grid overlay ─────────────────── */
.grid-bg{position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:linear-gradient(rgba(249,115,22,.04) 1px,transparent 1px),
                   linear-gradient(90deg,rgba(249,115,22,.04) 1px,transparent 1px);
  background-size:50px 50px}

/* ── Corner accents ──────────────── */
.corner{position:fixed;width:200px;height:200px;pointer-events:none;z-index:0}
.c-tl{top:0;left:0;border-top:2px solid rgba(249,115,22,.2);border-left:2px solid rgba(249,115,22,.2)}
.c-br{bottom:0;right:0;border-bottom:2px solid rgba(124,58,237,.2);border-right:2px solid rgba(124,58,237,.2)}

/* ── Page layout ─────────────────── */
.page{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}

/* ── Brand ───────────────────────── */
.brand{text-align:center;margin-bottom:36px}
.brand-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);border-radius:100px;margin-bottom:18px}
.brand-dot{width:8px;height:8px;background:#f97316;border-radius:50%;animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(249,115,22,.5)}60%{box-shadow:0 0 0 8px transparent}}
.brand-label{font-size:11px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#f97316}
.brand-title{font-size:clamp(2rem,5vw,3rem);font-weight:800;line-height:1.1;letter-spacing:-.02em;margin-bottom:6px}
.brand-title .hi{background:linear-gradient(135deg,#fff 20%,#f97316 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.brand-sub{font-size:14px;color:var(--muted2);letter-spacing:.01em}

/* ── Card ────────────────────────── */
.card{width:100%;max-width:430px;background:rgba(10,10,24,.9);backdrop-filter:blur(24px);border:1px solid var(--border);border-radius:20px;padding:32px;box-shadow:0 0 0 1px rgba(249,115,22,.04) inset,0 32px 80px rgba(0,0,0,.7)}

/* ── Tab switcher ────────────────── */
.tabs{display:flex;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:4px;gap:4px;margin-bottom:26px}
.tab{flex:1;padding:9px 8px;border:none;background:none;color:var(--muted2);font-family:inherit;font-size:13px;font-weight:600;border-radius:7px;cursor:pointer;transition:all .2s;letter-spacing:.01em}
.tab.active{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;box-shadow:0 4px 14px rgba(249,115,22,.4)}
.tab:hover:not(.active){background:rgba(255,255,255,.06);color:var(--text)}
.pane{display:none}.pane.active{display:block}

/* ── Step indicator ──────────────── */
.steps{display:flex;align-items:center;margin-bottom:22px}
.step-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;transition:all .3s}
.step-dot.done{background:#f97316;color:#fff;box-shadow:0 4px 12px rgba(249,115,22,.5)}
.step-dot.active{background:rgba(249,115,22,.15);border:2px solid #f97316;color:#f97316}
.step-dot.idle{background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--muted)}
.step-bar{flex:1;height:2px;background:var(--border);margin:0 6px;transition:background .3s}
.step-bar.done{background:#f97316}

/* ── Form ────────────────────────── */
.field{margin-bottom:16px}
.label{display:block;font-size:11px;font-weight:700;color:var(--muted2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
.iw{position:relative}
.pfx{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:600;color:var(--muted2)}
input[type=text],input[type=tel],input[type=password],textarea{
  width:100%;padding:12px 14px;background:rgba(255,255,255,.05);border:1px solid var(--border);
  border-radius:10px;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;
  font-size:15px;outline:none;transition:all .2s}
input[type=tel]{padding-left:44px}
input:focus,textarea:focus{border-color:#f97316;background:rgba(249,115,22,.07);box-shadow:0 0 0 3px rgba(249,115,22,.12)}
input::placeholder{color:var(--muted)}

/* ── OTP boxes ───────────────────── */
.otp-row{display:flex;gap:8px;justify-content:space-between}
.otp-box{width:54px!important;height:58px;text-align:center;font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;padding:0!important;border-radius:10px;flex-shrink:0}

/* ── Buttons ─────────────────────── */
.btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;letter-spacing:.03em;cursor:pointer;transition:all .25s}
.btn-primary{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;box-shadow:0 6px 20px rgba(249,115,22,.4)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(249,115,22,.55)}
.btn-primary:active{transform:translateY(0)}
.btn-ghost{background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--muted2);font-size:13px;padding:10px}
.btn-ghost:hover{color:var(--text);background:rgba(255,255,255,.09)}

.row-btns{display:flex;gap:10px;margin-top:6px}
.row-btns .btn{flex:1}

/* ── Alerts ──────────────────────── */
.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;line-height:1.5}
.alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5}
.alert-ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#86efac}
.alert-icon{flex-shrink:0;font-size:16px}

/* ── Divider ─────────────────────── */
.divider{display:flex;align-items:center;gap:10px;margin:18px 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
.divider span{font-size:11px;color:var(--muted);letter-spacing:.05em;text-transform:uppercase}

/* ── Spinner ─────────────────────── */
.spin{display:inline-block;width:16px;height:16px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:rot .7s linear infinite;vertical-align:middle}
@keyframes rot{to{transform:rotate(360deg)}}

/* ── Security note ───────────────── */
.sec-note{display:flex;align-items:center;gap:8px;margin-top:18px;padding:10px 14px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;font-size:11px;color:var(--muted)}
.sec-note .dot{width:6px;height:6px;border-radius:50%;background:#22c55e;flex-shrink:0}

/* ── Responsive ──────────────────── */
@media(max-width:480px){.card{padding:22px}.otp-box{width:44px!important;height:50px;font-size:18px}.otp-row{gap:5px}}
</style>
</head>
<body>
<div class="bg"><div class="mesh m1"></div><div class="mesh m2"></div><div class="mesh m3"></div></div>
<div class="grid-bg"></div>
<div class="corner c-tl"></div>
<div class="corner c-br"></div>

<main class="page">
  <!-- Brand -->
  <div class="brand">
    <div class="brand-badge"><div class="brand-dot"></div><span class="brand-label">Physics Wallah</span></div>
    <h1 class="brand-title"><span class="hi">PW Portal</span></h1>
    <p class="brand-sub">Access your courses, batches & video lectures</p>
  </div>

  <div class="card">
    <!-- Alerts -->
    <?php if ($error): ?><div class="alert alert-err"><span class="alert-icon">⚠</span><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-ok"><span class="alert-icon">✓</span><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (!empty($_GET['msg'])): ?><div class="alert alert-err"><span class="alert-icon">ℹ</span><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>

    <?php if ($step === 'start'): ?>
    <!-- ── STEP 1: Login options ── -->
    <div class="tabs">
      <button class="tab active" onclick="switchTab('otp',this)">📱 OTP Login</button>
      <button class="tab" onclick="switchTab('token',this)">🔑 Token Login</button>
    </div>

    <div class="pane active" id="pane-otp">
      <form method="POST" onsubmit="showSpin(this)">
        <input type="hidden" name="action" value="send_otp">
        <div class="field">
          <label class="label">Mobile Number</label>
          <div class="iw">
            <span class="pfx">+91</span>
            <input type="tel" name="phone" maxlength="10" placeholder="10-digit number" autocomplete="tel" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Send OTP →</button>
      </form>
    </div>

    <div class="pane" id="pane-token">
      <form method="POST" onsubmit="showSpin(this)">
        <input type="hidden" name="action" value="token_login">
        <div class="field">
          <label class="label">Bearer Token</label>
          <textarea name="token" rows="3" style="resize:vertical;padding:12px 14px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:12px;width:100%;outline:none;transition:all .2s" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Login with Token →</button>
      </form>
    </div>

    <?php elseif ($step === 'otp'): ?>
    <!-- ── STEP 2: OTP Verification ── -->
    <div class="steps">
      <div class="step-dot done">✓</div>
      <div class="step-bar done"></div>
      <div class="step-dot active">2</div>
      <div class="step-bar idle"></div>
      <div class="step-dot idle">3</div>
    </div>
    <p style="font-size:13px;color:var(--muted2);margin-bottom:20px">OTP sent to <strong style="color:var(--text)">+91 <?= htmlspecialchars($phone) ?></strong></p>

    <form method="POST" id="otpForm" onsubmit="joinAndSubmit(event)">
      <input type="hidden" name="action" value="verify_otp">
      <input type="hidden" name="otp" id="otpVal">
      <div class="field">
        <label class="label">Enter 6-Digit OTP</label>
        <div class="otp-row">
          <?php for($i=0;$i<6;$i++): ?>
          <input type="tel" class="otp-box" id="ob<?=$i?>" maxlength="1" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]">
          <?php endfor; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:6px">Verify OTP →</button>
    </form>

    <div class="divider"><span>options</span></div>
    <div class="row-btns">
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="resend_otp">
        <button type="submit" class="btn btn-ghost">↺ Resend</button>
      </form>
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="back">
        <button type="submit" class="btn btn-ghost">← Change No.</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="sec-note">
      <div class="dot"></div>
      Your data is encrypted. Tokens are never exposed to browser tools.
    </div>
  </div>
</main>

<script>
function switchTab(id, btn) {
  document.querySelectorAll('.pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
  document.getElementById('pane-' + id).classList.add('active');
  btn.classList.add('active');
}

// OTP box wiring
const boxes = [...document.querySelectorAll('.otp-box')];
boxes.forEach((b, i) => {
  b.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/,'').slice(-1);
    if (e.target.value && i < boxes.length - 1) boxes[i+1].focus();
  });
  b.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !e.target.value && i > 0) boxes[i-1].focus();
  });
  b.addEventListener('paste', e => {
    const p = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    boxes.forEach((bx, j) => { if (p[j]) bx.value = p[j]; });
    e.preventDefault();
    boxes[Math.min(p.length, boxes.length-1)].focus();
  });
});

function joinAndSubmit(e) {
  e.preventDefault();
  const otp = boxes.map(b => b.value).join('');
  if (otp.length < 4) { alert('Please enter OTP'); return; }
  document.getElementById('otpVal').value = otp;
  const btn = e.target.querySelector('button[type=submit]');
  btn.innerHTML = '<span class="spin"></span> Verifying...';
  btn.disabled = true;
  e.target.submit();
}

function showSpin(form) {
  const btn = form.querySelector('button[type=submit]');
  if (btn) { btn.innerHTML = '<span class="spin"></span> Please wait...'; btn.disabled = true; }
}

// block devtools on login
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key==='F12'||(e.ctrlKey&&e.shiftKey&&'IJC'.includes(e.key))||(e.ctrlKey&&e.key==='U')) e.preventDefault();
});
</script>
</body>
</html>
