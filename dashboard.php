<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot();
require_login();

if (isset($_GET['logout'])) { do_logout(); header('Location: /index.php'); exit; }

$token   = $_SESSION['u_token'];
$name    = $_SESSION['u_name']  ?? 'Student';
$phone   = $_SESSION['u_phone'] ?? '';
$page    = max(1, (int)($_GET['p'] ?? 1));

$r       = api_get_batches($token, $page);
$batches = $r['data'] ?? [];
$failed  = !$r['success'];

// Update batch count in storage
if (!$failed && !empty($batches)) {
    $bData = array_map(fn($b) => ['_id' => $b['_id'] ?? '', 'name' => $b['name'] ?? ''], $batches);
    $u = user_get_by_phone($phone);
    if ($u) user_upsert($phone, $token, $u['refresh_token'] ?? '', $u['name'] ?? $name, $u['email'] ?? '', $bData);
}

$icons = ['🧪','📐','🌍','📊','⚗','🔬','💡','🧮','📚','🏛','🔭','🧬','✏','📏','🖥'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>My Courses — PW Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#06060f;--s1:#0a0a1a;--card:#0c0c1e;--b1:#1a1a35;--b2:#242445;--acc:#f97316;--text:#f0f0ff;--muted:#7070a0;--m2:#a0a0c8;--ok:#22c55e;--err:#ef4444}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.bg-fix{position:fixed;inset:0;z-index:0;pointer-events:none}
.glow{position:absolute;border-radius:50%;filter:blur(80px)}
.g1{width:500px;height:500px;top:-150px;left:-100px;background:radial-gradient(rgba(249,115,22,.12),transparent 70%)}
.g2{width:400px;height:400px;bottom:-100px;right:-100px;background:radial-gradient(rgba(124,58,237,.1),transparent 70%)}
.shell{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}

/* nav */
nav{height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;background:rgba(6,6,15,.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--b1);position:sticky;top:0;z-index:100;gap:12px}
.nl{display:flex;align-items:center;gap:10px}
.nlogo{width:30px;height:30px;background:linear-gradient(135deg,#f97316,#ea580c);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px}
.nbrand{font-weight:800;font-size:15px;letter-spacing:-.01em}
.nbrand span{color:#f97316}
.nr{display:flex;align-items:center;gap:8px}
.upill{display:flex;align-items:center;gap:7px;padding:5px 12px;background:rgba(255,255,255,.04);border:1px solid var(--b1);border-radius:100px;max-width:200px}
.uava{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#f97316,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.uname-n{font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.uph{font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.logout-a{padding:6px 12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;color:#fca5a5;font-size:12px;text-decoration:none;font-weight:600;white-space:nowrap;transition:all .2s}
.logout-a:hover{background:rgba(239,68,68,.18)}

/* hero */
.hero{padding:32px 24px 24px;border-bottom:1px solid var(--b1)}
.hchip{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);border-radius:100px;font-size:10px;font-weight:700;color:#f97316;letter-spacing:.08em;text-transform:uppercase;margin-bottom:12px}
.hdot{width:6px;height:6px;border-radius:50%;background:#f97316;animation:p 2s ease infinite}
@keyframes p{0%,100%{opacity:1}50%{opacity:.3}}
.htitle{font-size:clamp(1.3rem,5vw,1.9rem);font-weight:800;letter-spacing:-.02em;margin-bottom:5px}
.htitle em{color:#f97316;font-style:normal}
.hsub{font-size:13px;color:var(--m2)}
.hstats{display:flex;gap:12px;margin-top:18px;flex-wrap:wrap}
.hstat{padding:10px 16px;background:rgba(255,255,255,.03);border:1px solid var(--b1);border-radius:10px}
.hstat-n{font-size:20px;font-weight:800;font-family:'JetBrains Mono',monospace;color:#f97316}
.hstat-l{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-top:2px}

/* main */
.main{flex:1;padding:28px 24px}
.sh{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.sh-t{font-size:15px;font-weight:700}
.sh-l{flex:1;height:1px;background:var(--b1)}

/* grid */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}

/* batch card */
.bc{background:var(--card);border:1.5px solid var(--b1);border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;display:block;transition:all .3s;position:relative}
.bc::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#f97316,#7c3aed);opacity:0;transition:opacity .3s;z-index:1}
.bc:hover{border-color:rgba(249,115,22,.45);transform:translateY(-4px);box-shadow:0 20px 40px rgba(0,0,0,.6)}
.bc:hover::before{opacity:1}
.bc-img{aspect-ratio:16/7;background:linear-gradient(135deg,#0e0e22,#181832);position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center}
.bc-img img{width:100%;height:100%;object-fit:cover}
.bc-fb{display:flex;flex-direction:column;align-items:center;gap:4px}
.bc-fb-i{font-size:36px}
.bc-fb-t{font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.1em}
.bc-body{padding:14px}
.bc-tag{display:inline-block;padding:3px 8px;border-radius:100px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);font-size:10px;font-weight:700;color:#fb923c;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px}
.bc-name{font-size:14px;font-weight:700;line-height:1.35;margin-bottom:8px}
.bc-meta{display:flex;gap:8px;flex-wrap:wrap}
.bc-m{font-size:11px;color:var(--m2);display:flex;align-items:center;gap:3px}
.bc-foot{padding:9px 14px;border-top:1px solid rgba(255,255,255,.05);display:flex;align-items:center;justify-content:space-between}
.bc-id{font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px}
.bc-arr{width:24px;height:24px;border-radius:50%;background:rgba(249,115,22,.12);display:flex;align-items:center;justify-content:center;font-size:12px;color:#f97316;transition:all .2s;flex-shrink:0}
.bc:hover .bc-arr{background:#f97316;color:#fff;transform:rotate(45deg)}

/* empty */
.empty{text-align:center;padding:60px 20px;border:1px dashed var(--b1);border-radius:16px}
.empty-i{font-size:52px;margin-bottom:14px}
.empty-t{font-size:18px;font-weight:800;margin-bottom:8px}
.empty-s{color:var(--m2);font-size:13px;max-width:320px;margin:0 auto}
.empty-a{display:inline-block;margin-top:18px;padding:11px 24px;background:#f97316;color:#fff;border-radius:10px;font-weight:700;text-decoration:none;font-size:13px}

.adanger{padding:12px 16px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:10px;color:#fca5a5;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px}

/* pagination */
.pagi{display:flex;gap:8px;justify-content:center;margin-top:28px}
.pb{padding:7px 16px;border-radius:8px;border:1px solid var(--b1);background:rgba(255,255,255,.04);color:var(--text);text-decoration:none;font-size:13px;font-weight:600;transition:all .2s}
.pb:hover,.pb.cur{background:#f97316;border-color:#f97316;color:#fff}
.pb.dis{opacity:.3;pointer-events:none}

@media(max-width:520px){nav{padding:0 14px}.hero,.main{padding-left:14px;padding-right:14px}.grid{grid-template-columns:1fr}.hstats{gap:8px}}
</style>
</head>
<body>
<div class="bg-fix"><div class="glow g1"></div><div class="glow g2"></div></div>
<div class="shell">
  <nav>
    <div class="nl">
      <div class="nlogo">⚡</div>
      <span class="nbrand">PW<span>Portal</span></span>
    </div>
    <div class="nr">
      <div class="upill">
        <div class="uava"><?= strtoupper(mb_substr($name ?: 'S', 0, 1)) ?></div>
        <div style="min-width:0">
          <div class="uname-n"><?= htmlspecialchars(mb_substr($name ?: 'Student', 0, 14)) ?></div>
          <div class="uph"><?= htmlspecialchars($phone) ?></div>
        </div>
      </div>
      <a href="?logout=1" class="logout-a">Logout</a>
    </div>
  </nav>

  <div class="hero">
    <div class="hchip"><div class="hdot"></div>My Learning</div>
    <h1 class="htitle">Hello, <em><?= htmlspecialchars(explode(' ', $name ?: 'Student')[0]) ?></em> 👋</h1>
    <p class="hsub">Your enrolled courses from Physics Wallah</p>
    <div class="hstats">
      <div class="hstat"><div class="hstat-n"><?= count($batches) ?></div><div class="hstat-l">Batches</div></div>
      <div class="hstat"><div class="hstat-n">P<?= $page ?></div><div class="hstat-l">Page</div></div>
    </div>
  </div>

  <div class="main">
    <?php if ($failed): ?>
    <div class="adanger">⚠ Session expired. <a href="/index.php" style="color:#fb923c;margin-left:8px;font-weight:700">Login again →</a></div>
    <?php endif; ?>

    <div class="sh"><span class="sh-t">📚 Your Courses</span><div class="sh-l"></div></div>

    <?php if (empty($batches) && !$failed): ?>
    <div class="empty">
      <div class="empty-i">📦</div>
      <div class="empty-t">No Courses Found</div>
      <p class="empty-s">You haven't enrolled in any PW batch. Visit PW website to enroll.</p>
      <a href="https://www.pw.live/" target="_blank" class="empty-a">Browse Courses →</a>
    </div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($batches as $i => $b):
        $id    = $b['_id']  ?? '';
        $title = $b['name'] ?? 'Unnamed';
        $thumb = $b['thumbnail'] ?? ($b['image']['baseUrl'] ?? '');
        $tag   = $b['examName'] ?? ($b['tags'][0] ?? 'PW');
        $subs  = count($b['subjects'] ?? []);
        $lang  = $b['language'] ?? '';
        $ic    = $icons[$i % count($icons)];
      ?>
      <a href="/batch.php?id=<?= urlencode($id) ?>" class="bc">
        <div class="bc-img">
          <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt="" onerror="this.style.display='none';this.nextSibling.style.display='flex'">
            <div class="bc-fb" style="display:none"><div class="bc-fb-i"><?= $ic ?></div><div class="bc-fb-t">PW Batch</div></div>
          <?php else: ?>
            <div class="bc-fb"><div class="bc-fb-i"><?= $ic ?></div><div class="bc-fb-t">PW Batch</div></div>
          <?php endif; ?>
        </div>
        <div class="bc-body">
          <?php if ($tag): ?><span class="bc-tag"><?= htmlspecialchars(mb_substr($tag,0,22)) ?></span><?php endif; ?>
          <div class="bc-name"><?= htmlspecialchars($title) ?></div>
          <div class="bc-meta">
            <?php if ($subs): ?><span class="bc-m">📚 <?= $subs ?> Subj</span><?php endif; ?>
            <?php if ($lang): ?><span class="bc-m">🌐 <?= htmlspecialchars($lang) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="bc-foot">
          <span class="bc-id"><?= htmlspecialchars($id) ?></span>
          <span class="bc-arr">↗</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="pagi">
      <?php if ($page > 1): ?><a href="?p=<?=$page-1?>" class="pb">← Prev</a><?php else: ?><span class="pb dis">← Prev</span><?php endif; ?>
      <span class="pb cur"><?= $page ?></span>
      <?php if (count($batches) >= 20): ?><a href="?p=<?=$page+1?>" class="pb">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
