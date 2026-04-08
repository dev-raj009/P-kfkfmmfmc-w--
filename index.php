<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

session_boot();

// Already logged in? go to dashboard
if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error   = '';
$success = '';
$step    = $_SESSION['login_step'] ?? 'start';   // start | otp | done
$phone   = $_SESSION['login_phone'] ?? '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Step 1: Send OTP
    if ($action === 'send_otp') {
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        if (strlen($phone) !== 10) {
            $error = 'Please enter a valid 10-digit mobile number.';
        } else {
            $res = pw_send_otp($phone);
            if ($res['code'] === 200) {
                $_SESSION['login_phone'] = $phone;
                $_SESSION['login_step']  = 'otp';
                $step    = 'otp';
                $success = 'OTP sent to +91 ' . $phone;
            } else {
                $error = 'Failed to send OTP. Check your number and try again.';
            }
        }
    }

    // Step 2: Verify OTP
    if ($action === 'verify_otp') {
        $otp   = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        $phone = $_SESSION['login_phone'] ?? '';
        if (strlen($otp) < 4 || !$phone) {
            $error = 'Invalid OTP or session expired.';
        } else {
            $res = pw_verify_otp($phone, $otp);
            if ($res['code'] === 200 && isset($res['body']['data']['access_token'])) {
                $token        = $res['body']['data']['access_token'];
                $refreshToken = $res['body']['data']['refresh_token'] ?? '';

                // Get profile
                $profile = pw_get_profile($token);
                $name    = $profile['body']['data']['name'] ?? '';

                user_upsert($phone, $token, $refreshToken, $name);
                login_user($phone, $token, $name);
                unset($_SESSION['login_step'], $_SESSION['login_phone']);
                header('Location: /dashboard.php');
                exit;
            } else {
                $error = 'Incorrect OTP. Please try again.';
            }
        }
    }

    // Direct Token Login
    if ($action === 'token_login') {
        $token = trim($_POST['token'] ?? '');
        if (strlen($token) < 20) {
            $error = 'Please enter a valid access token.';
        } else {
            $res = pw_validate_token($token);
            if ($res['code'] === 200) {
                $profile      = pw_get_profile($token);
                $name         = $profile['body']['data']['name'] ?? '';
                $phone        = $profile['body']['data']['username'] ?? 'token_user_' . time();
                user_upsert($phone, $token, '', $name);
                login_user($phone, $token, $name);
                unset($_SESSION['login_step'], $_SESSION['login_phone']);
                header('Location: /dashboard.php');
                exit;
            } else {
                $error = 'Invalid or expired token.';
            }
        }
    }

    // Resend OTP
    if ($action === 'resend_otp') {
        $phone = $_SESSION['login_phone'] ?? '';
        if ($phone) {
            pw_send_otp($phone);
            $success = 'OTP resent to +91 ' . $phone;
            $step = 'otp';
        }
    }

    // Back to start
    if ($action === 'back') {
        unset($_SESSION['login_step'], $_SESSION['login_phone']);
        header('Location: /index.php');
        exit;
    }
}

$msg = htmlspecialchars($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>PW Login — Physics Wallah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --bg:#09090f;
  --surface:#0f0f1a;
  --card:#14141f;
  --border:#1e1e30;
  --accent:#f97316;
  --accent2:#fb923c;
  --text:#f1f0ff;
  --muted:#6b6b8a;
  --success:#22c55e;
  --danger:#ef4444;
  --radius:16px;
}

html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden}

/* ── Animated background ── */
.bg-canvas{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.35;animation:drift 12s ease-in-out infinite alternate}
.orb1{width:500px;height:500px;background:radial-gradient(circle,#f97316,#7c3aed);top:-150px;left:-150px;animation-delay:0s}
.orb2{width:400px;height:400px;background:radial-gradient(circle,#0ea5e9,#7c3aed);bottom:-100px;right:-100px;animation-delay:-6s}
.orb3{width:250px;height:250px;background:radial-gradient(circle,#f97316,#ec4899);top:40%;left:50%;animation-delay:-3s}
@keyframes drift{0%{transform:translate(0,0) scale(1)}100%{transform:translate(40px,30px) scale(1.1)}}

/* ── Grid lines ── */
.grid-lines{position:fixed;inset:0;z-index:0;
  background-image:
    linear-gradient(rgba(249,115,22,.05) 1px,transparent 1px),
    linear-gradient(90deg,rgba(249,115,22,.05) 1px,transparent 1px);
  background-size:60px 60px;
  pointer-events:none}

/* ── Layout ── */
.page{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}

/* ── Logo / Header ── */
.logo-wrap{text-align:center;margin-bottom:32px}
.logo-badge{display:inline-flex;align-items:center;gap:12px;padding:10px 20px;background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.3);border-radius:100px;margin-bottom:20px}
.logo-dot{width:10px;height:10px;background:#f97316;border-radius:50%;animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(249,115,22,.6)}50%{box-shadow:0 0 0 8px rgba(249,115,22,0)}}
.logo-label{font-family:'Syne',sans-serif;font-size:13px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#f97316}
.site-title{font-family:'Syne',sans-serif;font-size:clamp(2rem,5vw,3.2rem);font-weight:800;line-height:1;margin-bottom:8px;background:linear-gradient(135deg,#fff 30%,#f97316 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.site-sub{font-size:15px;color:var(--muted);letter-spacing:.02em}

/* ── Card ── */
.card{width:100%;max-width:440px;background:rgba(20,20,31,.85);backdrop-filter:blur(20px);border:1px solid var(--border);border-radius:var(--radius);padding:36px;box-shadow:0 24px 80px rgba(0,0,0,.6),0 0 0 1px rgba(249,115,22,.05) inset}

/* ── Tabs ── */
.tabs{display:flex;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:4px;margin-bottom:28px;gap:4px}
.tab-btn{flex:1;padding:10px;border:none;background:transparent;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;border-radius:7px;cursor:pointer;transition:all .2s}
.tab-btn.active{background:var(--accent);color:#fff;box-shadow:0 4px 16px rgba(249,115,22,.35)}
.tab-btn:hover:not(.active){color:var(--text);background:rgba(255,255,255,.06)}
.tab-pane{display:none}.tab-pane.active{display:block}

/* ── Form Elements ── */
.form-group{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
.input-wrap{position:relative;display:flex;align-items:center}
.prefix{position:absolute;left:14px;font-size:14px;font-weight:500;color:var(--muted)}
.input-icon{position:absolute;right:14px;color:var(--muted);font-size:16px}
input[type=text],input[type=password],input[type=tel],textarea{
  width:100%;padding:13px 14px;background:rgba(255,255,255,.05);
  border:1px solid var(--border);border-radius:10px;color:var(--text);
  font-family:'DM Sans',sans-serif;font-size:15px;transition:all .2s;outline:none}
input[type=tel]{padding-left:48px}
input:focus{border-color:var(--accent);background:rgba(249,115,22,.07);box-shadow:0 0 0 3px rgba(249,115,22,.15)}
input::placeholder{color:var(--muted)}

/* OTP Row */
.otp-row{display:flex;gap:8px;justify-content:center}
.otp-row input{width:52px;height:56px;text-align:center;font-size:22px;font-weight:700;font-family:'Syne',sans-serif;border-radius:10px;padding:0;letter-spacing:0}

/* ── Buttons ── */
.btn{width:100%;padding:14px;border:none;border-radius:10px;font-family:'Syne',sans-serif;font-size:15px;font-weight:700;letter-spacing:.04em;cursor:pointer;transition:all .25s;position:relative;overflow:hidden}
.btn-primary{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;box-shadow:0 8px 24px rgba(249,115,22,.4)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 12px 32px rgba(249,115,22,.55)}
.btn-primary:active{transform:translateY(0)}
.btn-secondary{background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--muted);font-size:13px;padding:10px}
.btn-secondary:hover{color:var(--text);background:rgba(255,255,255,.1)}
.btn-sm{padding:8px 16px;width:auto;font-size:13px;border-radius:8px}

/* ── Alerts ── */
.alert{padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}

/* ── Info chips ── */
.info-row{display:flex;align-items:center;justify-content:space-between;margin-top:12px}
.chip{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px}

/* ── Step indicator ── */
.steps{display:flex;align-items:center;gap:0;margin-bottom:24px}
.step{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;font-family:'Syne',sans-serif;transition:all .3s}
.step.done{background:var(--accent);color:#fff}
.step.active{background:rgba(249,115,22,.2);border:2px solid var(--accent);color:var(--accent)}
.step.pending{background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--muted)}
.step-line{flex:1;height:2px;background:var(--border)}
.step-line.done{background:var(--accent)}

/* ── Footer ── */
.footer{margin-top:24px;text-align:center;font-size:12px;color:var(--muted)}
.footer a{color:var(--accent);text-decoration:none}

/* ── Loader ── */
.spinner{width:18px;height:18px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block;vertical-align:middle;margin-right:8px}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Divider ── */
.divider{display:flex;align-items:center;gap:12px;margin:20px 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
.divider span{font-size:12px;color:var(--muted)}

/* ── Responsive ── */
@media(max-width:480px){.card{padding:24px}.otp-row input{width:44px;height:50px;font-size:18px}}
</style>
</head>
<body>
<div class="bg-canvas">
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="orb orb3"></div>
</div>
<div class="grid-lines"></div>

<main class="page">
  <div class="logo-wrap">
    <div class="logo-badge">
      <span class="logo-dot"></span>
      <span class="logo-label">Physics Wallah</span>
    </div>
    <h1 class="site-title">PW Portal</h1>
    <p class="site-sub">Access your courses & batches</p>
  </div>

  <div class="card">
    <?php if ($msg): ?>
      <div class="alert alert-error"><span>⚠</span><?= $msg ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><span>✕</span><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><span>✓</span><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($step === 'start'): ?>
    <!-- ── Tabs: OTP | Token ── -->
    <div class="tabs">
      <button class="tab-btn active" onclick="switchTab('otp',this)">📱 Login with OTP</button>
      <button class="tab-btn" onclick="switchTab('token',this)">🔑 Use Token</button>
    </div>

    <!-- OTP Tab -->
    <div class="tab-pane active" id="tab-otp">
      <form method="POST" onsubmit="showLoader(this)">
        <input type="hidden" name="action" value="send_otp"/>
        <div class="form-group">
          <label>Mobile Number</label>
          <div class="input-wrap">
            <span class="prefix">+91</span>
            <input type="tel" name="phone" maxlength="10" placeholder="Enter 10-digit number" required autocomplete="tel"/>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Send OTP →</button>
      </form>
    </div>

    <!-- Token Tab -->
    <div class="tab-pane" id="tab-token">
      <form method="POST" onsubmit="showLoader(this)">
        <input type="hidden" name="action" value="token_login"/>
        <div class="form-group">
          <label>PW Access Token</label>
          <input type="text" name="token" placeholder="Paste your Bearer token here" required/>
        </div>
        <button type="submit" class="btn btn-primary">Login with Token →</button>
      </form>
      <div class="chip" style="margin-top:14px;font-size:12px;color:var(--muted)">
        🔒 Your token is verified securely. It is never stored in plain sight.
      </div>
    </div>
    <?php endif; ?>

    <?php if ($step === 'otp'): ?>
    <!-- ── OTP Verification Step ── -->
    <div class="steps">
      <div class="step done">1</div>
      <div class="step-line done"></div>
      <div class="step active">2</div>
      <div class="step-line pending"></div>
      <div class="step pending">3</div>
    </div>
    <p style="font-size:13px;color:var(--muted);margin-bottom:22px">OTP sent to <strong style="color:var(--text)">+91 <?= htmlspecialchars($phone) ?></strong></p>

    <form method="POST" onsubmit="joinOtp();showLoader(this)">
      <input type="hidden" name="action" value="verify_otp"/>
      <input type="hidden" name="otp" id="otpHidden"/>
      <div class="form-group">
        <label>Enter 6-digit OTP</label>
        <div class="otp-row">
          <?php for($i=0;$i<6;$i++): ?>
          <input type="tel" maxlength="1" class="otp-box" id="otp<?=$i?>" pattern="[0-9]" autocomplete="one-time-code" inputmode="numeric"/>
          <?php endfor; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:8px">Verify OTP →</button>
    </form>

    <div class="divider"><span>or</span></div>
    <div style="display:flex;gap:10px">
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="resend_otp"/>
        <button type="submit" class="btn btn-secondary">↺ Resend OTP</button>
      </form>
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="back"/>
        <button type="submit" class="btn btn-secondary">← Change No.</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="footer">
    Trouble logging in? &nbsp;
    <a href="https://www.pw.live/" target="_blank">Visit PW Website</a>
  </div>
</main>

<script>
// Tab switching
function switchTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// OTP boxes
document.querySelectorAll('.otp-box').forEach((box, i, all) => {
  box.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/g,'').slice(-1);
    if (e.target.value && i < all.length - 1) all[i+1].focus();
  });
  box.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !e.target.value && i > 0) all[i-1].focus();
  });
  box.addEventListener('paste', e => {
    const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
    all.forEach((b, j) => { if (paste[j]) b.value = paste[j]; });
    e.preventDefault();
    all[Math.min(paste.length, all.length-1)].focus();
  });
});

function joinOtp() {
  const val = [...document.querySelectorAll('.otp-box')].map(b => b.value).join('');
  document.getElementById('otpHidden').value = val;
}

function showLoader(form) {
  const btn = form.querySelector('button[type=submit]');
  if (btn) btn.innerHTML = '<span class="spinner"></span> Please wait...';
}
</script>
</body>
</html>
