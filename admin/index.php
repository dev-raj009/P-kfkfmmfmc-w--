<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
session_boot();

if (is_admin()) { header('Location: /admin/dashboard.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['u'] ?? '');
    $p = trim($_POST['p'] ?? '');
    if (hash_equals(ADMIN_USER, $u) && hash_equals(ADMIN_PASS_HASH, hash('sha256', $p))) {
        do_admin_login();
        header('Location: /admin/dashboard.php');
        exit;
    }
    sleep(1);
    $err = 'Wrong credentials. Access denied.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Admin Login — PW Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#03030a;--card:#07071a;--b1:#141430;--acc:#f97316;--text:#f0f0ff;--muted:#6060a0;--m2:#9090c0}
html,body{min-height:100%;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);overflow:hidden}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
canvas{position:fixed;inset:0;z-index:0;opacity:.15}
.vig{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse at center,transparent 30%,rgba(3,3,10,.97) 100%)}
.cgl{position:fixed;pointer-events:none;z-index:0}
.cg1{top:0;left:0;width:280px;height:280px;background:radial-gradient(circle at 0 0,rgba(249,115,22,.2),transparent 70%)}
.cg2{bottom:0;right:0;width:280px;height:280px;background:radial-gradient(circle at 100% 100%,rgba(124,58,237,.15),transparent 70%)}
.card{position:relative;z-index:1;width:100%;max-width:380px;background:rgba(7,7,26,.97);border:1px solid var(--b1);border-radius:18px;padding:32px;box-shadow:0 0 80px rgba(0,0,0,.9),0 0 0 1px rgba(249,115,22,.04) inset}
.top{text-align:center;margin-bottom:24px}
.shield{width:52px;height:52px;background:linear-gradient(135deg,rgba(249,115,22,.18),rgba(239,68,68,.12));border:1px solid rgba(249,115,22,.3);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px}
.ttl{font-size:21px;font-weight:900;letter-spacing:-.02em;margin-bottom:3px}
.sub{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:.12em;text-transform:uppercase}
.cur{display:inline-block;width:7px;height:13px;background:#f97316;animation:bl 1s step-end infinite;vertical-align:text-bottom;margin-left:1px}
@keyframes bl{0%,100%{opacity:1}50%{opacity:0}}
.field{margin-bottom:14px}
label{display:block;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:7px}
input[type=text],input[type=password]{width:100%;padding:12px 14px;background:rgba(255,255,255,.04);border:1.5px solid var(--b1);border-radius:10px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:14px;outline:none;transition:all .2s;-webkit-appearance:none}
input:focus{border-color:#f97316;background:rgba(249,115,22,.07);box-shadow:0 0 0 3px rgba(249,115,22,.12)}
input::placeholder{color:var(--muted)}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#f97316,#ea580c);border:none;border-radius:10px;color:#fff;font-family:'Inter',sans-serif;font-size:14px;font-weight:800;letter-spacing:.04em;cursor:pointer;transition:all .25s;margin-top:4px}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(249,115,22,.5)}
.btn:active{transform:translateY(0)}
.err{padding:11px 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:9px;color:#fca5a5;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.sbar{margin-top:18px;padding:10px 13px;background:rgba(255,255,255,.02);border:1px solid var(--b1);border-radius:8px;display:flex;align-items:center;gap:7px;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted)}
.sdt{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:p 2s ease infinite}
@keyframes p{0%,100%{opacity:1}50%{opacity:.3}}
.spin{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:rot .7s linear infinite;vertical-align:middle}
@keyframes rot{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="vig"></div>
<div class="cgl cg1"></div>
<div class="cgl cg2"></div>

<div class="card">
  <div class="top">
    <div class="shield">🔐</div>
    <div class="ttl">Admin Panel</div>
    <div class="sub">PW PORTAL · SECURE ZONE<span class="cur"></span></div>
  </div>

  <?php if ($err): ?><div class="err">⛔ <?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if (!empty($_GET['e'])): ?><div class="err">⛔ Session expired. Please login again.</div><?php endif; ?>

  <form method="POST" onsubmit="go(this)">
    <div class="field">
      <label>Username</label>
      <input type="text" name="u" placeholder="raj" required autocomplete="username">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="p" placeholder="••••••••••" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn">ACCESS PANEL →</button>
  </form>

  <div class="sbar"><div class="sdt"></div>All actions are logged. Unauthorized access is prohibited.</div>
</div>

<script>
// Matrix rain
const c = document.getElementById('c');
const ctx = c.getContext('2d');
const fit = () => { c.width = innerWidth; c.height = innerHeight; };
fit(); window.addEventListener('resize', fit);
const ch = '01アイウエオカキクケ0101';
const cols = Math.floor(innerWidth / 18);
const dr = Array(cols).fill(1);
setInterval(() => {
  ctx.fillStyle = 'rgba(3,3,10,.07)';
  ctx.fillRect(0,0,c.width,c.height);
  dr.forEach((y,i) => {
    ctx.fillStyle = i%5===0 ? '#f97316' : '#3a2008';
    ctx.font = '13px monospace';
    ctx.fillText(ch[Math.floor(Math.random()*ch.length)], i*18, y*18);
    if (y*18 > c.height && Math.random() > .975) dr[i] = 0;
    dr[i]++;
  });
}, 60);

function go(f) {
  const btn = f.querySelector('.btn');
  btn.innerHTML = '<span class="spin"></span> Authenticating...';
  btn.disabled = true;
}
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key==='F12'||(e.ctrlKey&&e.shiftKey&&'IJC'.includes(e.key))||(e.ctrlKey&&e.key==='U')) e.preventDefault();
});
</script>
</body>
</html>
