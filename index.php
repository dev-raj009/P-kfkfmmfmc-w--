<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot();

if (is_logged_in()) { header('Location: /dashboard.php'); exit; }

$error   = '';
$success = '';
$step    = $_SESSION['ls'] ?? 'start'; // ls = login step
$phone   = $_SESSION['lp'] ?? '';      // lp = login phone

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
            // FIX: PW returns HTTP 201 for success — accept ANY 2xx
            if ($r['success']) {
                $_SESSION['ls'] = 'otp';
                $_SESSION['lp'] = $phone;
                $step    = 'otp';
                $success = 'OTP sent to +91 ' . $phone . ' ✓';
            } else {
                // Show real HTTP code for debugging
                $error = 'OTP request failed (HTTP ' . $r['code'] . '). Check number or try Token login.';
            }
        }
    }

    // ── VERIFY OTP ───────────────────────────────────────────
    if ($action === 'verify_otp') {
        $otp   = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        $phone = $_SESSION['lp'] ?? '';
        $step  = 'otp';
        if (!$phone || strlen($otp) < 4) {
            $error = 'Invalid OTP or session expired.';
            $step  = 'start';
        } else {
            $r = api_verify_otp($phone, $otp);
            if ($r['success']) {
                $token = $r['access_token'];
                $rTok  = $r['refresh_token'];
                $prof  = api_get_profile($token);
                $name  = $prof['data']['name']  ?? '';
                $email = $prof['data']['email'] ?? '';
                // Fetch batches for storage
                $br    = api_get_batches($token, 1);
                $bData = array_map(fn($b) => [
                    '_id'  => $b['_id']  ?? '',
                    'name' => $b['name'] ?? '',
                ], $br['data'] ?? []);
                user_upsert($phone, $token, $rTok, $name, $email, $bData);
                do_login($phone, $token, $name);
                unset($_SESSION['ls'], $_SESSION['lp']);
                header('Location: /dashboard.php');
                exit;
            } else {
                $error = 'Wrong OTP. Please check and try again.';
                $step  = 'otp';
            }
        }
    }

    // ── RESEND OTP ───────────────────────────────────────────
    if ($action === 'resend_otp') {
        $phone = $_SESSION['lp'] ?? '';
        if ($phone) {
            $r = api_send_otp($phone);
            $success = $r['success'] ? 'OTP resent to +91 ' . $phone : 'Resend failed. Try again.';
        }
        $step = 'otp';
    }

    // ── TOKEN LOGIN ──────────────────────────────────────────
    if ($action === 'token_login') {
        $raw_token = trim($_POST['token'] ?? '');
        // Strip "Bearer " prefix if user pastes full header
        $token = preg_replace('/^Bearer\s+/i', '', $raw_token);
        if (strlen($token) < 30) {
            $error = 'Please enter a valid access token.';
        } elseif (api_validate_token($token)) {
            $prof  = api_get_profile($token);
            $name  = $prof['data']['name']     ?? '';
            $email = $prof['data']['email']    ?? '';
            $phone = $prof['data']['username'] ?? ('tok_' . substr(md5($token), 0, 8));
            $br    = api_get_batches($token, 1);
            $bData = array_map(fn($b) => [
                '_id'  => $b['_id']  ?? '',
                'name' => $b['name'] ?? '',
            ], $br['data'] ?? []);
            user_upsert($phone, $token, '', $name, $email, $bData);
            do_login($phone, $token, $name);
            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Token is invalid or expired. Please generate a new token.';
        }
    }

    // ── BACK ─────────────────────────────────────────────────
    if ($action === 'back') {
        unset($_SESSION['ls'], $_SESSION['lp']);
        header('Location: /index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>PW Portal — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
:root{
  --bg:#06060f;--s1:#0a0a1a;
  --card:rgba(12,12,28,0.95);
  --b1:#1a1a35;--b2:#252548;
  --acc:#f97316;--acc2:#fb923c;
  --text:#f0f0ff;--muted:#7070a0;--muted2:#a0a0c8;
  --ok:#22c55e;--err:#ef4444;
}
html,body{min-height:100%;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden}

/* bg */
.bg{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.orb{position:absolute;border-radius:50%;filter:blur(80px);animation:orb 15s ease-in-out infinite alternate}
.o1{width:500px;height:500px;top:-200px;left:-150px;background:radial-gradient(#f9731622,#7c3aed11);animation-delay:0s}
.o2{width:400px;height:400px;bottom:-150px;right:-100px;background:radial-gradient(#3b82f611,#7c3aed11);animation-delay:-7s}
.grid{position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:linear-gradient(rgba(249,115,22,.04)1px,transparent 1px),
                   linear-gradient(90deg,rgba(249,115,22,.04)1px,transparent 1px);
  background-size:48px 48px}
@keyframes orb{0%{transform:translate(0,0)}100%{transform:translate(30px,25px)}}

/* layout */
.page{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px 16px}

/* brand */
.brand{text-align:center;margin-bottom:28px}
.badge{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.3);border-radius:100px;margin-bottom:14px}
.badge-dot{width:8px;height:8px;border-radius:50%;background:#f97316;animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(249,115,22,.5)}60%{box-shadow:0 0 0 7px rgba(249,115,22,0)}}
.badge-txt{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#f97316}
.brand-title{font-size:clamp(2rem,8vw,3rem);font-weight:900;letter-spacing:-.03em;line-height:1}
.brand-title .wh{color:#fff}.brand-title .or{color:#f97316}
.brand-sub{font-size:14px;color:var(--muted2);margin-top:8px;font-weight:400}

/* card */
.card{width:100%;max-width:420px;background:var(--card);backdrop-filter:blur(20px);border:1px solid var(--b1);border-radius:20px;padding:28px;box-shadow:0 0 0 1px rgba(249,115,22,.04) inset,0 32px 64px rgba(0,0,0,.7)}

/* tabs */
.tabs{display:flex;gap:4px;background:rgba(255,255,255,.04);border:1px solid var(--b1);border-radius:12px;padding:4px;margin-bottom:24px}
.tab{flex:1;padding:10px 8px;border:none;background:none;color:var(--muted2);font-family:'Inter',sans-serif;font-size:13px;font-weight:600;border-radius:9px;cursor:pointer;transition:all .2s}
.tab.on{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;box-shadow:0 3px 12px rgba(249,115,22,.4)}
.tab:hover:not(.on){color:var(--text);background:rgba(255,255,255,.06)}
.pane{display:none}.pane.on{display:block}

/* steps */
.steps{display:flex;align-items:center;margin-bottom:20px}
.sdot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;transition:all .3s}
.sdot.done{background:#f97316;color:#fff}
.sdot.active{background:rgba(249,115,22,.15);border:2px solid #f97316;color:#f97316}
.sdot.idle{background:rgba(255,255,255,.06);border:1px solid var(--b1);color:var(--muted)}
.sbar{flex:1;height:2px;background:var(--b1);margin:0 5px;transition:background .3s}
.sbar.done{background:#f97316}

/* form */
.field{margin-bottom:16px}
.flabel{display:block;font-size:11px;font-weight:700;color:var(--muted2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
.iw{position:relative}
.ipfx{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:600;color:var(--muted2);pointer-events:none}
input[type=tel],input[type=text],textarea{
  width:100%;padding:13px 14px;background:rgba(255,255,255,.05);
  border:1.5px solid var(--b1);border-radius:11px;color:var(--text);
  font-family:'Inter',sans-serif;font-size:15px;outline:none;transition:all .2s;
  -webkit-appearance:none}
input[type=tel]{padding-left:46px}
input:focus,textarea:focus{border-color:#f97316;background:rgba(249,115,22,.07);box-shadow:0 0 0 3px rgba(249,115,22,.12)}
input::placeholder,textarea::placeholder{color:var(--muted)}

/* OTP boxes */
.otp-row{display:flex;gap:7px}
.ob{width:100%!important;height:56px;text-align:center;font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;padding:0!important;border-radius:11px;letter-spacing:0;flex:1}

/* buttons */
.btn{width:100%;padding:14px;border:none;border-radius:11px;font-family:'Inter',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-p{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;box-shadow:0 6px 20px rgba(249,115,22,.38)}
.btn-p:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(249,115,22,.52)}
.btn-p:active{transform:translateY(0)}
.btn-g{background:rgba(255,255,255,.06);border:1.5px solid var(--b1);color:var(--muted2);font-size:13px;padding:10px}
.btn-g:hover{background:rgba(255,255,255,.1);color:var(--text)}
.row2{display:flex;gap:8px;margin-top:10px}
.row2 .btn{flex:1}

/* alerts */
.alert{display:flex;align-items:flex-start;gap:9px;padding:11px 14px;border-radius:11px;font-size:13px;line-height:1.5;margin-bottom:18px}
.aerr{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5}
.aok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#86efac}
.aico{flex-shrink:0;font-size:15px}

/* divider */
.div{display:flex;align-items:center;gap:10px;margin:16px 0}
.div::before,.div::after{content:'';flex:1;height:1px;background:var(--b1)}
.div span{font-size:11px;color:var(--muted);letter-spacing:.06em}

/* spinner */
.spin{display:inline-block;width:16px;height:16px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:rot .7s linear infinite}
@keyframes rot{to{transform:rotate(360deg)}}

/* note */
.note{display:flex;align-items:center;gap:8px;margin-top:16px;padding:10px 14px;background:rgba(255,255,255,.02);border:1px solid var(--b1);border-radius:9px;font-size:11px;color:var(--muted)}
.ndot{width:6px;height:6px;border-radius:50%;background:var(--ok);flex-shrink:0}

@media(max-width:400px){.ob{height:48px;font-size:18px}.otp-row{gap:5px}}
</style>
</head>
<body>
<div class="bg">
  <div class="orb o1"></div>
  <div class="orb o2"></div>
</div>
<div class="grid"></div>

<main class="page">
  <div class="brand">
    <div class="badge"><div class="badge-dot"></div><span class="badge-txt">Physics Wallah</span></div>
    <h1 class="brand-title"><span class="wh">PW</span><span class="or">Portal</span></h1>
    <p class="brand-sub">Access your courses, batches &amp; video lectures</p>
  </div>

  <div class="card">
    <?php if ($error): ?><div class="alert aerr"><span class="aico">⚠</span><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert aok"><span class="aico">✓</span><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (!empty($_GET['m'])): ?><div class="alert aerr"><span class="aico">ℹ</span><?= htmlspecialchars($_GET['m']) ?></div><?php endif; ?>

    <?php if ($step === 'start'): ?>
    <div class="tabs">
      <button class="tab on" id="t-otp" onclick="swTab('otp',this)">📱 OTP Login</button>
      <button class="tab" id="t-tok" onclick="swTab('tok',this)">🔑 Token Login</button>
    </div>

    <div class="pane on" id="p-otp">
      <form method="POST" onsubmit="showSpin(this)">
        <input type="hidden" name="action" value="send_otp">
        <div class="field">
          <label class="flabel">Mobile Number</label>
          <div class="iw">
            <span class="ipfx">+91</span>
            <input type="tel" name="phone" maxlength="10" placeholder="10-digit number" autocomplete="tel" inputmode="numeric" required>
          </div>
        </div>
        <button type="submit" class="btn btn-p">Send OTP →</button>
      </form>
    </div>

    <div class="pane" id="p-tok">
      <form method="POST" onsubmit="showSpin(this)">
        <input type="hidden" name="action" value="token_login">
        <div class="field">
          <label class="flabel">Bearer Token</label>
          <textarea name="token" rows="4" style="font-family:'JetBrains Mono',monospace;font-size:12px;resize:vertical;border-radius:11px;background:rgba(255,255,255,.05);border:1.5px solid var(--b1);color:var(--text);width:100%;padding:12px 14px;outline:none;transition:all .2s" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...&#10;(You can also paste: Bearer eyJ...)" required></textarea>
        </div>
        <button type="submit" class="btn btn-p">Login with Token →</button>
      </form>
    </div>

    <?php elseif ($step === 'otp'): ?>
    <div class="steps">
      <div class="sdot done">✓</div>
      <div class="sbar done"></div>
      <div class="sdot active">2</div>
      <div class="sbar"></div>
      <div class="sdot idle">✓</div>
    </div>
    <p style="font-size:13px;color:var(--muted2);margin-bottom:18px">OTP sent to <strong style="color:var(--text)">+91 <?= htmlspecialchars($phone) ?></strong></p>

    <form method="POST" id="otpForm" onsubmit="return submitOtp(event)">
      <input type="hidden" name="action" value="verify_otp">
      <input type="hidden" name="otp" id="otpVal">
      <div class="field">
        <label class="flabel">Enter 6-Digit OTP</label>
        <div class="otp-row">
          <input type="tel" class="ob" id="ob0" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
          <input type="tel" class="ob" id="ob1" maxlength="1" inputmode="numeric">
          <input type="tel" class="ob" id="ob2" maxlength="1" inputmode="numeric">
          <input type="tel" class="ob" id="ob3" maxlength="1" inputmode="numeric">
          <input type="tel" class="ob" id="ob4" maxlength="1" inputmode="numeric">
          <input type="tel" class="ob" id="ob5" maxlength="1" inputmode="numeric">
        </div>
      </div>
      <button type="submit" class="btn btn-p" id="vbtn" style="margin-top:6px">Verify OTP →</button>
    </form>

    <div class="div"><span>options</span></div>
    <div class="row2">
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="resend_otp">
        <button type="submit" class="btn btn-g">↺ Resend</button>
      </form>
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="back">
        <button type="submit" class="btn btn-g">← Back</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="note"><div class="ndot"></div>Your tokens are stored securely and never exposed in browser.</div>
  </div>
</main>

<script>
function swTab(id, btn) {
  document.querySelectorAll('.pane').forEach(p => p.classList.remove('on'));
  document.querySelectorAll('.tab').forEach(b => b.classList.remove('on'));
  document.getElementById('p-' + id).classList.add('on');
  btn.classList.add('on');
}

// OTP boxes
const obs = [0,1,2,3,4,5].map(i => document.getElementById('ob' + i)).filter(Boolean);
obs.forEach((b, i) => {
  b.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/g, '').slice(-1);
    if (e.target.value && obs[i+1]) obs[i+1].focus();
  });
  b.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !e.target.value && obs[i-1]) obs[i-1].focus();
  });
  b.addEventListener('paste', e => {
    const p = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
    obs.forEach((bx, j) => { if (p[j]) bx.value = p[j]; });
    e.preventDefault();
    const last = obs[Math.min(p.length, obs.length) - 1];
    if (last) last.focus();
  });
});
if (obs[0]) obs[0].focus();

function submitOtp(e) {
  e.preventDefault();
  const otp = obs.map(b => b.value).join('');
  if (otp.length < 4) { alert('Please enter the complete OTP'); return false; }
  document.getElementById('otpVal').value = otp;
  const btn = document.getElementById('vbtn');
  btn.innerHTML = '<span class="spin"></span> Verifying...';
  btn.disabled = true;
  document.getElementById('otpForm').submit();
  return false;
}

function showSpin(f) {
  const btn = f.querySelector('button[type=submit]');
  if (btn) { btn.innerHTML = '<span class="spin"></span> Please wait...'; btn.disabled = true; }
}

// Block devtools
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && 'IJC'.includes(e.key)) || (e.ctrlKey && e.key === 'U'))
    e.preventDefault();
});
</script>
</body>
</html>
