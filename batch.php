<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot();
require_login();

$token   = $_SESSION['user_token'];
$batchId = trim($_GET['id'] ?? '');
if (!$batchId) { header('Location: /dashboard.php'); exit; }

$r       = api_get_batch_details($token, $batchId);
$data    = $r['data'] ?? [];
$title   = $data['name']     ?? 'Batch';
$subjects = $data['subjects'] ?? [];
$thumb   = $data['thumbnail'] ?? '';

$emojis = ['🧪','📐','🌍','📊','⚗️','🔬','💡','🧮','📚','🏛','🔭','🧬'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#05050d;--card:#0d0d1e;--border:#1c1c35;--accent:#f97316;--text:#eeeeff;--muted:#6b6b95;--muted2:#9090b8;--r:14px}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text)}
.glow{position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse at top right,rgba(249,115,22,.1),transparent 60%)}
.wrap{position:relative;z-index:1;min-height:100vh}

nav{display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:60px;background:rgba(5,5,13,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.back{display:flex;align-items:center;gap:8px;color:var(--muted2);text-decoration:none;font-size:14px;font-weight:600;transition:color .2s}
.back:hover{color:var(--accent)}
.nav-r{font-weight:800;font-size:15px;color:var(--text)}
.nav-r span{color:var(--accent)}

.hero{padding:40px 32px;border-bottom:1px solid var(--border)}
.bc{display:flex;gap:6px;align-items:center;font-size:12px;color:var(--muted2);margin-bottom:18px}
.bc a{color:var(--muted2);text-decoration:none;transition:color .2s}.bc a:hover{color:var(--accent)}
.bc-sep{color:var(--muted)}
.batch-title{font-size:clamp(1.4rem,3vw,2rem);font-weight:800;letter-spacing:-.02em;margin-bottom:14px;line-height:1.2}
.batch-meta{display:flex;gap:12px;flex-wrap:wrap}
.bm{display:flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:100px;font-size:12px;color:var(--muted2)}

.content{padding:36px 32px;max-width:900px}
.sh{font-size:16px;font-weight:800;margin-bottom:18px;display:flex;align-items:center;gap:12px}
.sh::after{content:'';flex:1;height:1px;background:var(--border)}

.slist{display:flex;flex-direction:column;gap:12px}
.scard{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;transition:all .25s}
.scard:hover{border-color:rgba(249,115,22,.45);transform:translateX(5px);box-shadow:0 8px 32px rgba(0,0,0,.4)}
.scard-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,rgba(249,115,22,.15),rgba(124,58,237,.15));display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;border:1px solid rgba(249,115,22,.15)}
.scard-info{flex:1}
.scard-name{font-size:15px;font-weight:700;margin-bottom:3px}
.scard-meta{font-size:12px;color:var(--muted2)}
.scard-id{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);background:rgba(255,255,255,.04);padding:4px 8px;border-radius:6px;white-space:nowrap}
.scard-arr{color:var(--accent);font-size:18px;transition:transform .2s;flex-shrink:0}
.scard:hover .scard-arr{transform:translateX(4px)}

.empty{text-align:center;padding:60px 20px;border:1px dashed var(--border);border-radius:14px}
@media(max-width:640px){nav,.hero,.content{padding-left:16px;padding-right:16px}}
</style>
</head>
<body>
<div class="glow"></div>
<div class="wrap">
  <nav>
    <a href="/dashboard.php" class="back">← Back to Courses</a>
    <span class="nav-r">PW<span>Portal</span></span>
  </nav>

  <div class="hero">
    <div class="bc">
      <a href="/dashboard.php">My Courses</a>
      <span class="bc-sep">›</span>
      <span><?= htmlspecialchars(mb_substr($title,0,30)) ?></span>
    </div>
    <h1 class="batch-title"><?= htmlspecialchars($title) ?></h1>
    <div class="batch-meta">
      <span class="bm">🆔 <?= htmlspecialchars(mb_substr($batchId,0,20)) ?>…</span>
      <?php if (!empty($data['language'])): ?><span class="bm">🌐 <?= htmlspecialchars($data['language']) ?></span><?php endif; ?>
      <?php if (!empty($subjects)): ?><span class="bm">📚 <?= count($subjects) ?> Subjects</span><?php endif; ?>
    </div>
  </div>

  <div class="content">
    <div class="sh">Subjects</div>
    <?php if (empty($subjects)): ?>
      <div class="empty">
        <div style="font-size:48px;margin-bottom:12px">📦</div>
        <div style="font-size:18px;font-weight:800;margin-bottom:8px">No Subjects Found</div>
        <p style="color:var(--muted2);font-size:14px">This batch has no subjects listed yet.</p>
      </div>
    <?php else: ?>
      <div class="slist">
        <?php foreach ($subjects as $i => $s):
          $sId   = $s['subjectId'] ?? $s['_id'] ?? '';
          $sName = $s['subject']   ?? 'Subject';
          $cnt   = $s['tagCount']  ?? 0;
          $em    = $emojis[$i % count($emojis)];
        ?>
        <a href="/subject.php?bid=<?= urlencode($batchId) ?>&sid=<?= urlencode($sId) ?>&sname=<?= urlencode($sName) ?>" class="scard">
          <div class="scard-icon"><?= $em ?></div>
          <div class="scard-info">
            <div class="scard-name"><?= htmlspecialchars($sName) ?></div>
            <div class="scard-meta">Topics: <?= $cnt ?: '—' ?></div>
          </div>
          <span class="scard-id"><?= htmlspecialchars(mb_substr($sId,0,18)) ?></span>
          <span class="scard-arr">›</span>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
