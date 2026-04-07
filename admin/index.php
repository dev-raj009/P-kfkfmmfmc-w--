<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
session_boot();

if (is_admin()) { header('Location: /admin/dashboard.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u  = $_POST['u'] ?? '';
    $p  = $_POST['p'] ?? '';
    $ok = hash_equals(ADMIN_USER, $u) && hash_equals(ADMIN_PASS_HASH, hash('sha256', $p));
    if ($ok) {
        login_admin();
        header('Location: /admin/dashboard.php');
        exit;
    }
    sleep(1);
    $err = 'Invalid credentials. Access denied.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin — Secure Access | PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#04040c;--card:#08081a;--border:#161630;--accent:#f97316;--text:#eeeeff;--muted:#55557a;--r:12px}
html,body{min-height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);overflow:hidden}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}

/* Matrix-style bg */
.bg{position:fixed;inset:0;z-index:0;pointer-events:none}
canvas{position:absolute;inset:0;opacity:.18}
.vignette{position:absolute;inset:0;background:radial-gradient(ellipse at center,transparent 40%,rgba(4,4,12,.95) 100%)}
.corner-glow{position:absolute;pointer-events:none}
.cg1{top:0;left:0;width:300px;height:300px;background:radial-gradient(circle at 0 0,rgba(249,115,22,.18),transparent 70%)}
.cg2{bottom:0;right:0;width:300px;height:300px;background:radial-gradient(circle at 100% 100%,rgba(124,58,237,.15),transparent 70%)}

/* Scanlines */
body::after{content:'';position:fixed;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.05) 3px,rgba(0,0,0,.05) 4px);pointer-events:none;z-index:0}

.card{position:relative;z-index:1;width:100%;max-width:390px;background:rgba(8,8,26,.92);backdrop-filter:blur(24px);border:1px solid var(--border);border-radius:20px;padding:36px;box-shadow:0 0 60px rgba(0,0,0,.8),0 0 0 1px rgba(249,115,22,.04) inset}

.top{text-align:center;margin-bottom:28px}
.shield{width:56px;height:56px;background:linear-gradient(135deg,rgba(249,115,22,.2),rgba(239,68,68,.15));border:1px solid rgba(249,115,22,.3);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 14px}
.title{font-size:22px;font-weight:800;letter-spacing:-.02em;margin-bottom:4px}
.sub{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:.15em;text-transform:uppercase}

label{display:block;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px}
.field{margin-bottom:16px}
input[type=text],input[type=password]{width:100%;padding:12px 14px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:14px;outline:none;transition:all .2s}
input:focus{border-color:#f97316;background:rgba(249,115,22,.07);box-shadow:0 0 0 3px rgba(249,115,22,.12)}
input::placeholder{color:var(--muted)}

.btn{width:100%;padding:13px;background:linear-gradient(135deg,#f97316,#ea580c);border:none;border-radius:10px;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;letter-spacing:.04em;cursor:pointer;transition:all .25s;margin-top:6px}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(249,115,22,.5)}
.btn:active{transform:translateY(0)}

.err{padding:12px 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:10px;color:#fca5a5;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px}

.sec-bar{margin-top:20px;padding:10px 14px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:8px;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:.03em}
.sec-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:bl 2s infinite}
@keyframes bl{0%,100%{opacity:1}50%{opacity:.3}}

.spin{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:rot .7s linear infinite;vertical-align:middle}
@keyframes rot{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="bg">
  <canvas id="c"></canvas>
  <div class="vignette"></div>
  <div class="corner-glow cg1"></div>
  <div class="corner-glow cg2"></div>
</div>

<div class="card">
  <div class="top">
    <div class="shield">🔐</div>
    <div class="title">Admin Panel</div>
    <div class="sub">PW Portal · Restricted Zone</div>
  </div>

  <?php if ($err): ?><div class="err">⛔ <?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="err">⛔ Session expired. Please login again.</div><?php endif; ?>

  <form method="POST" onsubmit="showSpin(this)">
    <div class="field">
      <label>Username</label>
      <input type="text" name="u" placeholder="admin" required autocomplete="username">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="p" placeholder="••••••••••••" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn">ACCESS PANEL →</button>
  </form>

  <div class="sec-bar"><div class="sec-dot"></div>Encrypted · Session-protected · Activity logged</div>
</div>

<script>
// Matrix rain
const c = document.getElementById('c');
const ctx = c.getContext('2d');
const resize = () => { c.width = innerWidth; c.height = innerHeight; };
resize(); window.addEventListener('resize', resize);
const chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノ';
const cols = Math.floor(innerWidth / 16);
const drops = Array(cols).fill(1);
setInterval(() => {
  ctx.fillStyle = 'rgba(4,4,12,.08)';
  ctx.fillRect(0,0,c.width,c.height);
  ctx.fillStyle = '#f97316';
  ctx.font = '13px JetBrains Mono,monospace';
  drops.forEach((y,i) => {
    const ch = chars[Math.floor(Math.random()*chars.length)];
    ctx.fillStyle = i%7===0 ? '#f97316' : '#4a2010';
    ctx.fillText(ch, i*16, y*16);
    if (y*16 > c.height && Math.random() > .975) drops[i] = 0;
    drops[i]++;
  });
}, 50);

function showSpin(f) {
  const btn = f.querySelector('button');
  btn.innerHTML = '<span class="spin"></span> Authenticating...';
  btn.disabled = true;
}

// Block devtools
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key==='F12'||(e.ctrlKey&&e.shiftKey&&'IJC'.includes(e.key))||(e.ctrlKey&&e.key==='U')) e.preventDefault();
});
</script>
</body>
</html>
