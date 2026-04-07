<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot();
require_login();

$token  = $_SESSION['user_token'];
$bid    = trim($_GET['bid']   ?? '');
$sid    = trim($_GET['sid']   ?? '');
$sname  = trim($_GET['sname'] ?? 'Subject');
if (!$bid || !$sid) { header('Location: /dashboard.php'); exit; }

$ctype = $_GET['type'] ?? 'videos';
$page  = max(1, (int)($_GET['page'] ?? 1));

$r     = api_get_contents($token, $bid, $sid, $ctype, $page);
$items = $r['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($sname) ?> — PW Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#05050d;--card:#0d0d1e;--border:#1c1c35;--accent:#f97316;--text:#eeeeff;--muted:#6b6b95;--muted2:#9090b8;--r:14px;--green:#22c55e}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text)}
.glow{position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse at top right,rgba(249,115,22,.09),transparent 60%)}
.wrap{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}

nav{display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:60px;background:rgba(5,5,13,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.back{display:flex;align-items:center;gap:8px;color:var(--muted2);text-decoration:none;font-size:14px;font-weight:600;transition:color .2s}
.back:hover{color:var(--accent)}
.nav-r{font-weight:800;font-size:15px}.nav-r span{color:var(--accent)}

.hero{padding:32px 32px 0;border-bottom:1px solid var(--border)}
.bc{display:flex;gap:6px;align-items:center;font-size:12px;color:var(--muted2);margin-bottom:14px;flex-wrap:wrap}
.bc a{color:var(--muted2);text-decoration:none}.bc a:hover{color:var(--accent)}
.bc-sep{color:var(--muted)}
.page-title{font-size:clamp(1.3rem,3vw,1.9rem);font-weight:800;letter-spacing:-.02em;margin-bottom:20px}

/* Content tabs */
.ctabs{display:flex;gap:0;border-bottom:1px solid var(--border)}
.ctab{padding:12px 20px;border:none;background:none;color:var(--muted2);font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s;text-decoration:none;display:inline-block}
.ctab:hover{color:var(--text)}
.ctab.active{color:var(--accent);border-bottom-color:var(--accent)}

.main{flex:1;padding:28px 32px}

/* Video grid */
.vgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.vcard{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;cursor:pointer;transition:all .3s}
.vcard:hover{border-color:rgba(249,115,22,.4);transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,.5)}
.vthumb{aspect-ratio:16/9;background:linear-gradient(135deg,#111125,#1c1c38);position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center}
.vthumb img{width:100%;height:100%;object-fit:cover}
.play-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35);opacity:0;transition:opacity .2s}
.vcard:hover .play-overlay{opacity:1}
.play-btn{width:52px;height:52px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:22px;box-shadow:0 4px 20px rgba(249,115,22,.6)}
.vbody{padding:14px}
.vtag{display:inline-block;padding:2px 8px;border-radius:100px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);font-size:10px;font-weight:700;color:#fb923c;text-transform:uppercase;margin-bottom:8px}
.vtitle{font-size:14px;font-weight:700;line-height:1.4;margin-bottom:6px}
.vmeta{font-size:11px;color:var(--muted2);display:flex;align-items:center;gap:8px}

/* Note/DPP list */
.nlist{display:flex;flex-direction:column;gap:10px}
.ncard{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;transition:all .25s}
.ncard:hover{border-color:rgba(249,115,22,.4);transform:translateX(4px)}
.nicon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.nicon-pdf{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.2)}
.nicon-dpp{background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2)}
.ninfo{flex:1}
.nname{font-size:14px;font-weight:600;margin-bottom:3px}
.nmeta{font-size:11px;color:var(--muted2)}
.ndl{padding:7px 14px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:8px;color:#fb923c;font-size:12px;font-weight:700;white-space:nowrap}

.empty{text-align:center;padding:60px 20px;border:1px dashed var(--border);border-radius:14px}

/* Pagination */
.pagi{display:flex;gap:8px;justify-content:center;margin-top:28px}
.pbt{padding:8px 18px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--text);text-decoration:none;font-size:13px;font-weight:600;transition:all .2s}
.pbt:hover,.pbt.cur{background:var(--accent);border-color:var(--accent);color:#fff}
.pbt.dis{opacity:.35;pointer-events:none}

/* Video Modal */
.modal-bg{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.92);backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:20px}
.modal-bg.open{display:flex}
.modal-box{background:#0a0a18;border:1px solid var(--border);border-radius:18px;width:100%;max-width:880px;overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.8)}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.modal-title{font-size:14px;font-weight:700;flex:1;margin-right:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.modal-close{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.08);border:none;color:var(--muted2);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
.modal-close:hover{background:rgba(239,68,68,.2);color:#fca5a5}
.video-wrap{position:relative;padding-bottom:56.25%;background:#000}
.video-wrap video,.video-wrap iframe{position:absolute;inset:0;width:100%;height:100%;border:none}
.modal-info{padding:14px 20px;font-size:12px;color:var(--muted2)}

@media(max-width:640px){nav,.hero,.main{padding-left:16px;padding-right:16px}.hero{padding-top:20px}.vgrid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="glow"></div>
<div class="wrap">
  <nav>
    <a href="/batch.php?id=<?= urlencode($bid) ?>" class="back">← Back to Subjects</a>
    <span class="nav-r">PW<span>Portal</span></span>
  </nav>

  <div class="hero">
    <div class="bc">
      <a href="/dashboard.php">My Courses</a><span class="bc-sep">›</span>
      <a href="/batch.php?id=<?= urlencode($bid) ?>">Batch</a><span class="bc-sep">›</span>
      <span><?= htmlspecialchars($sname) ?></span>
    </div>
    <h1 class="page-title"><?= htmlspecialchars($sname) ?></h1>

    <div class="ctabs">
      <?php
      $types = ['videos' => '🎬 Videos', 'notes' => '📄 Notes', 'DppNotes' => '📝 DPP'];
      foreach ($types as $t => $lbl):
        $url = "?bid=".urlencode($bid)."&sid=".urlencode($sid)."&sname=".urlencode($sname)."&type=".urlencode($t)."&page=1";
      ?>
      <a href="<?= $url ?>" class="ctab <?= $ctype===$t?'active':'' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="main">
    <?php if (empty($items)): ?>
    <div class="empty">
      <div style="font-size:52px;margin-bottom:14px"><?= $ctype==='videos'?'🎬':($ctype==='DppNotes'?'📝':'📄') ?></div>
      <div style="font-size:18px;font-weight:800;margin-bottom:8px">No <?= ucfirst($ctype) ?> Found</div>
      <p style="color:var(--muted2);font-size:14px">Nothing available in this section yet.</p>
    </div>
    <?php elseif ($ctype === 'videos'): ?>
    <div class="vgrid">
      <?php foreach ($items as $v):
        $vtitle = $v['topic'] ?? $v['title'] ?? 'Lecture';
        $vurl   = $v['url'] ?? '';
        // From Python: replace cdn domain and mpd→m3u8
        $m3u8   = str_replace(['d1d34p8vz63oiq','mpd'],['d26g5bnklkwsh4','m3u8'], $vurl);
        $thumb2  = $v['thumbnail'] ?? ($v['image'] ?? '');
        $dur    = $v['duration'] ?? '';
      ?>
      <div class="vcard" onclick="openVideo('<?= htmlspecialchars(addslashes($vtitle)) ?>','<?= htmlspecialchars(addslashes($vurl)) ?>','<?= htmlspecialchars(addslashes($m3u8)) ?>')">
        <div class="vthumb">
          <?php if ($thumb2): ?>
            <img src="<?= htmlspecialchars($thumb2) ?>" alt="" onerror="this.remove()">
          <?php else: ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
              <div style="font-size:36px">🎬</div>
              <div style="font-size:10px;color:var(--muted);text-transform:uppercase">Video Lecture</div>
            </div>
          <?php endif; ?>
          <div class="play-overlay"><div class="play-btn">▶</div></div>
        </div>
        <div class="vbody">
          <span class="vtag">Lecture</span>
          <div class="vtitle"><?= htmlspecialchars($vtitle) ?></div>
          <?php if ($dur): ?><div class="vmeta">⏱ <?= htmlspecialchars($dur) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Notes / DPP list -->
    <div class="nlist">
      <?php foreach ($items as $n):
        $ntitle = $n['topic'] ?? $n['title'] ?? 'Document';
        $links  = [];
        // Try common url fields
        if (!empty($n['url'])) $links[] = $n['url'];
        if (!empty($n['homeworkIds'])) {
          foreach ($n['homeworkIds'] as $hw) {
            if (!empty($hw['attachmentIds']['baseUrl']) && !empty($hw['attachmentIds']['key']))
              $links[] = $hw['attachmentIds']['baseUrl'] . $hw['attachmentIds']['key'];
          }
        }
        $isDpp  = $ctype === 'DppNotes';
      ?>
      <div class="ncard">
        <div class="nicon <?= $isDpp?'nicon-dpp':'nicon-pdf' ?>"><?= $isDpp?'📝':'📄' ?></div>
        <div class="ninfo">
          <div class="nname"><?= htmlspecialchars($ntitle) ?></div>
          <div class="nmeta"><?= $isDpp?'DPP':'Study Material' ?> <?= count($links)?'· '.count($links).' file(s)':'' ?></div>
        </div>
        <?php if (!empty($links)): ?>
          <a href="<?= htmlspecialchars($links[0]) ?>" target="_blank" class="ndl">Download ↗</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <div class="pagi">
      <?php
      $base = "?bid=".urlencode($bid)."&sid=".urlencode($sid)."&sname=".urlencode($sname)."&type=".urlencode($ctype);
      if ($page > 1): ?><a href="<?=$base?>&page=<?=$page-1?>" class="pbt">← Prev</a><?php else: ?><span class="pbt dis">← Prev</span><?php endif; ?>
      <span class="pbt cur"><?= $page ?></span>
      <?php if (count($items) >= 20): ?><a href="<?=$base?>&page=<?=$page+1?>" class="pbt">Next →</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- Video Modal -->
<div class="modal-bg" id="vModal">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title" id="vTitle">Lecture</div>
      <button class="modal-close" onclick="closeVideo()">✕</button>
    </div>
    <div class="video-wrap" id="vWrap"></div>
    <div class="modal-info" id="vInfo"></div>
  </div>
</div>

<script>
function openVideo(title, url, m3u8) {
  document.getElementById('vTitle').textContent = title;
  document.getElementById('vInfo').textContent = 'Stream URL: ' + (m3u8 || url);
  const wrap = document.getElementById('vWrap');

  if (m3u8 && m3u8.includes('.m3u8')) {
    // HLS stream
    wrap.innerHTML = `<video id="vplayer" controls autoplay style="position:absolute;inset:0;width:100%;height:100%;background:#000">
      <source src="${m3u8}" type="application/x-mpegURL">
      <source src="${url}" type="video/mp4">
      Your browser does not support HLS. <a href="${url}" target="_blank" style="color:#f97316">Open in new tab ↗</a>
    </video>`;
    // Try HLS.js
    if (window.Hls && Hls.isSupported()) {
      const hls = new Hls();
      hls.loadSource(m3u8);
      hls.attachMedia(document.getElementById('vplayer'));
    }
  } else if (url) {
    wrap.innerHTML = `<video controls autoplay style="position:absolute;inset:0;width:100%;height:100%;background:#000">
      <source src="${url}">
      <a href="${url}" target="_blank" style="color:#f97316;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.7)">Open Video ↗</a>
    </video>`;
  } else {
    wrap.innerHTML = `<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#6b6b95">No video URL available</div>`;
  }
  document.getElementById('vModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeVideo() {
  document.getElementById('vModal').classList.remove('open');
  document.getElementById('vWrap').innerHTML = '';
  document.body.style.overflow = '';
}

document.getElementById('vModal').addEventListener('click', function(e) {
  if (e.target === this) closeVideo();
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeVideo(); });
</script>
<!-- HLS.js for M3U8 streams -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.4.12/hls.min.js"></script>
</body>
</html>
