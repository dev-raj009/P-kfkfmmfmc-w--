<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

require_login();

$token   = $_SESSION['user_token'];
$batchId = trim($_GET['id'] ?? '');

if (!$batchId) { header('Location: /dashboard.php'); exit; }

$res     = pw_get_batch_details($token, $batchId);
$data    = $res['body']['data'] ?? [];
$title   = $data['name'] ?? 'Batch Details';
$subjects = $data['subjects'] ?? [];
$error   = $res['code'] !== 200 ? 'Failed to load batch.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= htmlspecialchars($title) ?> — PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#09090f;--card:#14141f;--border:#1e1e30;--accent:#f97316;--text:#f1f0ff;--muted:#6b6b8a;--radius:14px}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.bg-glow{position:fixed;inset:0;z-index:0;pointer-events:none}
.glow1{position:absolute;top:-100px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(249,115,22,.12),transparent 70%)}
.shell{position:relative;z-index:1}

/* Nav */
nav{display:flex;align-items:center;justify-content:space-between;padding:0 40px;height:60px;background:rgba(9,9,15,.9);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.back-link{display:flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;font-size:14px;transition:color .2s}
.back-link:hover{color:var(--accent)}
.nav-right{font-family:'Syne',sans-serif;font-size:16px;font-weight:700}
.nav-right span{color:var(--accent)}

/* Hero */
.batch-hero{padding:48px 40px;border-bottom:1px solid var(--border);background:linear-gradient(135deg,rgba(249,115,22,.05) 0%,transparent 60%)}
.batch-tag{display:inline-block;padding:4px 12px;border-radius:100px;background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.25);font-size:11px;color:#fb923c;font-weight:600;text-transform:uppercase;letter-spacing:.07em;margin-bottom:14px}
.batch-title{font-family:'Syne',sans-serif;font-size:clamp(1.5rem,3vw,2.2rem);font-weight:800;line-height:1.2;margin-bottom:16px}
.batch-meta{display:flex;gap:20px;flex-wrap:wrap}
.meta-pill{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);padding:6px 14px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:100px}

/* Content */
.content{padding:40px;max-width:900px}
.section-head{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:12px}
.section-head::after{content:'';flex:1;height:1px;background:var(--border)}

/* Subject cards */
.subjects-list{display:flex;flex-direction:column;gap:12px}
.subject-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px 22px;display:flex;align-items:center;gap:16px;transition:all .25s}
.subject-card:hover{border-color:rgba(249,115,22,.4);transform:translateX(4px)}
.subj-icon{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,rgba(249,115,22,.2),rgba(124,58,237,.2));display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.subj-name{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:2px}
.subj-meta{font-size:12px;color:var(--muted)}
.subj-id{margin-left:auto;font-size:11px;color:var(--muted);font-family:monospace;padding:4px 8px;background:rgba(255,255,255,.04);border-radius:6px}

.empty-box{text-align:center;padding:60px 20px;border:1px dashed var(--border);border-radius:var(--radius)}
.alert-danger{padding:14px 18px;border-radius:10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;margin-bottom:24px}

@media(max-width:768px){nav,.batch-hero,.content{padding-left:20px;padding-right:20px}}
</style>
</head>
<body>
<div class="bg-glow"><div class="glow1"></div></div>
<div class="shell">
  <nav>
    <a href="/dashboard.php" class="back-link">← Back to Courses</a>
    <span class="nav-right">PW<span>Portal</span></span>
  </nav>

  <div class="batch-hero">
    <div class="batch-tag">📚 Batch Details</div>
    <h1 class="batch-title"><?= htmlspecialchars($title) ?></h1>
    <div class="batch-meta">
      <span class="meta-pill">🆔 <?= htmlspecialchars($batchId) ?></span>
      <?php if (!empty($data['language'])): ?>
        <span class="meta-pill">🌐 <?= htmlspecialchars($data['language']) ?></span>
      <?php endif; ?>
      <?php if (!empty($subjects)): ?>
        <span class="meta-pill">📚 <?= count($subjects) ?> Subjects</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($error): ?>
      <div class="alert-danger">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php $emojis = ['📐','🔬','📖','⚗️','🧮','🌍','💡','🧪','📊','🏛️']; ?>

    <?php if (!empty($subjects)): ?>
      <div class="section-head">Subjects</div>
      <div class="subjects-list">
        <?php foreach ($subjects as $i => $s): ?>
        <div class="subject-card">
          <div class="subj-icon"><?= $emojis[$i % count($emojis)] ?></div>
          <div>
            <div class="subj-name"><?= htmlspecialchars($s['subject'] ?? 'Subject') ?></div>
            <div class="subj-meta">Topics: <?= $s['tagCount'] ?? '—' ?></div>
          </div>
          <span class="subj-id"><?= htmlspecialchars($s['subjectId'] ?? '') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-box">
        <div style="font-size:48px;margin-bottom:12px">📦</div>
        <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:8px">No Subjects Found</div>
        <p style="color:var(--muted)">This batch may not have any subjects listed yet.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
