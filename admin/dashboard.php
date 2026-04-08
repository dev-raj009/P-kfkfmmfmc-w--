<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';
session_boot(); require_admin();

if (isset($_GET['logout'])) { do_admin_logout(); header('Location: /admin/index.php'); exit; }

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del'])) {
    user_delete($_POST['del']);
    header('Location: /admin/dashboard.php?msg=deleted');
    exit;
}

// View user detail by UID
$viewUid  = trim($_GET['uid'] ?? '');
$viewUser = $viewUid ? user_get_by_uid($viewUid) : null;

$users   = users_load();
$search  = trim($_GET['q'] ?? '');
$msg     = $_GET['msg'] ?? '';
$today   = date('Y-m-d');

// Sort newest first
uasort($users, fn($a,$b) => strcmp($b['last_login'] ?? '', $a['last_login'] ?? ''));

// Filter
$filtered = $users;
if ($search) {
    $filtered = array_filter($users, fn($u) =>
        str_contains(strtolower($u['phone'] ?? ''), strtolower($search)) ||
        str_contains(strtolower($u['name']  ?? ''), strtolower($search)) ||
        str_contains(strtolower($u['uid']   ?? ''), strtolower($search))
    );
}

$total        = count($users);
$activeToday  = count(array_filter($users, fn($u) => str_starts_with($u['last_login'] ?? '', $today)));
$totalTokens  = array_sum(array_column($users, 'token_count'));
$totalBatches = array_sum(array_column($users, 'batch_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Admin Panel — PW Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
:root{
  --bg:#03030a;--s1:#07071a;--card:#0a0a20;--card2:#0e0e28;
  --b1:#141430;--b2:#1e1e40;--b3:#282855;
  --acc:#f97316;--acc2:#fb923c;
  --text:#f0f0ff;--muted:#6060a0;--m2:#9090c0;--m3:#b0b0d8;
  --ok:#22c55e;--err:#ef4444;--warn:#f59e0b;
  --purple:#7c3aed;--blue:#3b82f6;
}
html,body{min-height:100%;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text)}

.layout{display:flex;min-height:100vh}

/* ── SIDEBAR ── */
aside{width:220px;background:var(--s1);border-right:1px solid var(--b1);position:fixed;height:100vh;z-index:50;display:flex;flex-direction:column;overflow-y:auto;overflow-x:hidden}
.aside-logo{padding:18px 16px;border-bottom:1px solid var(--b1);display:flex;align-items:center;gap:9px}
.al-box{width:32px;height:32px;background:linear-gradient(135deg,#f97316,#ea580c);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.al-txt{font-weight:900;font-size:14px;letter-spacing:-.01em}
.al-txt span{color:#f97316}
.a-sect{padding:12px 16px 5px;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:.12em;text-transform:uppercase}
.al{display:flex;align-items:center;gap:9px;padding:9px 16px;color:var(--m2);text-decoration:none;font-size:13px;font-weight:500;border-left:2px solid transparent;transition:all .2s}
.al:hover{color:var(--text);background:rgba(255,255,255,.04)}
.al.on{color:#f97316;background:rgba(249,115,22,.08);border-left-color:#f97316;font-weight:600}
.al-ic{font-size:14px;width:16px;text-align:center;flex-shrink:0}
.aside-bot{margin-top:auto;padding:16px;border-top:1px solid var(--b1)}
.lo-btn{display:flex;align-items:center;gap:8px;padding:9px 13px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.18);border-radius:9px;color:#fca5a5;text-decoration:none;font-size:12px;font-weight:600;transition:all .2s}
.lo-btn:hover{background:rgba(239,68,68,.16)}
.live{display:flex;align-items:center;gap:6px;margin-top:9px;font-size:10px;color:var(--ok);font-family:'JetBrains Mono',monospace}
.ldot{width:5px;height:5px;border-radius:50%;background:var(--ok);animation:bl 2s infinite}
@keyframes bl{0%,100%{opacity:1}50%{opacity:.3}}

/* ── MAIN ── */
.main{margin-left:220px;flex:1;display:flex;flex-direction:column;min-width:0}

/* topbar */
.tb{height:54px;display:flex;align-items:center;padding:0 24px;gap:12px;background:rgba(3,3,10,.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--b1);position:sticky;top:0;z-index:40}
.tb-ttl{font-weight:800;font-size:14px;white-space:nowrap}
.tb-srch{flex:1;max-width:340px;position:relative}
.tb-srch input{width:100%;padding:8px 12px 8px 34px;background:rgba(255,255,255,.04);border:1px solid var(--b1);border-radius:8px;color:var(--text);font-family:'Inter',sans-serif;font-size:12px;outline:none;transition:all .2s}
.tb-srch input:focus{border-color:#f97316;background:rgba(249,115,22,.05)}
.tb-srch::before{content:'🔍';position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;pointer-events:none}
.tb-r{display:flex;align-items:center;gap:8px;margin-left:auto;flex-shrink:0}
.admin-chip{display:flex;align-items:center;gap:6px;padding:5px 11px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:100px;font-size:11px;color:#fb923c;font-weight:700}
.clr-btn{padding:5px 10px;background:rgba(255,255,255,.05);border:1px solid var(--b1);border-radius:7px;color:var(--muted);text-decoration:none;font-size:11px;font-weight:600;transition:all .2s}
.clr-btn:hover{color:var(--text)}

/* content */
.cnt{padding:22px 24px;flex:1}

/* stats */
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:22px}
.st{background:var(--card);border:1px solid var(--b1);border-radius:12px;padding:16px;position:relative;overflow:hidden}
.st::after{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.st.c1::after{background:linear-gradient(90deg,#f97316,#fb923c)}
.st.c2::after{background:linear-gradient(90deg,#22c55e,#4ade80)}
.st.c3::after{background:linear-gradient(90deg,#7c3aed,#a78bfa)}
.st.c4::after{background:linear-gradient(90deg,#3b82f6,#60a5fa)}
.st-n{font-size:24px;font-weight:900;font-family:'JetBrains Mono',monospace;margin-bottom:3px}
.st-l{font-size:10px;color:var(--m2);text-transform:uppercase;letter-spacing:.05em}
.st-ic{position:absolute;top:12px;right:12px;font-size:22px;opacity:.2}

/* toolbar */
.tbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px}
.tbar-l{font-size:14px;font-weight:700}
.tbar-r{font-size:11px;color:var(--muted)}

/* user cards */
.ucards{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px}

/* user card */
.uc{background:var(--card);border:1.5px solid var(--b1);border-radius:15px;overflow:hidden;transition:border-color .3s}
.uc:hover{border-color:var(--b2)}

.uc-head{padding:14px 16px;border-bottom:1px solid var(--b1);display:flex;align-items:center;gap:11px}
.uc-ava{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#f97316,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:900;flex-shrink:0;cursor:pointer;transition:transform .2s}
.uc-ava:hover{transform:scale(1.1)}
.uc-info{flex:1;min-width:0}
.uc-name{font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.uc-phone{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--m2);margin-top:2px}
.uc-uid{font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--muted)}
.badge{padding:3px 7px;border-radius:100px;font-size:9px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}
.badge-ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#4ade80}
.badge-old{background:rgba(255,255,255,.04);border:1px solid var(--b1);color:var(--muted)}

.uc-body{padding:12px 16px}

/* token block */
.tblock{background:rgba(255,255,255,.025);border:1px solid var(--b1);border-radius:9px;padding:10px 13px;margin-bottom:12px}
.tb-lbl{font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px;display:flex;align-items:center;gap:5px}
.tb-lbl .tic{color:#f97316}
.tok-txt{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--m2);word-break:break-all;line-height:1.5;max-height:40px;overflow:hidden;cursor:pointer;transition:color .2s;position:relative}
.tok-txt:hover{color:var(--text)}
.tok-txt.exp{max-height:none}
.t-acts{display:flex;gap:5px;margin-top:7px;flex-wrap:wrap}
.tbtn{padding:4px 9px;border-radius:6px;font-size:10px;font-weight:700;cursor:pointer;border:none;font-family:'Inter',sans-serif;transition:all .2s;display:inline-flex;align-items:center;gap:3px}
.tbtn-c{background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);color:#fb923c}
.tbtn-c:hover{background:rgba(249,115,22,.2)}
.tbtn-c.cok{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.25);color:#4ade80}
.tbtn-e{background:rgba(255,255,255,.05);border:1px solid var(--b1);color:var(--muted)}
.tbtn-e:hover{color:var(--text);background:rgba(255,255,255,.09)}

/* info grid */
.igrid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:12px}
.ig{background:rgba(255,255,255,.025);border:1px solid var(--b1);border-radius:8px;padding:9px 11px}
.ig-v{font-family:'JetBrains Mono',monospace;font-size:16px;font-weight:700;color:#f97316;margin-bottom:2px}
.ig-l{font-size:9px;color:var(--m2);text-transform:uppercase;letter-spacing:.05em}

.utime{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:5px}

/* detail btn */
.det-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:rgba(124,58,237,.1);border:1px solid rgba(124,58,237,.25);border-radius:7px;color:#a78bfa;font-size:10px;font-weight:700;text-decoration:none;transition:all .2s}
.det-btn:hover{background:rgba(124,58,237,.2)}

.uc-foot{padding:9px 16px;border-top:1px solid var(--b1);display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.del-form{display:inline}
.del-btn{padding:5px 11px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.18);border-radius:7px;color:#fca5a5;font-size:10px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}
.del-btn:hover{background:rgba(239,68,68,.18)}

/* alerts */
.alert-ok{padding:11px 14px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);border-radius:9px;color:#4ade80;font-size:12px;margin-bottom:16px}

/* empty */
.empty{text-align:center;padding:50px 20px;border:1px dashed var(--b1);border-radius:14px}

/* ── USER DETAIL MODAL ── */
.dm{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.9);backdrop-filter:blur(12px);align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto}
.dm.open{display:flex}
.dmbox{background:var(--card);border:1.5px solid var(--b1);border-radius:18px;width:100%;max-width:680px;margin:auto;overflow:hidden;box-shadow:0 40px 80px rgba(0,0,0,.8)}
.dmhead{display:flex;align-items:center;padding:18px 20px;border-bottom:1px solid var(--b1);gap:12px}
.dmava{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#f97316,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;flex-shrink:0}
.dminfo{flex:1;min-width:0}
.dmname{font-size:16px;font-weight:800;margin-bottom:3px}
.dmph{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--m2)}
.dmclose{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.07);border:none;color:var(--m2);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
.dmclose:hover{background:rgba(239,68,68,.2);color:#fca5a5}
.dmbody{padding:20px}
.dm-sect{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;margin-top:18px;display:flex;align-items:center;gap:8px}
.dm-sect:first-child{margin-top:0}
.dm-sect::after{content:'';flex:1;height:1px;background:var(--b1)}
.dm-grid4{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:4px}
.dm-ig{background:rgba(255,255,255,.025);border:1px solid var(--b1);border-radius:9px;padding:11px 13px}
.dm-ig-v{font-family:'JetBrains Mono',monospace;font-size:18px;font-weight:800;color:#f97316;margin-bottom:3px}
.dm-ig-l{font-size:9px;color:var(--m2);text-transform:uppercase;letter-spacing:.05em}
.dm-tok{background:rgba(255,255,255,.025);border:1px solid var(--b1);border-radius:9px;padding:12px 14px;margin-bottom:8px}
.dm-tok-lbl{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;color:#f97316}
.dm-tok-val{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--m3);word-break:break-all;line-height:1.6;user-select:all}
.dm-acts{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
.dm-copy{padding:5px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:7px;color:#fb923c;font-size:11px;font-weight:700;cursor:pointer;border:1px solid rgba(249,115,22,.2);font-family:'Inter',sans-serif;transition:all .2s}
.dm-copy:hover{background:rgba(249,115,22,.2)}
.dm-copy.cok{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.25);color:#4ade80}
.batch-list{display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto}
.batch-item{display:flex;align-items:center;gap:8px;padding:8px 11px;background:rgba(255,255,255,.025);border:1px solid var(--b1);border-radius:8px;font-size:12px}
.batch-ic{font-size:14px;flex-shrink:0}
.batch-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.batch-id{font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--muted)}
.tok-hist{display:flex;flex-direction:column;gap:6px;max-height:180px;overflow-y:auto}
.th-item{background:rgba(255,255,255,.02);border:1px solid var(--b1);border-radius:7px;padding:8px 10px}
.th-time{font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace;margin-bottom:4px}
.th-tok{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--m2);word-break:break-all;line-height:1.4}

@media(max-width:820px){aside{display:none}.main{margin-left:0}}
@media(max-width:640px){.cnt{padding:16px 14px}.tb{padding:0 14px}.stats{grid-template-columns:1fr 1fr}.ucards{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside>
    <div class="aside-logo">
      <div class="al-box">🔐</div>
      <div class="al-txt">PW<span>Admin</span></div>
    </div>
    <div class="a-sect">Navigation</div>
    <a href="/admin/dashboard.php" class="al on"><span class="al-ic">👥</span>Users & Tokens</a>
    <a href="/dashboard.php" target="_blank" class="al"><span class="al-ic">🌐</span>View Frontend</a>
    <a href="/index.php" target="_blank" class="al"><span class="al-ic">🔑</span>Login Page</a>
    <div class="aside-bot">
      <a href="?logout=1" class="lo-btn"><span>🚪</span>Logout Admin</a>
      <div class="live"><div class="ldot"></div>Session Active · Admin: <?= htmlspecialchars(ADMIN_USER) ?></div>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">
    <!-- Topbar -->
    <div class="tb">
      <span class="tb-ttl">User Management</span>
      <div class="tb-srch">
        <form method="GET">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, phone, UID...">
        </form>
      </div>
      <div class="tb-r">
        <div class="admin-chip">🔐 <?= htmlspecialchars(ADMIN_USER) ?></div>
        <?php if ($search): ?><a href="/admin/dashboard.php" class="clr-btn">✕ Clear</a><?php endif; ?>
      </div>
    </div>

    <div class="cnt">
      <?php if ($msg === 'deleted'): ?><div class="alert-ok">✓ User deleted.</div><?php endif; ?>

      <!-- Stats -->
      <div class="stats">
        <div class="st c1"><div class="st-n"><?= $total ?></div><div class="st-l">Total Users</div><div class="st-ic">👥</div></div>
        <div class="st c2"><div class="st-n"><?= $activeToday ?></div><div class="st-l">Active Today</div><div class="st-ic">📅</div></div>
        <div class="st c3"><div class="st-n"><?= $totalTokens ?></div><div class="st-l">Total Tokens</div><div class="st-ic">🔑</div></div>
        <div class="st c4"><div class="st-n"><?= $totalBatches ?></div><div class="st-l">Total Batches</div><div class="st-ic">📚</div></div>
      </div>

      <div class="tbar">
        <span class="tbar-l">📋 User Registry (<?= count($filtered) ?>)</span>
        <span class="tbar-r">Newest login first · Click avatar for full details</span>
      </div>

      <?php if (empty($filtered)): ?>
      <div class="empty">
        <div style="font-size:44px;margin-bottom:12px">🔍</div>
        <div style="font-size:16px;font-weight:800;margin-bottom:6px">No users found</div>
        <p style="color:var(--m2);font-size:12px">No users have logged in yet.</p>
      </div>
      <?php else: ?>
      <div class="ucards">
        <?php foreach ($filtered as $ph => $u):
          $uid      = $u['uid'] ?? 'USR?';
          $uname    = $u['name'] ?? '';
          $aToken   = $u['access_token'] ?? '';
          $rToken   = $u['refresh_token'] ?? '';
          $logCnt   = $u['login_count'] ?? 1;
          $tokCnt   = $u['token_count'] ?? 1;
          $bCnt     = $u['batch_count'] ?? 0;
          $lastL    = $u['last_login']  ?? '—';
          $isToday  = str_starts_with($lastL, $today);
          $init     = strtoupper(mb_substr($uname ?: $ph, 0, 2));
          $eid      = 'u' . md5($ph);
          $juid     = json_encode($uid);
        ?>
        <div class="uc">
          <div class="uc-head">
            <div class="uc-ava" onclick="openDetail('<?= htmlspecialchars(addslashes($ph)) ?>')" title="View full details"><?= $init ?></div>
            <div class="uc-info">
              <div class="uc-name"><?= htmlspecialchars($uname ?: 'Unknown User') ?></div>
              <div class="uc-phone">+91 <?= htmlspecialchars($ph) ?></div>
              <div class="uc-uid"><?= htmlspecialchars($uid) ?></div>
            </div>
            <span class="badge <?= $isToday?'badge-ok':'badge-old' ?>"><?= $isToday?'🟢 Today':'Inactive' ?></span>
          </div>

          <div class="uc-body">
            <div class="utime">⏱ Last: <?= htmlspecialchars($lastL) ?></div>

            <div class="igrid">
              <div class="ig"><div class="ig-v"><?= $logCnt ?></div><div class="ig-l">Logins</div></div>
              <div class="ig"><div class="ig-v"><?= $tokCnt ?></div><div class="ig-l">Tokens</div></div>
              <div class="ig"><div class="ig-v"><?= $bCnt ?: '—' ?></div><div class="ig-l">Batches</div></div>
              <div class="ig"><div class="ig-v" style="font-size:11px;color:var(--m2)"><?= htmlspecialchars(mb_substr($u['email'] ?? '—', 0, 16)) ?></div><div class="ig-l">Email</div></div>
            </div>

            <!-- Bearer Token -->
            <div class="tblock">
              <div class="tb-lbl"><span class="tic">🔑</span>Bearer Access Token</div>
              <?php if ($aToken): ?>
              <div class="tok-txt" id="at_<?= $eid ?>" onclick="toggleExp(this)">Authorization: Bearer <?= htmlspecialchars($aToken) ?></div>
              <div class="t-acts">
                <button class="tbtn tbtn-c" onclick="cpTok('<?= htmlspecialchars(addslashes($aToken)) ?>',this)">📋 Copy Token</button>
                <button class="tbtn tbtn-c" onclick="cpTok('Bearer <?= htmlspecialchars(addslashes($aToken)) ?>',this)">📋 Copy Bearer</button>
                <button class="tbtn tbtn-e" onclick="toggleExp(document.getElementById('at_<?= $eid ?>'))">⤢ Expand</button>
              </div>
              <?php else: ?><div style="font-size:11px;color:var(--muted)">No token yet.</div><?php endif; ?>
            </div>
          </div>

          <div class="uc-foot">
            <form class="del-form" method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars(addslashes($ph)) ?>?')">
              <input type="hidden" name="del" value="<?= htmlspecialchars($ph) ?>">
              <button type="submit" class="del-btn">🗑 Delete</button>
            </form>
            <a href="#" class="det-btn" onclick="openDetail('<?= htmlspecialchars(addslashes($ph)) ?>');return false">🔍 Full Details</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Detail Modal -->
<div class="dm" id="detModal">
  <div class="dmbox">
    <div class="dmhead">
      <div class="dmava" id="dm-ava">?</div>
      <div class="dminfo">
        <div class="dmname" id="dm-name">Loading...</div>
        <div class="dmph" id="dm-ph">—</div>
      </div>
      <button class="dmclose" onclick="closeDet()">✕</button>
    </div>
    <div class="dmbody" id="dm-body">Loading...</div>
  </div>
</div>

<script>
// All user data (safe for JS — no secrets in HTML source)
const USERS = <?php
  $safe = [];
  foreach ($users as $ph => $u) {
      $safe[$ph] = [
          'uid'           => $u['uid']          ?? '',
          'name'          => $u['name']         ?? '',
          'email'         => $u['email']        ?? '',
          'phone'         => $ph,
          'access_token'  => $u['access_token'] ?? '',
          'refresh_token' => $u['refresh_token'] ?? '',
          'token_history' => $u['token_history'] ?? [],
          'login_count'   => $u['login_count']  ?? 1,
          'token_count'   => $u['token_count']  ?? 1,
          'batch_count'   => $u['batch_count']  ?? 0,
          'batches'       => $u['batches']      ?? [],
          'last_login'    => $u['last_login']   ?? '',
          'first_login'   => $u['first_login']  ?? '',
          'email'         => $u['email']        ?? '',
      ];
  }
  echo json_encode($safe);
?>;

function openDetail(phone) {
  const u = USERS[phone];
  if (!u) return;

  document.getElementById('dm-ava').textContent = (u.name || phone).substring(0,2).toUpperCase();
  document.getElementById('dm-name').textContent = u.name || 'Unknown User';
  document.getElementById('dm-ph').textContent   = '+91 ' + phone + '  ·  UID: ' + u.uid;

  let html = '';

  // Stats
  html += '<div class="dm-sect">Statistics</div>';
  html += '<div class="dm-grid4">';
  html += `<div class="dm-ig"><div class="dm-ig-v">${u.login_count}</div><div class="dm-ig-l">Total Logins</div></div>`;
  html += `<div class="dm-ig"><div class="dm-ig-v">${u.token_count}</div><div class="dm-ig-l">Tokens</div></div>`;
  html += `<div class="dm-ig"><div class="dm-ig-v">${u.batch_count||'—'}</div><div class="dm-ig-l">Batches</div></div>`;
  html += `<div class="dm-ig"><div class="dm-ig-v" style="font-size:11px;color:var(--m2)">${u.email||'—'}</div><div class="dm-ig-l">Email</div></div>`;
  html += `<div class="dm-ig"><div class="dm-ig-v" style="font-size:11px">${u.last_login||'—'}</div><div class="dm-ig-l">Last Login</div></div>`;
  html += `<div class="dm-ig"><div class="dm-ig-v" style="font-size:11px">${u.first_login||'—'}</div><div class="dm-ig-l">First Login</div></div>`;
  html += '</div>';

  // Access Token
  html += '<div class="dm-sect">Bearer Access Token</div>';
  html += '<div class="dm-tok">';
  html += '<div class="dm-tok-lbl">🔑 Authorization Header</div>';
  const fullBearer = 'Authorization: Bearer ' + u.access_token;
  html += `<div class="dm-tok-val" id="dm-at">${escHtml(fullBearer)}</div>`;
  html += `<div class="dm-acts">
    <button class="dm-copy" onclick="cpTok(${JSON.stringify(u.access_token)},this)">📋 Copy Token</button>
    <button class="dm-copy" onclick="cpTok('Bearer ' + ${JSON.stringify(u.access_token)},this)">📋 Copy Bearer</button>
    <button class="dm-copy" onclick="cpTok(${JSON.stringify(fullBearer)},this)">📋 Copy Full Header</button>
  </div>`;
  html += '</div>';

  // Refresh Token
  if (u.refresh_token) {
    html += '<div class="dm-tok">';
    html += '<div class="dm-tok-lbl">🔄 Refresh Token</div>';
    html += `<div class="dm-tok-val">${escHtml(u.refresh_token)}</div>`;
    html += `<div class="dm-acts"><button class="dm-copy" onclick="cpTok(${JSON.stringify(u.refresh_token)},this)">📋 Copy</button></div>`;
    html += '</div>';
  }

  // Batches
  if (u.batches && u.batches.length) {
    html += '<div class="dm-sect">Enrolled Batches (' + u.batches.length + ')</div>';
    html += '<div class="batch-list">';
    u.batches.forEach((b,i) => {
      const icons = ['🧪','📐','🌍','📊','⚗','🔬','💡','🧮','📚','🏛'];
      html += `<div class="batch-item"><span class="batch-ic">${icons[i%icons.length]}</span><span class="batch-name">${escHtml(b.name||'Unknown')}</span><span class="batch-id">${escHtml((b._id||'').substring(0,18))}</span></div>`;
    });
    html += '</div>';
  }

  // Token history
  if (u.token_history && u.token_history.length > 1) {
    html += '<div class="dm-sect">Token History (' + u.token_history.length + ')</div>';
    html += '<div class="tok-hist">';
    u.token_history.slice(0,8).forEach(t => {
      html += `<div class="th-item">
        <div class="th-time">🕐 ${escHtml(t.time||'—')} · Batches: ${t.batches||0}</div>
        <div class="th-tok">${escHtml((t.token||'').substring(0,80))}… <button class="dm-copy" style="display:inline-block;margin-left:5px;padding:2px 7px" onclick="cpTok(${JSON.stringify(t.token)},this)">Copy</button></div>
      </div>`;
    });
    html += '</div>';
  }

  document.getElementById('dm-body').innerHTML = html;
  document.getElementById('detModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDet() {
  document.getElementById('detModal').classList.remove('open');
  document.body.style.overflow = '';
}

document.getElementById('detModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDet(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDet(); });

function escHtml(t) {
  return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function cpTok(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML;
    btn.textContent = '✓ Copied!';
    btn.classList.add('cok');
    setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('cok'); }, 2500);
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta); ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
    btn.textContent = '✓ Copied!'; btn.classList.add('cok');
    setTimeout(() => { btn.textContent = '📋 Copy'; btn.classList.remove('cok'); }, 2500);
  });
}

function toggleExp(el) {
  el.classList.toggle('exp');
}

// Block devtools
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key==='F12'||(e.ctrlKey&&e.shiftKey&&'IJC'.includes(e.key))||(e.ctrlKey&&e.key==='U')) e.preventDefault();
});
</script>
</body>
</html>
