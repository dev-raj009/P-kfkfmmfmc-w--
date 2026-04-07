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

$users   = users_load();
$total   = count($users);
$search  = trim($_GET['q'] ?? '');
$msg     = htmlspecialchars($_GET['msg'] ?? '');

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
:root{--bg:#06060e;--surface:#0a0a16;--card:#0e0e1c;--border:#181826;--accent:#f97316;--text:#e8e8ff;--muted:#545472;--success:#22c55e;--danger:#ef4444;--radius:12px}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* Sidebar */
.layout{display:flex;min-height:100vh}
aside{width:240px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;height:100vh;z-index:50}
.aside-logo{padding:24px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.logo-box{width:36px;height:36px;background:linear-gradient(135deg,#f97316,#ea580c);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}
.logo-text{font-family:'Syne',sans-serif;font-size:16px;font-weight:800}
.logo-text span{color:var(--accent)}
.aside-label{padding:16px 20px 8px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.1em}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--muted);text-decoration:none;font-size:14px;border-radius:0;transition:all .2s;border-left:3px solid transparent}
.nav-item:hover{color:var(--text);background:rgba(255,255,255,.04)}
.nav-item.active{color:var(--accent);background:rgba(249,115,22,.08);border-left-color:var(--accent)}
.nav-icon{font-size:16px;width:20px;text-align:center}
.aside-footer{margin-top:auto;padding:20px;border-top:1px solid var(--border)}
.logout-link{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;color:#fca5a5;text-decoration:none;font-size:13px;transition:all .2s}
.logout-link:hover{background:rgba(239,68,68,.15)}

/* Main */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column}

/* Topbar */
.topbar{padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);background:rgba(6,6,14,.95);backdrop-filter:blur(16px);position:sticky;top:0;z-index:40}
.topbar-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700}
.admin-chip{display:flex;align-items:center;gap:8px;padding:6px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);border-radius:100px;font-size:12px;color:#fb923c}
.dot-live{width:7px;height:7px;border-radius:50%;background:var(--success);animation:blink 2s step-end infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* Content */
.content{padding:32px;flex:1}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:32px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),#7c3aed)}
.stat-num{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;margin-bottom:4px}
.stat-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.stat-icon{position:absolute;top:16px;right:16px;font-size:24px;opacity:.3}

/* Search */
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap}
.search-box{flex:1;min-width:200px;position:relative}
.search-box input{width:100%;padding:10px 14px 10px 38px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s}
.search-box input:focus{border-color:var(--accent);background:rgba(249,115,22,.05)}
.search-box::before{content:'🔍';position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px}
.result-count{font-size:13px;color:var(--muted);white-space:nowrap}

/* Table */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.table-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700}
table{width:100%;border-collapse:collapse}
thead tr{background:rgba(255,255,255,.03)}
th{padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;border-bottom:1px solid var(--border)}
tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(249,115,22,.04)}
td{padding:14px 16px;font-size:13px;vertical-align:middle}
.user-cell{display:flex;align-items:center;gap:10px}
.user-ava{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#f97316,#7c3aed);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;flex-shrink:0}
.user-name-cell{font-weight:500;margin-bottom:2px}
.user-phone-cell{font-size:11px;color:var(--muted);font-family:'DM Mono',monospace}

/* Token display */
.token-wrap{position:relative;display:flex;align-items:center;gap:8px}
.token-text{font-family:'DM Mono',monospace;font-size:11px;color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:rgba(255,255,255,.04);padding:4px 8px;border-radius:6px;border:1px solid var(--border);cursor:pointer;transition:all .2s;user-select:all}
.token-text:hover{border-color:var(--accent);color:var(--accent)}
.copy-btn{padding:4px 8px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:6px;color:#fb923c;font-size:11px;cursor:pointer;font-family:'DM Mono',monospace;white-space:nowrap;transition:all .2s}
.copy-btn:hover{background:rgba(249,115,22,.2)}
.copy-btn.copied{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#86efac}

.badge-active{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);border-radius:100px;font-size:10px;color:#86efac;font-weight:600}
.badge-count{display:inline-block;padding:2px 8px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:100px;font-size:11px;color:#fb923c;font-family:'DM Mono',monospace}

.del-form{display:inline}
.del-btn{padding:5px 10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:6px;color:#fca5a5;font-size:11px;cursor:pointer;transition:all .2s}
.del-btn:hover{background:rgba(239,68,68,.2)}

.empty-row td{text-align:center;padding:60px 20px;color:var(--muted)}

.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}

/* Modal overlay for full token */
.modal{display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.8);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:20px}
.modal.open{display:flex}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;max-width:600px;width:100%}
.modal-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between}
.modal-close{cursor:pointer;color:var(--muted);font-size:20px;background:none;border:none;color:var(--muted)}
.modal-token{font-family:'DM Mono',monospace;font-size:12px;word-break:break-all;background:rgba(255,255,255,.04);padding:16px;border-radius:8px;border:1px solid var(--border);color:var(--text);margin-bottom:16px;line-height:1.6;user-select:all}
.modal-copy{padding:10px 20px;background:var(--accent);border:none;border-radius:8px;color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:13px;cursor:pointer;transition:all .2s}

@media(max-width:900px){aside{display:none}.main{margin-left:0}}
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

  <!-- Main -->
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

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-num"><?= $total ?></div>
          <div class="stat-label">Total Users</div>
          <div class="stat-icon">👥</div>
        </div>
        <div class="stat-card">
          <div class="stat-num"><?= count($filtered) ?></div>
          <div class="stat-label">Shown</div>
          <div class="stat-icon">🔍</div>
        </div>
        <div class="stat-card">
          <div class="stat-num"><?php
            $today = date('Y-m-d');
            echo count(array_filter($users, fn($u) => str_starts_with($u['last_login'] ?? '', $today)));
          ?></div>
          <div class="stat-label">Active Today</div>
          <div class="stat-icon">📅</div>
        </div>
      </div>

      <!-- Search toolbar -->
      <div class="toolbar">
        <form method="GET" style="flex:1;display:flex;gap:12px" onsubmit="return true">
          <div class="search-box">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or phone..."/>
          </div>
          <button type="submit" style="padding:10px 18px;background:var(--accent);border:none;border-radius:8px;color:#fff;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;cursor:pointer">Search</button>
          <?php if ($search): ?>
            <a href="/admin/dashboard.php" style="padding:10px 16px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;color:var(--muted);text-decoration:none;font-size:13px">Clear</a>
          <?php endif; ?>
        </form>
        <span class="result-count"><?= count($filtered) ?> of <?= $total ?> users</span>
      </div>

      <!-- Table -->
      <div class="table-wrap">
        <div class="table-head">
          <span class="table-title">User Registry & Tokens</span>
          <span style="font-size:12px;color:var(--muted);font-family:'DM Mono',monospace">🔒 Secure View</span>
        </div>
        <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Phone</th>
              <th>Access Token</th>
              <th>Refresh Token</th>
              <th>Last Login</th>
              <th>Logins</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($filtered)): ?>
              <tr class="empty-row"><td colspan="8">No users found</td></tr>
            <?php else: $i = 1; foreach ($filtered as $phone => $u): ?>
            <tr>
              <td style="color:var(--muted);font-family:'DM Mono',monospace"><?= $i++ ?></td>
              <td>
                <div class="user-cell">
                  <div class="user-ava"><?= strtoupper(substr($u['name'] ?? 'U', 0, 1)) ?></div>
                  <div>
                    <div class="user-name-cell"><?= htmlspecialchars($u['name'] ?: '—') ?></div>
                    <div class="user-phone-cell"><?= htmlspecialchars($phone) ?></div>
                  </div>
                </div>
              </td>
              <td><span style="font-family:'DM Mono',monospace;font-size:12px"><?= htmlspecialchars($u['phone'] ?? $phone) ?></span></td>
              <td>
                <div class="token-wrap">
                  <?php $at = $u['access_token'] ?? ''; ?>
                  <span class="token-text" title="<?= htmlspecialchars($at) ?>"><?= htmlspecialchars(substr($at,0,30)) ?>…</span>
                  <button class="copy-btn" onclick="showToken('<?= htmlspecialchars(addslashes($at)) ?>', '<?= htmlspecialchars(addslashes($u['name'] ?? $phone)) ?>')">View</button>
                </div>
              </td>
              <td>
                <?php $rt = $u['refresh_token'] ?? ''; ?>
                <?php if ($rt): ?>
                  <div class="token-wrap">
                    <span class="token-text"><?= htmlspecialchars(substr($rt,0,20)) ?>…</span>
                    <button class="copy-btn" onclick="copyText('<?= htmlspecialchars(addslashes($rt)) ?>', this)">Copy</button>
                  </div>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:12px">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--muted);white-space:nowrap;font-family:'DM Mono',monospace"><?= htmlspecialchars($u['last_login'] ?? '—') ?></td>
              <td><span class="badge-count"><?= (int)($u['login_count'] ?? 1) ?></span></td>
              <td>
                <form method="POST" class="del-form" onsubmit="return confirm('Delete user <?= htmlspecialchars(addslashes($phone)) ?>?')">
                  <input type="hidden" name="delete_user" value="<?= htmlspecialchars($phone) ?>"/>
                  <button type="submit" class="del-btn">🗑 Delete</button>
                </form>
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

<!-- Token Modal -->
<div class="modal" id="tokenModal">
  <div class="modal-box">
    <div class="modal-title">
      <span>🔑 Full Access Token — <span id="modalUserName"></span></span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-token" id="modalTokenText"></div>
    <button class="modal-copy" onclick="copyModal()">Copy Token</button>
  </div>
</div>

<script>
function showToken(token, name) {
  document.getElementById('modalTokenText').textContent = token;
  document.getElementById('modalUserName').textContent = name;
  document.getElementById('tokenModal').classList.add('open');
}
function closeModal() {
  document.getElementById('tokenModal').classList.remove('open');
}
document.getElementById('tokenModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
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
// Block devtools on admin panel
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key)) || (e.ctrlKey && e.key === 'U')) {
    e.preventDefault();
  }
});
</script>
</body>
</html>
