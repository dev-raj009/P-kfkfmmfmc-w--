<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

require_admin();

// Logout
if (($_GET['logout'] ?? '') === '1') {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_login_time']);
    header('Location: /admin/index.php');
    exit;
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $phone = $_POST['delete_user'];
    $users = users_load();
    unset($users[$phone]);
    users_save($users);
    header('Location: /admin/dashboard.php?msg=User+deleted');
    exit;
}

// Refresh batch count for a user (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_batches'])) {
    $phone = $_POST['refresh_batches'];
    $users = users_load();
    if (isset($users[$phone])) {
        $token = $users[$phone]['access_token'] ?? '';
        if ($token) {
            $count = pw_get_batch_count($token);
            $users[$phone]['batch_count'] = $count;
            $users[$phone]['batch_count_updated'] = date('Y-m-d H:i:s');
            users_save($users);
        }
    }
    header('Location: /admin/dashboard.php?msg=Batch+count+updated');
    exit;
}

$users   = users_load();
$total   = count($users);
$search  = trim($_GET['q'] ?? '');
$msg     = htmlspecialchars($_GET['msg'] ?? '');

// Aggregate stats
$totalLogins  = array_sum(array_column($users, 'login_count'));
$totalTokens  = count(array_filter($users, fn($u) => !empty($u['access_token'])));
$today        = date('Y-m-d');
$activeToday  = count(array_filter($users, fn($u) => str_starts_with($u['last_login'] ?? '', $today)));

// Filter
$filtered = $users;
if ($search) {
    $filtered = array_filter($users, fn($u) =>
        str_contains(strtolower($u['phone'] ?? ''), strtolower($search)) ||
        str_contains(strtolower($u['name'] ?? ''), strtolower($search))
    );
}

// Sort by last login
uasort($filtered, fn($a, $b) => strcmp($b['last_login'] ?? '', $a['last_login'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin Panel — PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#06060e;--surface:#0a0a16;--card:#0e0e1c;--border:#181826;
  --accent:#f97316;--text:#e8e8ff;--muted:#545472;
  --success:#22c55e;--danger:#ef4444;--radius:12px;
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* ── Sidebar ── */
.layout{display:flex;min-height:100vh}
aside{width:240px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;height:100vh;z-index:50}
.aside-logo{padding:20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.logo-box{width:36px;height:36px;background:linear-gradient(135deg,#f97316,#ea580c);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.logo-text{font-family:'Syne',sans-serif;font-size:15px;font-weight:800}
.logo-text span{color:var(--accent)}
.aside-label{padding:16px 20px 8px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.1em}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--muted);text-decoration:none;font-size:14px;transition:all .2s;border-left:3px solid transparent}
.nav-item:hover{color:var(--text);background:rgba(255,255,255,.04)}
.nav-item.active{color:var(--accent);background:rgba(249,115,22,.08);border-left-color:var(--accent)}
.nav-icon{font-size:16px;width:20px;text-align:center}
.aside-footer{margin-top:auto;padding:20px;border-top:1px solid var(--border)}
.logout-link{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;color:#fca5a5;text-decoration:none;font-size:13px;transition:all .2s}
.logout-link:hover{background:rgba(239,68,68,.15)}

/* ── Main ── */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column}
.topbar{padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);background:rgba(6,6,14,.95);backdrop-filter:blur(16px);position:sticky;top:0;z-index:40}
.topbar-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700}
.admin-chip{display:flex;align-items:center;gap:8px;padding:6px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);border-radius:100px;font-size:12px;color:#fb923c}
.dot-live{width:7px;height:7px;border-radius:50%;background:var(--success);animation:blink 2s step-end infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.content{padding:24px 28px;flex:1}

/* ── Stats Grid ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:28px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),#7c3aed)}
.stat-card.green::after{background:linear-gradient(90deg,#22c55e,#16a34a)}
.stat-card.blue::after{background:linear-gradient(90deg,#0ea5e9,#6366f1)}
.stat-num{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;margin-bottom:3px}
.stat-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.stat-icon{position:absolute;top:14px;right:14px;font-size:22px;opacity:.25}

/* ── Search Toolbar ── */
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.search-box{flex:1;min-width:200px;position:relative}
.search-box input{width:100%;padding:10px 14px 10px 38px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s}
.search-box input:focus{border-color:var(--accent);background:rgba(249,115,22,.05)}
.search-box::before{content:'🔍';position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px}

/* ── Table ── */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.table-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700}
table{width:100%;border-collapse:collapse}
thead tr{background:rgba(255,255,255,.03)}
th{padding:11px 14px;text-align:left;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;border-bottom:1px solid var(--border);white-space:nowrap}
tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(249,115,22,.04)}
td{padding:12px 14px;font-size:13px;vertical-align:middle}

/* User cell */
.user-cell{display:flex;align-items:center;gap:10px}
.user-ava{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#f97316,#7c3aed);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;flex-shrink:0;color:#fff}
.user-name-main{font-weight:500;margin-bottom:2px}
.user-phone-sub{font-size:11px;color:var(--muted);font-family:'DM Mono',monospace}

/* Token */
.token-wrap{display:flex;align-items:center;gap:6px}
.token-text{font-family:'DM Mono',monospace;font-size:10px;color:var(--muted);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:rgba(255,255,255,.04);padding:3px 7px;border-radius:5px;border:1px solid var(--border);cursor:pointer;transition:all .2s;user-select:all}
.token-text:hover{border-color:var(--accent);color:var(--accent)}
.copy-btn{padding:3px 7px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:5px;color:#fb923c;font-size:10px;cursor:pointer;font-family:'DM Mono',monospace;white-space:nowrap;transition:all .2s}
.copy-btn:hover{background:rgba(249,115,22,.2)}
.copy-btn.copied{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#86efac}

/* Badges */
.badge-count{display:inline-block;padding:2px 8px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:100px;font-size:11px;color:#fb923c;font-family:'DM Mono',monospace}
.badge-batch{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;background:rgba(14,165,233,.1);border:1px solid rgba(14,165,233,.2);border-radius:100px;font-size:11px;color:#7dd3fc;font-family:'DM Mono',monospace}
.badge-na{color:var(--muted);font-size:11px;font-family:'DM Mono',monospace}

/* Login time */
.login-time{font-size:11px;font-family:'DM Mono',monospace;color:var(--muted)}
.login-time-main{color:var(--text);font-size:12px}

/* Actions */
.del-btn{padding:4px 9px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:6px;color:#fca5a5;font-size:11px;cursor:pointer;transition:all .2s}
.del-btn:hover{background:rgba(239,68,68,.2)}
.refresh-btn{padding:4px 9px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:6px;color:#86efac;font-size:11px;cursor:pointer;transition:all .2s;margin-bottom:4px}
.refresh-btn:hover{background:rgba(34,197,94,.15)}

.empty-row td{text-align:center;padding:60px 20px;color:var(--muted)}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}

/* ── Token Modal ── */
.modal{display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.8);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:20px}
.modal.open{display:flex}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;max-width:580px;width:100%}
.modal-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between}
.modal-close{cursor:pointer;color:var(--muted);font-size:20px;background:none;border:none}
.modal-token{font-family:'DM Mono',monospace;font-size:11px;word-break:break-all;background:rgba(255,255,255,.04);padding:14px;border-radius:8px;border:1px solid var(--border);color:var(--text);margin-bottom:14px;line-height:1.7;user-select:all;max-height:160px;overflow-y:auto}
.modal-copy{padding:10px 20px;background:var(--accent);border:none;border-radius:8px;color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:13px;cursor:pointer;transition:all .2s}

/* ── User Detail Panel ── */
.detail-panel{display:none;position:fixed;inset:0;z-index:900;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:20px}
.detail-panel.open{display:flex}
.detail-box{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;max-width:520px;width:100%;max-height:85vh;overflow-y:auto}
.detail-box h2{font-family:'Syne',sans-serif;font-size:16px;font-weight:800;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center}
.detail-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;gap:10px}
.detail-row:last-child{border-bottom:none}
.detail-key{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding-top:2px}
.detail-val{font-family:'DM Mono',monospace;font-size:12px;text-align:right;word-break:break-all;max-width:300px}

@media(max-width:900px){aside{display:none}.main{margin-left:0}}
@media(max-width:600px){.content{padding:16px}.stats-grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <aside>
    <div class="aside-logo">
      <div class="logo-box">🔐</div>
      <div class="logo-text">PW<span>Admin</span></div>
    </div>
    <div class="aside-label">Menu</div>
    <a href="/admin/dashboard.php" class="nav-item active"><span class="nav-icon">👥</span>Users & Tokens</a>
    <a href="/dashboard.php" target="_blank" class="nav-item"><span class="nav-icon">🌐</span>View Frontend</a>
    <div class="aside-footer">
      <a href="?logout=1" class="logout-link"><span>🚪</span>Logout Admin</a>
    </div>
  </aside>

  <div class="main">
    <!-- Topbar -->
    <div class="topbar">
      <span class="topbar-title">Admin Dashboard</span>
      <div class="admin-chip"><div class="dot-live"></div>Logged in as Admin</div>
    </div>

    <div class="content">
      <?php if ($msg): ?>
        <div class="alert alert-success">✓ <?= $msg ?></div>
      <?php endif; ?>

      <!-- ── Stats ── -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-num"><?= $total ?></div>
          <div class="stat-label">Total Users</div>
          <div class="stat-icon">👥</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-num"><?= $totalTokens ?></div>
          <div class="stat-label">Active Tokens</div>
          <div class="stat-icon">🔑</div>
        </div>
        <div class="stat-card">
          <div class="stat-num"><?= $totalLogins ?></div>
          <div class="stat-label">Total Logins</div>
          <div class="stat-icon">🔢</div>
        </div>
        <div class="stat-card green">
          <div class="stat-num"><?= $activeToday ?></div>
          <div class="stat-label">Active Today</div>
          <div class="stat-icon">📅</div>
        </div>
      </div>

      <!-- ── Toolbar ── -->
      <div class="toolbar">
        <form method="GET" style="flex:1;display:flex;gap:10px">
          <div class="search-box">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or phone..."/>
          </div>
          <button type="submit" style="padding:10px 16px;background:var(--accent);border:none;border-radius:8px;color:#fff;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;cursor:pointer">Search</button>
          <?php if ($search): ?>
            <a href="/admin/dashboard.php" style="padding:10px 14px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;color:var(--muted);text-decoration:none;font-size:13px">Clear</a>
          <?php endif; ?>
        </form>
        <span style="font-size:12px;color:var(--muted);white-space:nowrap"><?= count($filtered) ?> of <?= $total ?> users</span>
      </div>

      <!-- ── Table ── -->
      <div class="table-wrap">
        <div class="table-head">
          <span class="table-title">User Registry & Tokens</span>
          <span style="font-size:11px;color:var(--muted);font-family:'DM Mono',monospace">🔒 Secure View</span>
        </div>
        <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Access Token</th>
              <th>Last Login</th>
              <th>Logins</th>
              <th>Batches</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($filtered)): ?>
              <tr class="empty-row"><td colspan="7">No users found</td></tr>
            <?php else: $i = 1; foreach ($filtered as $phone => $u): ?>
            <?php
              $at = $u['access_token'] ?? '';
              $name = $u['name'] ?: $phone;
              $batchCount = $u['batch_count'] ?? null;
              $batchUpdated = $u['batch_count_updated'] ?? null;
              $lastLogin = $u['last_login'] ?? '—';
              $loginCount = (int)($u['login_count'] ?? 1);
            ?>
            <tr>
              <td style="color:var(--muted);font-family:'DM Mono',monospace;font-size:11px"><?= $i++ ?></td>
              <td>
                <div class="user-cell">
                  <div class="user-ava"><?= strtoupper(mb_substr($u['name'] ?: 'U', 0, 1)) ?></div>
                  <div>
                    <div class="user-name-main"><?= htmlspecialchars($u['name'] ?: '—') ?></div>
                    <div class="user-phone-sub"><?= htmlspecialchars($phone) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($at): ?>
                <div class="token-wrap">
                  <span class="token-text" title="Click View for full token"><?= htmlspecialchars(substr($at,0,28)) ?>…</span>
                  <button class="copy-btn" onclick="showToken('<?= htmlspecialchars(addslashes($at)) ?>','<?= htmlspecialchars(addslashes($name)) ?>')">View</button>
                  <button class="copy-btn" onclick="copyText('<?= htmlspecialchars(addslashes($at)) ?>',this)">Copy</button>
                </div>
                <?php else: ?><span class="badge-na">—</span><?php endif; ?>
              </td>
              <td>
                <div class="login-time-main"><?= htmlspecialchars($lastLogin) ?></div>
              </td>
              <td><span class="badge-count"><?= $loginCount ?></span></td>
              <td>
                <?php if ($batchCount !== null): ?>
                  <span class="badge-batch">📚 <?= $batchCount ?></span>
                  <?php if ($batchUpdated): ?>
                    <div style="font-size:9px;color:var(--muted);margin-top:3px;font-family:'DM Mono',monospace"><?= $batchUpdated ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge-na">Not fetched</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;flex-direction:column;gap:4px">
                  <button class="copy-btn" onclick="showDetail(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">📋 Detail</button>
                  <?php if ($at): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="refresh_batches" value="<?= htmlspecialchars($phone) ?>"/>
                    <button type="submit" class="refresh-btn">🔄 Batches</button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($phone)) ?>?')">
                    <input type="hidden" name="delete_user" value="<?= htmlspecialchars($phone) ?>"/>
                    <button type="submit" class="del-btn">🗑 Delete</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div>

<!-- ── Token Modal ── -->
<div class="modal" id="tokenModal">
  <div class="modal-box">
    <div class="modal-title">
      <span>🔑 Token — <span id="modalUserName"></span></span>
      <button class="modal-close" onclick="closeModal('tokenModal')">✕</button>
    </div>
    <div class="modal-token" id="modalTokenText"></div>
    <button class="modal-copy" onclick="copyModal()">Copy Token</button>
  </div>
</div>

<!-- ── User Detail Panel ── -->
<div class="detail-panel" id="detailPanel">
  <div class="detail-box">
    <h2>
      <span>👤 User Details</span>
      <button class="modal-close" onclick="closeModal('detailPanel')">✕</button>
    </h2>
    <div id="detailContent"></div>
  </div>
</div>

<script>
// Token modal
function showToken(token, name) {
  document.getElementById('modalTokenText').textContent = token;
  document.getElementById('modalUserName').textContent = name;
  document.getElementById('tokenModal').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.modal,.detail-panel').forEach(m => {
  m.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
});
function copyModal() {
  const text = document.getElementById('modalTokenText').textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.modal-copy');
    btn.textContent = '✓ Copied!';
    setTimeout(() => btn.textContent = 'Copy Token', 2000);
  });
}
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    btn.textContent = '✓ Copied';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
  });
}

// User detail panel
function showDetail(user) {
  const labels = {
    phone: 'Phone',
    name: 'Name',
    last_login: 'Last Login',
    login_count: 'Total Logins',
    batch_count: 'Batch Count',
    batch_count_updated: 'Batches Updated',
    access_token: 'Access Token',
    refresh_token: 'Refresh Token',
  };
  let html = '';
  for (const [key, label] of Object.entries(labels)) {
    let val = user[key];
    if (val === undefined || val === null || val === '') continue;
    if (key === 'access_token' || key === 'refresh_token') {
      const short = String(val).slice(0,40) + '…';
      val = `<span style="cursor:pointer;color:var(--accent)" onclick="copyText('${String(val).replace(/'/g,"\\'")}',this)" title="Click to copy">${short}</span>`;
    } else {
      val = `<span>${String(val)}</span>`;
    }
    html += `<div class="detail-row"><span class="detail-key">${label}</span><span class="detail-val">${val}</span></div>`;
  }
  // Extra fields
  if (user.extra && typeof user.extra === 'object') {
    for (const [k, v] of Object.entries(user.extra)) {
      html += `<div class="detail-row"><span class="detail-key">${k}</span><span class="detail-val">${JSON.stringify(v)}</span></div>`;
    }
  }
  document.getElementById('detailContent').innerHTML = html || '<p style="color:var(--muted);font-size:13px">No additional data.</p>';
  document.getElementById('detailPanel').classList.add('open');
}

// Block devtools on admin
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key==='F12' || (e.ctrlKey&&e.shiftKey&&['I','J','C'].includes(e.key)) || (e.ctrlKey&&e.key==='U')) e.preventDefault();
});
</script>
</body>
</html>
