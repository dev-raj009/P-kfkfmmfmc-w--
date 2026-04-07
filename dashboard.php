<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

require_login();

$token = $_SESSION['user_token'];
$name  = $_SESSION['user_name'] ?? 'Student';
$phone = $_SESSION['user_phone'] ?? '';

// Fetch batches
$page   = max(1, (int)($_GET['page'] ?? 1));
$res    = pw_get_batches($token, $page);
$batches = $res['body']['data'] ?? [];
$error  = '';

if ($res['code'] !== 200) {
    $error = 'Session expired or token invalid. Please login again.';
}

// Logout handler
if (($_GET['logout'] ?? '') === '1') {
    logout_user();
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Courses — PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#09090f;--surface:#0f0f1a;--card:#14141f;--border:#1e1e30;
  --accent:#f97316;--text:#f1f0ff;--muted:#6b6b8a;--radius:14px;
  --success:#22c55e;--danger:#ef4444;
}
html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text)}

/* BG */
.bg-glow{position:fixed;inset:0;z-index:0;pointer-events:none}
.glow1{position:absolute;top:-200px;left:-200px;width:600px;height:600px;background:radial-gradient(circle,rgba(249,115,22,.15),transparent 70%)}
.glow2{position:absolute;bottom:-200px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(124,58,237,.12),transparent 70%)}

/* Layout */
.shell{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}

/* Navbar */
nav{display:flex;align-items:center;justify-content:space-between;padding:0 40px;height:64px;background:rgba(9,9,15,.9);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.nav-brand{display:flex;align-items:center;gap:12px}
.nav-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;animation:pulse2 2s infinite}
@keyframes pulse2{0%,100%{opacity:1}50%{opacity:.4}}
.nav-title{font-family:'Syne',sans-serif;font-weight:800;font-size:18px}
.nav-title span{color:var(--accent)}
.nav-right{display:flex;align-items:center;gap:16px}
.user-chip{display:flex;align-items:center;gap:10px;padding:7px 14px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:100px}
.user-avatar{width:30px;height:30px;background:linear-gradient(135deg,#f97316,#7c3aed);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:13px;font-weight:700}
.user-name{font-size:14px;font-weight:500}
.user-phone{font-size:11px;color:var(--muted)}
.logout-btn{padding:8px 16px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:8px;color:#fca5a5;font-size:13px;cursor:pointer;text-decoration:none;transition:all .2s}
.logout-btn:hover{background:rgba(239,68,68,.2)}

/* Hero */
.hero{padding:48px 40px 32px;border-bottom:1px solid var(--border)}
.hero-label{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);border-radius:100px;font-size:12px;color:#fb923c;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px}
.hero-title{font-family:'Syne',sans-serif;font-size:clamp(1.6rem,3vw,2.4rem);font-weight:800;line-height:1.2;margin-bottom:8px}
.hero-title em{color:var(--accent);font-style:normal}
.hero-sub{color:var(--muted);font-size:15px}
.stats-row{display:flex;gap:16px;margin-top:24px;flex-wrap:wrap}
.stat-chip{padding:10px 18px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;text-align:center}
.stat-num{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--accent)}
.stat-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}

/* Main */
.main{flex:1;padding:40px;max-width:1200px;width:100%;margin:0 auto;align-self:stretch}

/* Alert */
.alert{padding:14px 18px;border-radius:10px;margin-bottom:24px;display:flex;align-items:center;gap:10px}
.alert-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}

/* Section header */
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.section-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:700}
.section-line{flex:1;height:1px;background:var(--border);margin-left:16px}

/* Empty */
.empty-box{text-align:center;padding:80px 20px;background:rgba(255,255,255,.02);border:1px dashed var(--border);border-radius:var(--radius)}
.empty-icon{font-size:56px;margin-bottom:16px}
.empty-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;margin-bottom:8px}
.empty-text{color:var(--muted);max-width:400px;margin:0 auto;line-height:1.6}
.empty-cta{display:inline-block;margin-top:20px;padding:12px 28px;background:var(--accent);color:#fff;border-radius:10px;font-family:'Syne',sans-serif;font-weight:700;text-decoration:none;transition:all .2s}
.empty-cta:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(249,115,22,.4)}

/* Course Grid */
.courses-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}

.course-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:all .3s;position:relative;cursor:pointer;text-decoration:none;color:inherit;display:block}
.course-card:hover{border-color:rgba(249,115,22,.5);transform:translateY(-4px);box-shadow:0 20px 40px rgba(0,0,0,.5),0 0 0 1px rgba(249,115,22,.1) inset}
.course-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--accent),#7c3aed);opacity:0;transition:opacity .3s}
.course-card:hover::before{opacity:1}

.card-thumb{aspect-ratio:16/7;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;font-size:40px;position:relative;overflow:hidden}
.card-thumb img{width:100%;height:100%;object-fit:cover}
.card-thumb-fallback{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:100%}
.thumb-emoji{font-size:36px;margin-bottom:4px}
.thumb-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.1em}

.card-body{padding:18px}
.card-tag{display:inline-block;padding:3px 10px;border-radius:100px;background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.2);font-size:11px;color:#fb923c;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px}
.card-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;line-height:1.35;margin-bottom:8px}
.card-meta{display:flex;gap:12px;flex-wrap:wrap}
.card-meta-item{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px}
.card-footer{padding:12px 18px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-id{font-size:11px;color:var(--muted);font-family:monospace}
.card-arrow{width:28px;height:28px;background:rgba(249,115,22,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--accent);transition:all .2s}
.course-card:hover .card-arrow{background:var(--accent);color:#fff;transform:rotate(45deg)}

/* Pagination */
.pagination{display:flex;align-items:center;gap:8px;margin-top:32px;justify-content:center}
.page-btn{padding:8px 16px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--text);font-size:13px;cursor:pointer;text-decoration:none;transition:all .2s}
.page-btn:hover,.page-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}
.page-btn.disabled{opacity:.4;pointer-events:none}

@media(max-width:768px){nav{padding:0 20px}.hero{padding:32px 20px}.main{padding:24px 20px}}
</style>
</head>
<body>
<div class="bg-glow"><div class="glow1"></div><div class="glow2"></div></div>

<div class="shell">
  <!-- Navbar -->
  <nav>
    <div class="nav-brand">
      <div class="nav-dot"></div>
      <span class="nav-title">PW<span>Portal</span></span>
    </div>
    <div class="nav-right">
      <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($name ?: 'S', 0, 1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($name ?: 'Student') ?></div>
          <div class="user-phone">+91 <?= htmlspecialchars($phone) ?></div>
        </div>
      </div>
      <a href="?logout=1" class="logout-btn">Logout</a>
    </div>
  </nav>

  <!-- Hero -->
  <div class="hero">
    <div class="hero-label">🎓 My Learning</div>
    <h1 class="hero-title">Welcome back, <em><?= htmlspecialchars(explode(' ', $name)[0] ?: 'Student') ?></em></h1>
    <p class="hero-sub">Your enrolled courses and batches from Physics Wallah</p>
    <?php if (!empty($batches)): ?>
    <div class="stats-row">
      <div class="stat-chip">
        <div class="stat-num"><?= count($batches) ?></div>
        <div class="stat-label">Batches This Page</div>
      </div>
      <div class="stat-chip">
        <div class="stat-num"><?= $page ?></div>
        <div class="stat-label">Current Page</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Main content -->
  <div style="padding:40px;max-width:1200px;width:100%;margin:0 auto">
    <?php if ($error): ?>
      <div class="alert alert-danger">
        <span>⚠</span>
        <?= htmlspecialchars($error) ?>
        <a href="/index.php" style="color:#fb923c;margin-left:auto">Re-login →</a>
      </div>
    <?php endif; ?>

    <div class="section-header">
      <span class="section-title">Your Courses</span>
      <div class="section-line"></div>
    </div>

    <?php if (empty($batches) && !$error): ?>
      <!-- Empty state -->
      <div class="empty-box">
        <div class="empty-icon">📚</div>
        <div class="empty-title">No Courses Found</div>
        <p class="empty-text">You don't have any enrolled batches on Physics Wallah. Purchase a course to get started with your learning journey.</p>
        <a href="https://www.pw.live/" target="_blank" class="empty-cta">Browse Courses on PW →</a>
      </div>
    <?php else: ?>
      <!-- Courses grid -->
      <div class="courses-grid">
        <?php foreach ($batches as $b): 
          $id       = $b['_id'] ?? '';
          $title    = $b['name'] ?? 'Unnamed Batch';
          $thumb    = $b['thumbnail'] ?? ($b['image'] ?? '');
          $subjects = count($b['subjects'] ?? []);
          $language = $b['language'] ?? 'Hindi/English';
          $price    = $b['amount'] ?? 0;
          $tag      = $b['examName'] ?? 'PW Batch';
          $videos   = $b['totalVideoCount'] ?? 0;
        ?>
        <a href="batch.php?id=<?= urlencode($id) ?>" class="course-card">
          <div class="card-thumb">
            <?php if ($thumb): ?>
              <img src="<?= htmlspecialchars($thumb) ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
              <div class="card-thumb-fallback" style="display:none">
                <div class="thumb-emoji">📖</div>
                <div class="thumb-label">PW Course</div>
              </div>
            <?php else: ?>
              <div class="card-thumb-fallback">
                <div class="thumb-emoji">📖</div>
                <div class="thumb-label">PW Course</div>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if ($tag): ?>
              <span class="card-tag"><?= htmlspecialchars($tag) ?></span>
            <?php endif; ?>
            <div class="card-title"><?= htmlspecialchars($title) ?></div>
            <div class="card-meta">
              <?php if ($subjects): ?>
                <span class="card-meta-item">📚 <?= $subjects ?> Subjects</span>
              <?php endif; ?>
              <?php if ($videos): ?>
                <span class="card-meta-item">🎬 <?= $videos ?> Videos</span>
              <?php endif; ?>
              <span class="card-meta-item">🌐 <?= htmlspecialchars($language) ?></span>
            </div>
          </div>
          <div class="card-footer">
            <span class="card-id"><?= htmlspecialchars($id) ?></span>
            <span class="card-arrow">↗</span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>" class="page-btn">← Prev</a>
        <?php else: ?>
          <span class="page-btn disabled">← Prev</span>
        <?php endif; ?>
        <span class="page-btn active"><?= $page ?></span>
        <?php if (count($batches) >= 20): ?>
          <a href="?page=<?= $page+1 ?>" class="page-btn">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
