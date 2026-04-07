<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';
session_boot();
require_admin();

// Actions
if (isset($_GET['logout'])) { logout_admin(); header('Location: /admin/index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['del'])) { user_delete($_POST['del']); header('Location: /admin/dashboard.php?msg=deleted'); exit; }
}

$users  = users_load();
$search = trim($_GET['q'] ?? '');
$msg    = $_GET['msg'] ?? '';

// Sort: most recent login first
uasort($users, fn($a,$b) => strcmp($b['last_login'] ?? '', $a['last_login'] ?? ''));

// Filter
$filtered = $users;
if ($search) {
    $filtered = array_filter($users, fn($u) =>
        str_contains(strtolower($u['phone'] ?? ''), strtolower($search)) ||
        str_contains(strtolower($u['name']  ?? ''), strtolower($search))
    );
}

$total       = count($users);
$today       = date('Y-m-d');
$activeToday = count(array_filter($users, fn($u) => str_starts_with($u['last_login'] ?? '', $today)));
$totalTokens = array_sum(array_column($users, 'token_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard — PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#04040c;--s1:#08081a;--s2:#0c0c22;
  --card:#0a0a1e;--card2:#0e0e28;
  --border:#161630;--border2:#202040;
  --accent:#f97316;--accent2:#fb923c;
  --purple:#7c3aed;--blue:#3b82f6;
  --text:#eeeeff;--muted:#55557a;--muted2:#8080aa;
  --success:#22c55e;--danger:#ef4444;--warn:#f59e0b;
  --r:14px;
}
html,body{min-height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text)}

/* Layout */
.layout{display:flex;min-height:100vh}

/* Sidebar */
aside{width:230px;background:var(--s1);border-right:1px solid var(--border);position:fixed;height:100vh;z-index:50;display:flex;flex-direction:column;overflow-y:auto}
.aside-top{padding:22px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.alogo{width:34px;height:34px;background:linear-gradient(135deg,#f97316,#ea580c);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.abrand{font-weight:800;font-size:15px;letter-spacing:-.01em}
.abrand span{color:var(--accent)}
.aside-sect{padding:14px 18px 6px;font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.12em;text-transform:uppercase}
.alink{display:flex;align-items:center;gap:10px;padding:9px 18px;color:var(--muted2);text-decoration:none;font-size:13px;font-weight:600;border-left:2px solid transparent;transition:all .2s}
.alink:hover{color:var(--text);background:rgba(255,255,255,.04)}
.alink.act{color:var(--accent);background:rgba(249,115,22,.08);border-left-color:var(--accent)}
.alink-ic{font-size:15px;width:18px;text-align:center;flex-shrink:0}
.aside-bot{margin-top:auto;padding:18px;border-top:1px solid var(--border)}
.logout-btn{display:flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;color:#fca5a5;text-decoration:none;font-size:13px;font-weight:600;transition:all .2s}
.logout-btn:hover{background:rgba(239,68,68,.18)}
.live-badge{display:flex;align-items:center;gap:6px;margin-top:10px;font-size:11px;color:var(--success);font-family:'JetBrains Mono',monospace}
.live-dot{width:6px;height:6px;border-radius:50%;background:var(--success);animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* Main */
.main{margin-left:230px;flex:1;display:flex;flex-direction:column}

/* Topbar */
.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 30px;height:58px;background:rgba(4,4,12,.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:40;gap:16px}
.tb-title{font-weight:800;font-size:15px;white-space:nowrap}
.tb-search{flex:1;max-width:380px}
.tb-search form{position:relative;display:flex}
.tb-search input{width:100%;padding:9px 14px 9px 36px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:inherit;font-size:13px;outline:none;transition:all .2s}
.tb-search input:focus{border-color:var(--accent);background:rgba(249,115,22,.06)}
.tb-search::before{content:'🔍';position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:13px;pointer-events:none;z-index:1}
.tb-right{display:flex;align-items:center;gap:10px;flex-shrink:0}
.admin-pill{display:flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:100px;font-size:12px;color:#fb923c;font-weight:700}

/* Content */
.content{padding:28px 30px;flex:1}

/* Stats row */
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:28px}
.stat{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:18px;position:relative;overflow:hidden}
.stat::after{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.stat.s-orange::after{background:linear-gradient(90deg,#f97316,#fb923c)}
.stat.s-purple::after{background:linear-gradient(90deg,#7c3aed,#a78bfa)}
.stat.s-blue::after{background:linear-gradient(90deg,#3b82f6,#60a5fa)}
.stat.s-green::after{background:linear-gradient(90deg,#22c55e,#4ade80)}
.stat-n{font-size:28px;font-weight:800;font-family:'JetBrains Mono',monospace;margin-bottom:4px}
.stat-l{font-size:11px;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em}
.stat-ic{position:absolute;top:14px;right:14px;font-size:26px;opacity:.25}

/* Toolbar */
.toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.t-left{font-size:14px;font-weight:700}
.t-right{font-size:12px;color:var(--muted2)}
.t-count{font-family:'JetBrains Mono',monospace;color:var(--accent)}

/* User cards grid */
.ucards{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px}

/* User card */
.ucard{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:border-color .3s}
.ucard:hover{border-color:var(--border2)}
.ucard-head{padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.uava{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#f97316,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0}
.uinfo{flex:1;min-width:0}
.uname{font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.uphone{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted2);margin-top:2px}
.ubadge{padding:3px 8px;border-radius:100px;font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
.badge-active{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#4ade80}
.badge-old{background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--muted2)}

.ucard-body{padding:14px 18px}

/* Token block */
.token-block{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:14px}
.tb-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.tb-label .ticon{color:var(--accent)}
.token-text{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted2);word-break:break-all;line-height:1.5;max-height:44px;overflow:hidden;position:relative;cursor:pointer;transition:color .2s}
.token-text:hover{color:var(--text)}
.token-text.expanded{max-height:none}
.token-actions{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
.tbtn{padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:all .2s;display:inline-flex;align-items:center;gap:4px}
.tbtn-copy{background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);color:#fb923c}
.tbtn-copy:hover{background:rgba(249,115,22,.2)}
.tbtn-copy.copied{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.2);color:#4ade80}
.tbtn-expand{background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--muted2)}
.tbtn-expand:hover{color:var(--text);background:rgba(255,255,255,.09)}

/* Info grid inside card */
.uinfo-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.uig{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:10px 12px}
.uig-v{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:700;color:var(--accent);margin-bottom:2px}
.uig-l{font-size:10px;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em}

/* Time */
.utime{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-bottom:14px}

/* Card footer */
.ucard-foot{padding:10px 18px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.del-btn{padding:6px 12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:7px;color:#fca5a5;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s}
.del-btn:hover{background:rgba(239,68,68,.2)}
.view-front{padding:6px 12px;background:rgba(249,115,22,.08);border:1px solid rgba(249,115,22,.2);border-radius:7px;color:#fb923c;font-size:11px;font-weight:700;text-decoration:none;transition:all .2s}
.view-front:hover{background:rgba(249,115,22,.18)}

/* Alert */
.alert-ok{padding:12px 16px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);border-radius:10px;color:#4ade80;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* Empty */
.empty{text-align:center;padding:60px 20px;border:1px dashed var(--border);border-radius:16px}

/* Responsive */
@media(max-width:900px){aside{display:none}.main{margin-left:0}.ucards{grid-template-columns:1fr}}
@media(max-width:640px){.content{padding:20px 16px}.topbar{padding:0 16px}.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside>
    <div class="aside-top">
      <div class="alogo">🔐</div>
      <div class="abrand">PW<span>Admin</span></div>
    </div>
    <div class="aside-sect">Navigation</div>
    <a href="/admin/dashboard.php" class="alink act"><span class="alink-ic">👥</span>Users & Tokens</a>
    <a href="/dashboard.php" target="_blank" class="alink"><span class="alink-ic">🌐</span>View Frontend</a>
    <a href="/index.php" target="_blank" class="alink"><span class="alink-ic">🔑</span>Login Page</a>
    <div class="aside-bot">
      <a href="?logout=1" class="logout-btn"><span>🚪</span>Logout</a>
      <div class="live-badge"><div class="live-dot"></div>Admin Session Active</div>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">
    <div class="topbar">
      <span class="tb-title">User Management</span>
      <div class="tb-search">
        <form method="GET">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or phone...">
        </form>
      </div>
      <div class="tb-right">
        <div class="admin-pill">🔐 Admin</div>
        <?php if ($search): ?><a href="/admin/dashboard.php" style="padding:6px 12px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--muted2);text-decoration:none;font-size:12px;font-weight:600">✕ Clear</a><?php endif; ?>
      </div>
    </div>

    <div class="content">
      <?php if ($msg === 'deleted'): ?><div class="alert-ok">✓ User deleted successfully.</div><?php endif; ?>

      <!-- Stats -->
      <div class="stats">
        <div class="stat s-orange">
          <div class="stat-n"><?= $total ?></div>
          <div class="stat-l">Total Users</div>
          <div class="stat-ic">👥</div>
        </div>
        <div class="stat s-green">
          <div class="stat-n"><?= $activeToday ?></div>
          <div class="stat-l">Active Today</div>
          <div class="stat-ic">📅</div>
        </div>
        <div class="stat s-purple">
          <div class="stat-n"><?= $totalTokens ?></div>
          <div class="stat-l">Total Tokens</div>
          <div class="stat-ic">🔑</div>
        </div>
        <div class="stat s-blue">
          <div class="stat-n"><?= count($filtered) ?></div>
          <div class="stat-l">Shown</div>
          <div class="stat-ic">🔍</div>
        </div>
      </div>

      <div class="toolbar">
        <span class="t-left">📋 User Registry <span class="t-count">(<?= count($filtered) ?>)</span></span>
        <span class="t-right">Sorted by most recent login · Tokens are Bearer tokens from PW API</span>
      </div>

      <?php if (empty($filtered)): ?>
      <div class="empty">
        <div style="font-size:48px;margin-bottom:12px">🔍</div>
        <div style="font-size:18px;font-weight:800;margin-bottom:8px">No users found</div>
        <p style="color:var(--muted2);font-size:14px">No users have logged in yet or search found nothing.</p>
      </div>
      <?php else: ?>
      <div class="ucards">
        <?php foreach ($filtered as $ph => $u):
          $accessTok  = $u['access_token']  ?? '';
          $refreshTok = $u['refresh_token'] ?? '';
          $uname      = $u['name']          ?? '';
          $loginCnt   = $u['login_count']   ?? 1;
          $tokCnt     = $u['token_count']   ?? 1;
          $batchCnt   = $u['batch_count']   ?? 0;
          $lastLogin  = $u['last_login']    ?? '—';
          $firstLogin = $u['first_login']   ?? '—';
          $isToday    = str_starts_with($lastLogin, $today);
          $initials   = strtoupper(mb_substr($uname ?: $ph, 0, 2));
          $uid        = 'u' . md5($ph);
        ?>
        <div class="ucard">
          <!-- Head -->
          <div class="ucard-head">
            <div class="uava"><?= $initials ?></div>
            <div class="uinfo">
              <div class="uname"><?= htmlspecialchars($uname ?: 'Unknown User') ?></div>
              <div class="uphone">+91 <?= htmlspecialchars($ph) ?></div>
            </div>
            <span class="ubadge <?= $isToday?'badge-active':'badge-old' ?>"><?= $isToday?'🟢 Today':'Inactive' ?></span>
          </div>

          <!-- Body -->
          <div class="ucard-body">
            <!-- Time info -->
            <div class="utime">⏱ Last login: <?= htmlspecialchars($lastLogin) ?></div>

            <!-- Info grid -->
            <div class="uinfo-grid">
              <div class="uig"><div class="uig-v"><?= $loginCnt ?></div><div class="uig-l">Total Logins</div></div>
              <div class="uig"><div class="uig-v"><?= $tokCnt ?></div><div class="uig-l">Total Tokens</div></div>
              <div class="uig"><div class="uig-v"><?= $batchCnt ?: '—' ?></div><div class="uig-l">Batches</div></div>
              <div class="uig"><div class="uig-v" style="font-size:11px;word-break:break-all;color:var(--muted2)"><?= htmlspecialchars(mb_substr($u['email'] ?? '—', 0, 22)) ?></div><div class="uig-l">Email</div></div>
            </div>

            <!-- Access Token -->
            <div class="token-block">
              <div class="tb-label"><span class="ticon">🔑</span>Bearer Access Token</div>
              <?php if ($accessTok): ?>
                <div class="token-text" id="at_<?= $uid ?>" onclick="toggleExpand('at_<?= $uid ?>')">Authorization: Bearer <?= htmlspecialchars($accessTok) ?></div>
                <div class="token-actions">
                  <button class="tbtn tbtn-copy" onclick="copyToken('<?= htmlspecialchars(addslashes($accessTok)) ?>',this)">📋 Copy Token</button>
                  <button class="tbtn tbtn-copy" onclick="copyToken('Bearer <?= htmlspecialchars(addslashes($accessTok)) ?>',this)">📋 Copy Bearer</button>
                  <button class="tbtn tbtn-expand" onclick="toggleExpand('at_<?= $uid ?>')">⤢ Expand</button>
                </div>
              <?php else: ?><div style="font-size:12px;color:var(--muted)">No token stored.</div><?php endif; ?>
            </div>

            <!-- Refresh Token -->
            <?php if ($refreshTok): ?>
            <div class="token-block">
              <div class="tb-label"><span class="ticon">🔄</span>Refresh Token</div>
              <div class="token-text" id="rt_<?= $uid ?>"><?= htmlspecialchars($refreshTok) ?></div>
              <div class="token-actions">
                <button class="tbtn tbtn-copy" onclick="copyToken('<?= htmlspecialchars(addslashes($refreshTok)) ?>',this)">📋 Copy</button>
                <button class="tbtn tbtn-expand" onclick="toggleExpand('rt_<?= $uid ?>')">⤢ Expand</button>
              </div>
            </div>
            <?php endif; ?>

            <!-- Token History -->
            <?php if (!empty($u['tokens']) && count($u['tokens']) > 1): ?>
            <details style="margin-top:6px">
              <summary style="cursor:pointer;font-size:12px;color:var(--muted2);font-weight:600;padding:6px 0">📜 Token History (<?= count($u['tokens']) ?>)</summary>
              <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px">
                <?php foreach (array_slice($u['tokens'],0,5) as $tk): ?>
                <div style="background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;padding:8px 10px">
                  <div style="font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace;margin-bottom:4px"><?= htmlspecialchars($tk['created_at']) ?></div>
                  <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted2);word-break:break-all;line-height:1.4"><?= htmlspecialchars(mb_substr($tk['token'],0,60)) ?>…
                    <button class="tbtn tbtn-copy" style="margin-left:6px" onclick="copyToken('<?= htmlspecialchars(addslashes($tk['token'])) ?>',this)">Copy</button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </details>
            <?php endif; ?>
          </div>

          <!-- Footer -->
          <div class="ucard-foot">
            <form method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars(addslashes($ph)) ?>?')">
              <input type="hidden" name="del" value="<?= htmlspecialchars($ph) ?>">
              <button type="submit" class="del-btn">🗑 Delete User</button>
            </form>
            <span style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted)">First: <?= htmlspecialchars(mb_substr($firstLogin,0,10)) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function copyToken(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML;
    btn.textContent = '✓ Copied!';
    btn.classList.add('copied');
    setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('copied'); }, 2500);
  }).catch(() => {
    // Fallback
    const ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    btn.textContent = '✓ Copied!'; btn.classList.add('copied');
    setTimeout(() => { btn.textContent = '📋 Copy'; btn.classList.remove('copied'); }, 2500);
  });
}

function toggleExpand(id) {
  const el = document.getElementById(id);
  el.classList.toggle('expanded');
}

// Block devtools
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key==='F12'||(e.ctrlKey&&e.shiftKey&&'IJC'.includes(e.key))||(e.ctrlKey&&e.key==='U')) e.preventDefault();
});
</script>
</body>
</html>
