<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot();
require_login();

if (isset($_GET['logout'])) { logout_user(); header('Location: /index.php'); exit; }

$token   = $_SESSION['user_token'];
$name    = $_SESSION['user_name']  ?? 'Student';
$phone   = $_SESSION['user_phone'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));

$r       = api_get_batches($token, $page);
$batches = $r['data'] ?? [];
$apiErr  = !$r['success'];

if (!$apiErr) user_update_batches($phone, count($batches));

$emojis = ['🧪','📐','🌍','📊','⚗️','🔬','💡','🧮','📚','🏛','🔭','🧬'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Batches — PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#05050d;--s1:#0a0a18;--card:#0d0d1e;--border:#1c1c35;--accent:#f97316;--text:#eeeeff;--muted:#6b6b95;--muted2:#9090b8;--success:#22c55e;--danger:#ef4444;--r:14px}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.bg-glow{position:fixed;inset:0;z-index:0;pointer-events:none}
.g1{position:absolute;top:-150px;left:-100px;width:600px;height:600px;background:radial-gradient(circle,rgba(249,115,22,.1),transparent 70%)}
.g2{position:absolute;bottom:-100px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(124,58,237,.08),transparent 70%)}
.wrap{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}

/* Nav */
.nav{display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:62px;background:rgba(5,5,13,.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.nav-brand{display:flex;align-items:center;gap:10px}
.nav-logo{width:32px;height:32px;background:linear-gradient(135deg,#f97316,#ea580c);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px}
.nav-name{font-weight:800;font-size:16px;letter-spacing:-.01em}
.nav-name span{color:var(--accent)}
.nav-r{display:flex;align-items:center;gap:10px}
.user-pill{display:flex;align-items:center;gap:8px;padding:6px 14px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:100px}
.ava{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#f97316,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0}
.user-info .un{font-size:13px;font-weight:600}
.user-info .ph{font-size:11px;color:var(--muted2);font-family:'JetBrains Mono',monospace}
.logout{padding:7px 14px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;color:#fca5a5;font-size:13px;text-decoration:none;font-weight:600;transition:all .2s}
.logout:hover{background:rgba(239,68,68,.18)}

/* Hero section */
.hero{padding:40px 32px 32px;border-bottom:1px solid var(--border)}
.hero-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);border-radius:100px;font-size:11px;font-weight:700;color:#fb923c;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px}
.hero-dot{width:7px;height:7px;border-radius:50%;background:#f97316;animation:p 2s ease infinite}
@keyframes p{0%,100%{opacity:1}50%{opacity:.3}}
.hero-title{font-size:clamp(1.5rem,3vw,2.2rem);font-weight:800;letter-spacing:-.02em;margin-bottom:6px}
.hero-title em{color:var(--accent);font-style:normal}
.hero-sub{font-size:14px;color:var(--muted2)}
.hero-stats{display:flex;gap:14px;margin-top:22px;flex-wrap:wrap}
.hs{padding:12px 20px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px}
.hs-n{font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;color:var(--accent)}
.hs-l{font-size:11px;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}

/* Main */
.main{flex:1;padding:36px 32px}
.sec-head{display:flex;align-items:center;gap:14px;margin-bottom:22px}
.sec-title{font-size:16px;font-weight:800;white-space:nowrap}
.sec-line{flex:1;height:1px;background:var(--border)}

/* Grid */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:18px}

/* Batch card */
.bcard{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;display:block;transition:all .3s;position:relative}
.bcard::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#f97316,#7c3aed);opacity:0;transition:opacity .3s}
.bcard:hover{border-color:rgba(249,115,22,.45);transform:translateY(-5px);box-shadow:0 24px 48px rgba(0,0,0,.6),0 0 0 1px rgba(249,115,22,.08) inset}
.bcard:hover::before{opacity:1}

.bcard-img{aspect-ratio:16/7;background:linear-gradient(135deg,#111125,#1a1a35);position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center}
.bcard-img img{width:100%;height:100%;object-fit:cover}
.bcard-img .fallback{display:flex;flex-direction:column;align-items:center;gap:4px}
.fallback-icon{font-size:38px}
.fallback-txt{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.1em}

.bcard-body{padding:16px}
.bcard-tag{display:inline-block;padding:3px 10px;border-radius:100px;background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.2);font-size:10px;font-weight:700;color:#fb923c;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px}
.bcard-title{font-size:15px;font-weight:700;line-height:1.35;margin-bottom:10px}
.bcard-meta{display:flex;gap:10px;flex-wrap:wrap}
.bcard-mi{font-size:11px;color:var(--muted2);display:flex;align-items:center;gap:4px}

.bcard-foot{padding:10px 16px;border-top:1px solid rgba(255,255,255,.05);display:flex;align-items:center;justify-content:space-between}
.bid{font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace}
.barrow{width:26px;height:26px;border-radius:50%;background:rgba(249,115,22,.12);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--accent);transition:all .25s}
.bcard:hover .barrow{background:var(--accent);color:#fff;transform:rotate(45deg)}

/* Empty */
.empty{text-align:center;padding:80px 20px;border:1px dashed var(--border);border-radius:16px}
.empty-ico{font-size:60px;margin-bottom:16px}
.empty-t{font-size:20px;font-weight:800;margin-bottom:8px}
.empty-s{color:var(--muted2);max-width:360px;margin:0 auto 20px;font-size:14px;line-height:1.6}
.empty-btn{display:inline-block;padding:12px 28px;background:var(--accent);color:#fff;border-radius:10px;font-weight:700;text-decoration:none;font-size:14px}

/* Alert */
.alert-danger{padding:14px 16px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:10px;color:#fca5a5;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px}

/* Pagination */
.pagi{display:flex;gap:8px;justify-content:center;margin-top:32px}
.pbt{padding:8px 18px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--text);text-decoration:none;font-size:13px;font-weight:600;transition:all .2s}
.pbt:hover,.pbt.cur{background:var(--accent);border-color:var(--accent);color:#fff}
.pbt.dis{opacity:.35;pointer-events:none}

@media(max-width:640px){.nav{padding:0 16px}.hero{padding:28px 16px}.main{padding:24px 16px}.hs{padding:10px 14px}.hero-stats{gap:10px}}
</style>
</head>
<body>
<div class="bg-glow"><div class="g1"></div><div class="g2"></div></div>
<div class="wrap">

  <nav class="nav">
    <div class="nav-brand">
      <div class="nav-logo">⚡</div>
      <span class="nav-name">PW<span>Portal</span></span>
    </div>
    <div class="nav-r">
      <div class="user-pill">
        <div class="ava"><?= strtoupper(mb_substr($name ?: 'S', 0, 1)) ?></div>
        <div class="user-info">
          <div class="un"><?= htmlspecialchars(mb_substr($name ?: 'Student', 0, 16)) ?></div>
          <div class="ph">+91 <?= htmlspecialchars($phone) ?></div>
        </div>
      </div>
      <a href="?logout=1" class="logout">Logout</a>
    </div>
  </nav>

  <div class="hero">
    <div class="hero-chip"><div class="hero-dot"></div>My Learning</div>
    <h1 class="hero-title">Welcome, <em><?= htmlspecialchars(explode(' ', $name)[0] ?: 'Student') ?></em> 👋</h1>
    <p class="hero-sub">All your enrolled batches from Physics Wallah</p>
    <div class="hero-stats">
      <div class="hs"><div class="hs-n"><?= count($batches) ?></div><div class="hs-l">Batches</div></div>
      <div class="hs"><div class="hs-n"><?= $page ?></div><div class="hs-l">Page</div></div>
    </div>
  </div>

  <div class="main">
    <?php if ($apiErr): ?>
    <div class="alert-danger">⚠ Session expired or token invalid. <a href="/index.php" style="color:#fb923c;margin-left:8px">Re-login →</a></div>
    <?php endif; ?>

    <div class="sec-head"><span class="sec-title">📚 Your Courses</span><div class="sec-line"></div></div>

    <?php if (empty($batches) && !$apiErr): ?>
    <div class="empty">
      <div class="empty-ico">📦</div>
      <div class="empty-t">No Courses Found</div>
      <p class="empty-s">You haven't enrolled in any PW batch yet. Visit PW website to enroll.</p>
      <a href="https://www.pw.live/" target="_blank" class="empty-btn">Browse Courses →</a>
    </div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($batches as $idx => $b):
        $id    = $b['_id']  ?? '';
        $title = $b['name'] ?? 'Unnamed Batch';
        $thumb = $b['thumbnail'] ?? ($b['image']['baseUrl'] ?? '');
        $tag   = $b['examName'] ?? ($b['tags'][0] ?? 'PW Course');
        $subs  = count($b['subjects'] ?? []);
        $lang  = $b['language'] ?? '';
        $em    = $emojis[$idx % count($emojis)];
      ?>
      <a href="/batch.php?id=<?= urlencode($id) ?>" class="bcard">
        <div class="bcard-img">
          <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt="" onerror="this.style.display='none';this.nextSibling.style.display='flex'">
            <div class="fallback" style="display:none"><div class="fallback-icon"><?= $em ?></div><div class="fallback-txt">PW Batch</div></div>
          <?php else: ?>
            <div class="fallback"><div class="fallback-icon"><?= $em ?></div><div class="fallback-txt">PW Batch</div></div>
          <?php endif; ?>
        </div>
        <div class="bcard-body">
          <?php if ($tag): ?><span class="bcard-tag"><?= htmlspecialchars(mb_substr($tag,0,24)) ?></span><?php endif; ?>
          <div class="bcard-title"><?= htmlspecialchars($title) ?></div>
          <div class="bcard-meta">
            <?php if ($subs): ?><span class="bcard-mi">📚 <?= $subs ?> Subjects</span><?php endif; ?>
            <?php if ($lang): ?><span class="bcard-mi">🌐 <?= htmlspecialchars($lang) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="bcard-foot">
          <span class="bid"><?= htmlspecialchars(substr($id,0,20)) ?>…</span>
          <span class="barrow">↗</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="pagi">
      <?php if ($page > 1): ?><a href="?page=<?=$page-1?>" class="pbt">← Prev</a><?php else: ?><span class="pbt dis">← Prev</span><?php endif; ?>
      <span class="pbt cur"><?= $page ?></span>
      <?php if (count($batches) >= 20): ?><a href="?page=<?=$page+1?>" class="pbt">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
