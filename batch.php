<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot(); require_login();

$token   = $_SESSION['u_token'];
$bid     = trim($_GET['id'] ?? '');
if (!$bid) { header('Location: /dashboard.php'); exit; }

$r        = api_get_batch_details($token, $bid);
$data     = $r['data'];
$title    = $data['name'] ?? 'Batch';
$subjects = $r['subjects'];
$icons    = ['🧪','📐','🌍','📊','⚗','🔬','💡','🧮','📚','🏛','🔭','🧬'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title><?= htmlspecialchars($title) ?> — PW Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#06060f;--card:#0c0c1e;--b1:#1a1a35;--acc:#f97316;--text:#f0f0ff;--muted:#7070a0;--m2:#a0a0c8}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.glow-bg{position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse at top right,rgba(249,115,22,.1),transparent 60%)}
.shell{position:relative;z-index:1}
nav{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;background:rgba(6,6,15,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--b1);position:sticky;top:0;z-index:50}
.back{display:flex;align-items:center;gap:7px;color:var(--m2);text-decoration:none;font-size:13px;font-weight:600;transition:color .2s}
.back:hover{color:#f97316}
.nbrand{font-weight:800;font-size:14px}.nbrand span{color:#f97316}
.hero{padding:28px 24px;border-bottom:1px solid var(--b1)}
.bc{display:flex;gap:6px;align-items:center;font-size:11px;color:var(--muted);margin-bottom:12px;flex-wrap:wrap}
.bc a{color:var(--muted);text-decoration:none;transition:color .2s}.bc a:hover{color:#f97316}
.bc-s{color:var(--muted);margin:0 2px}
.ptitle{font-size:clamp(1.2rem,4vw,1.8rem);font-weight:800;letter-spacing:-.02em;margin-bottom:12px;line-height:1.2}
.pmeta{display:flex;gap:10px;flex-wrap:wrap}
.pm{display:flex;align-items:center;gap:5px;padding:5px 12px;background:rgba(255,255,255,.04);border:1px solid var(--b1);border-radius:100px;font-size:11px;color:var(--m2)}
.content{padding:28px 24px;max-width:860px}
.sh{font-size:15px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:12px}
.sh::after{content:'';flex:1;height:1px;background:var(--b1)}
.slist{display:flex;flex-direction:column;gap:10px}
.sc{background:var(--card);border:1.5px solid var(--b1);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;transition:all .25s}
.sc:hover{border-color:rgba(249,115,22,.45);transform:translateX(4px);box-shadow:0 8px 24px rgba(0,0,0,.4)}
.sc-ic{width:42px;height:42px;border-radius:11px;background:linear-gradient(135deg,rgba(249,115,22,.15),rgba(124,58,237,.15));display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;border:1px solid rgba(249,115,22,.15)}
.sc-info{flex:1;min-width:0}
.sc-name{font-size:14px;font-weight:700;margin-bottom:2px}
.sc-meta{font-size:11px;color:var(--m2)}
.sc-id{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);background:rgba(255,255,255,.04);padding:3px 8px;border-radius:6px;white-space:nowrap;flex-shrink:0;max-width:140px;overflow:hidden;text-overflow:ellipsis}
.sc-arr{color:#f97316;font-size:16px;transition:transform .2s;flex-shrink:0}
.sc:hover .sc-arr{transform:translateX(4px)}
.empty{text-align:center;padding:50px 20px;border:1px dashed var(--b1);border-radius:14px}
@media(max-width:520px){nav,.hero,.content{padding-left:14px;padding-right:14px}}
</style>
</head>
<body>
<div class="glow-bg"></div>
<div class="shell">
  <nav>
    <a href="/dashboard.php" class="back">← My Courses</a>
    <span class="nbrand">PW<span>Portal</span></span>
  </nav>
  <div class="hero">
    <div class="bc">
      <a href="/dashboard.php">Courses</a><span class="bc-s">›</span>
      <span><?= htmlspecialchars(mb_substr($title,0,30)) ?></span>
    </div>
    <h1 class="ptitle"><?= htmlspecialchars($title) ?></h1>
    <div class="pmeta">
      <span class="pm">🆔 <?= htmlspecialchars(mb_substr($bid,0,18)) ?>…</span>
      <?php if (!empty($data['language'])): ?><span class="pm">🌐 <?= htmlspecialchars($data['language']) ?></span><?php endif; ?>
      <?php if (!empty($subjects)): ?><span class="pm">📚 <?= count($subjects) ?> Subjects</span><?php endif; ?>
    </div>
  </div>
  <div class="content">
    <div class="sh">Subjects</div>
    <?php if (empty($subjects)): ?>
      <div class="empty">
        <div style="font-size:44px;margin-bottom:12px">📦</div>
        <div style="font-size:17px;font-weight:800;margin-bottom:6px">No subjects found</div>
        <p style="color:var(--m2);font-size:13px">This batch may not have subjects listed yet.</p>
      </div>
    <?php else: ?>
      <div class="slist">
        <?php foreach ($subjects as $i => $s):
          $sId   = $s['subjectId'] ?? ($s['_id'] ?? '');
          $sName = $s['subject'] ?? 'Subject';
          $cnt   = $s['tagCount'] ?? 0;
        ?>
        <a href="/subject.php?bid=<?= urlencode($bid) ?>&sid=<?= urlencode($sId) ?>&sn=<?= urlencode($sName) ?>&bt=<?= urlencode($title) ?>" class="sc">
          <div class="sc-ic"><?= $icons[$i % count($icons)] ?></div>
          <div class="sc-info">
            <div class="sc-name"><?= htmlspecialchars($sName) ?></div>
            <div class="sc-meta">Topics: <?= $cnt ?: '—' ?></div>
          </div>
          <span class="sc-id"><?= htmlspecialchars($sId) ?></span>
          <span class="sc-arr">›</span>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
