<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
session_boot(); require_login();

$token  = $_SESSION['u_token'];
$bid    = trim($_GET['bid'] ?? '');
$sid    = trim($_GET['sid'] ?? '');
$sn     = trim($_GET['sn']  ?? 'Subject');
$bt     = trim($_GET['bt']  ?? 'Batch');
if (!$bid || !$sid) { header('Location: /dashboard.php'); exit; }

$ctype  = in_array($_GET['t'] ?? '', ['videos','notes','DppNotes']) ? $_GET['t'] : 'videos';
$page   = max(1, (int)($_GET['p'] ?? 1));
$r      = api_get_contents($token, $bid, $sid, $ctype, $page);
$items  = $r['data'] ?? [];
$typeLabels = ['videos' => '🎬 Videos', 'notes' => '📄 Notes', 'DppNotes' => '📝 DPP'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title><?= htmlspecialchars($sn) ?> — PW Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#06060f;--card:#0c0c1e;--b1:#1a1a35;--b2:#242445;--acc:#f97316;--text:#f0f0ff;--muted:#7070a0;--m2:#a0a0c8;--ok:#22c55e}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.glow-bg{position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse at 80% 10%,rgba(249,115,22,.09),transparent 55%)}
.shell{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}
nav{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;background:rgba(6,6,15,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--b1);position:sticky;top:0;z-index:50}
.back{display:flex;align-items:center;gap:7px;color:var(--m2);text-decoration:none;font-size:13px;font-weight:600;transition:color .2s}
.back:hover{color:#f97316}
.nbrand{font-weight:800;font-size:14px}.nbrand span{color:#f97316}

.hero{padding:24px 24px 0;border-bottom:1px solid var(--b1)}
.bc{display:flex;gap:5px;align-items:center;font-size:11px;color:var(--muted);margin-bottom:12px;flex-wrap:wrap}
.bc a{color:var(--muted);text-decoration:none}.bc a:hover{color:#f97316}
.bc-s{margin:0 2px;color:var(--muted)}
.ptitle{font-size:clamp(1.1rem,4vw,1.7rem);font-weight:800;letter-spacing:-.02em;margin-bottom:16px}

/* content tabs */
.ctabs{display:flex;border-bottom:none;gap:0}
.ct{padding:11px 18px;border:none;background:none;color:var(--muted);font-family:'Inter',sans-serif;font-size:13px;font-weight:600;cursor:pointer;border-bottom:2.5px solid transparent;transition:all .2s;text-decoration:none;display:inline-block;white-space:nowrap}
.ct:hover{color:var(--text)}
.ct.on{color:#f97316;border-bottom-color:#f97316}

/* main */
.main{flex:1;padding:24px}

/* video grid */
.vgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.vc{background:var(--card);border:1.5px solid var(--b1);border-radius:14px;overflow:hidden;cursor:pointer;transition:all .3s}
.vc:hover{border-color:rgba(249,115,22,.4);transform:translateY(-3px);box-shadow:0 14px 32px rgba(0,0,0,.5)}
.vthumb{aspect-ratio:16/9;background:linear-gradient(135deg,#0e0e22,#181832);position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden}
.vthumb img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.vthumb .vfb{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px}
.vfb-i{font-size:30px}
.vfb-t{font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}
.play-ov{position:absolute;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .25s}
.vc:hover .play-ov{opacity:1}
.play-ic{width:48px;height:48px;border-radius:50%;background:#f97316;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 4px 16px rgba(249,115,22,.6)}
.vbody{padding:12px 14px}
.vtag{display:inline-block;padding:2px 7px;border-radius:100px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);font-size:9px;font-weight:700;color:#fb923c;text-transform:uppercase;margin-bottom:6px}
.vtitle{font-size:13px;font-weight:700;line-height:1.4;margin-bottom:4px}
.vmeta{font-size:11px;color:var(--m2)}

/* notes/dpp list */
.nlist{display:flex;flex-direction:column;gap:9px}
.nc{background:var(--card);border:1.5px solid var(--b1);border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:all .25s}
.nc:hover{border-color:rgba(249,115,22,.35);transform:translateX(3px)}
.nic{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.nic-pdf{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)}
.nic-dpp{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.2)}
.ninfo{flex:1;min-width:0}
.nname{font-size:13px;font-weight:600;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nmeta{font-size:11px;color:var(--m2)}
.ndl{padding:6px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);border-radius:7px;color:#fb923c;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap;transition:all .2s}
.ndl:hover{background:rgba(249,115,22,.2)}

.empty{text-align:center;padding:50px 20px;border:1px dashed var(--b1);border-radius:14px}

/* pagi */
.pagi{display:flex;gap:8px;justify-content:center;margin-top:24px}
.pb{padding:7px 16px;border-radius:8px;border:1px solid var(--b1);background:rgba(255,255,255,.04);color:var(--text);text-decoration:none;font-size:12px;font-weight:600;transition:all .2s}
.pb:hover,.pb.cur{background:#f97316;border-color:#f97316;color:#fff}
.pb.dis{opacity:.3;pointer-events:none}

/* Video Modal */
.modal{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.9);backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:16px}
.modal.open{display:flex}
.mbox{background:#08081a;border:1px solid var(--b1);border-radius:16px;width:100%;max-width:860px;overflow:hidden;box-shadow:0 40px 80px rgba(0,0,0,.9)}
.mhead{display:flex;align-items:center;padding:14px 18px;border-bottom:1px solid var(--b1);gap:10px}
.mtitle{flex:1;font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mclose{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.08);border:none;color:var(--m2);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.mclose:hover{background:rgba(239,68,68,.2);color:#fca5a5}
.vwrap{position:relative;padding-bottom:56.25%;background:#000;min-height:200px}
.vwrap video,.vwrap iframe{position:absolute;inset:0;width:100%;height:100%;border:none}
.minfo{padding:10px 18px;font-size:11px;color:var(--muted);font-family:'JetBrains Mono',monospace;word-break:break-all}

@media(max-width:520px){nav,.hero,.main{padding-left:14px;padding-right:14px}.hero{padding-top:18px}.vgrid{grid-template-columns:1fr}.ct{padding:10px 12px;font-size:12px}}
</style>
</head>
<body>
<div class="glow-bg"></div>
<div class="shell">
  <nav>
    <a href="/batch.php?id=<?= urlencode($bid) ?>" class="back">← <?= htmlspecialchars(mb_substr($bt,0,20)) ?></a>
    <span class="nbrand">PW<span>Portal</span></span>
  </nav>

  <div class="hero">
    <div class="bc">
      <a href="/dashboard.php">Courses</a><span class="bc-s">›</span>
      <a href="/batch.php?id=<?= urlencode($bid) ?>"><?= htmlspecialchars(mb_substr($bt,0,20)) ?></a><span class="bc-s">›</span>
      <span><?= htmlspecialchars($sn) ?></span>
    </div>
    <h1 class="ptitle"><?= htmlspecialchars($sn) ?></h1>
    <div class="ctabs">
      <?php foreach ($typeLabels as $t => $lbl):
        $u = "?bid=".urlencode($bid)."&sid=".urlencode($sid)."&sn=".urlencode($sn)."&bt=".urlencode($bt)."&t=".urlencode($t)."&p=1";
      ?>
      <a href="<?= $u ?>" class="ct <?= $ctype===$t?'on':'' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="main">
    <?php if (empty($items)): ?>
      <div class="empty">
        <div style="font-size:44px;margin-bottom:12px"><?= $ctype==='videos'?'🎬':($ctype==='DppNotes'?'📝':'📄') ?></div>
        <div style="font-size:16px;font-weight:800;margin-bottom:6px">No <?= $typeLabels[$ctype] ?> found</div>
        <p style="color:var(--m2);font-size:13px">Nothing available in this section.</p>
      </div>
    <?php elseif ($ctype === 'videos'): ?>
      <div class="vgrid">
        <?php foreach ($items as $v):
          $vt    = $v['topic'] ?? ($v['title'] ?? 'Lecture');
          $vurl  = $v['url']   ?? '';
          // From Python: replace CDN domain + mpd→m3u8
          $m3u8  = str_replace(['d1d34p8vz63oiq','.mpd'],['d26g5bnklkwsh4','.m3u8'], $vurl);
          $vthb  = $v['thumbnail'] ?? ($v['image'] ?? '');
          $vdur  = $v['duration'] ?? '';
          $venc  = htmlspecialchars(addslashes($vt));
          $uenc  = htmlspecialchars(addslashes($vurl));
          $menc  = htmlspecialchars(addslashes($m3u8));
        ?>
        <div class="vc" onclick="openVideo('<?= $venc ?>','<?= $uenc ?>','<?= $menc ?>')">
          <div class="vthumb">
            <?php if ($vthb): ?>
              <img src="<?= htmlspecialchars($vthb) ?>" alt="" loading="lazy" onerror="this.style.opacity=0">
            <?php endif; ?>
            <div class="vfb"><div class="vfb-i">🎬</div><div class="vfb-t">Lecture</div></div>
            <div class="play-ov"><div class="play-ic">▶</div></div>
          </div>
          <div class="vbody">
            <span class="vtag">Video</span>
            <div class="vtitle"><?= htmlspecialchars($vt) ?></div>
            <?php if ($vdur): ?><div class="vmeta">⏱ <?= htmlspecialchars($vdur) ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <!-- Notes / DPP -->
      <div class="nlist">
        <?php foreach ($items as $n):
          $nt    = $n['topic'] ?? ($n['title'] ?? 'Document');
          $links = [];
          if (!empty($n['url'])) $links[] = $n['url'];
          if (!empty($n['homeworkIds'])) {
            foreach ($n['homeworkIds'] as $hw) {
              $bu = $hw['attachmentIds']['baseUrl'] ?? '';
              $ky = $hw['attachmentIds']['key']     ?? '';
              if ($bu && $ky) $links[] = $bu . $ky;
            }
          }
          $isDpp = ($ctype === 'DppNotes');
        ?>
        <div class="nc">
          <div class="nic <?= $isDpp?'nic-dpp':'nic-pdf' ?>"><?= $isDpp?'📝':'📄' ?></div>
          <div class="ninfo">
            <div class="nname"><?= htmlspecialchars($nt) ?></div>
            <div class="nmeta"><?= $isDpp?'DPP':'Study Material' ?><?= count($links)?' · '.count($links).' file(s)':'' ?></div>
          </div>
          <?php if (!empty($links)): ?>
            <a href="<?= htmlspecialchars($links[0]) ?>" target="_blank" class="ndl">Open ↗</a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php
    $base = "?bid=".urlencode($bid)."&sid=".urlencode($sid)."&sn=".urlencode($sn)."&bt=".urlencode($bt)."&t=".urlencode($ctype);
    ?>
    <div class="pagi">
      <?php if ($page>1): ?><a href="<?=$base?>&p=<?=$page-1?>" class="pb">← Prev</a><?php else: ?><span class="pb dis">← Prev</span><?php endif; ?>
      <span class="pb cur"><?= $page ?></span>
      <?php if (count($items)>=20): ?><a href="<?=$base?>&p=<?=$page+1?>" class="pb">Next →</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- Video Modal -->
<div class="modal" id="vm">
  <div class="mbox">
    <div class="mhead">
      <span class="mtitle" id="vmt">Loading...</span>
      <button class="mclose" onclick="closeV()">✕</button>
    </div>
    <div class="vwrap" id="vwrap"></div>
    <div class="minfo" id="vinfo"></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.4.12/hls.min.js"></script>
<script>
let hlsInst = null;

function openVideo(title, url, m3u8) {
  document.getElementById('vmt').textContent = title;
  const wrap = document.getElementById('vwrap');
  document.getElementById('vinfo').textContent = 'URL: ' + (m3u8 || url || 'N/A');

  if (hlsInst) { hlsInst.destroy(); hlsInst = null; }

  const vid = document.createElement('video');
  vid.controls = true;
  vid.autoplay = true;
  vid.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;background:#000';
  wrap.innerHTML = '';
  wrap.appendChild(vid);

  if (m3u8 && Hls && Hls.isSupported() && m3u8.includes('.m3u8')) {
    hlsInst = new Hls({ enableWorker: false });
    hlsInst.loadSource(m3u8);
    hlsInst.attachMedia(vid);
    hlsInst.on(Hls.Events.MANIFEST_PARSED, () => vid.play().catch(() => {}));
    hlsInst.on(Hls.Events.ERROR, (e, d) => {
      if (d.fatal && url) { vid.src = url; vid.play().catch(() => {}); }
    });
  } else if (m3u8 && vid.canPlayType('application/vnd.apple.mpegurl')) {
    vid.src = m3u8;
  } else if (url) {
    vid.src = url;
  } else {
    wrap.innerHTML = '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#7070a0;font-size:14px">No video URL available</div>';
  }

  document.getElementById('vm').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeV() {
  if (hlsInst) { hlsInst.destroy(); hlsInst = null; }
  document.getElementById('vwrap').innerHTML = '';
  document.getElementById('vm').classList.remove('open');
  document.body.style.overflow = '';
}

document.getElementById('vm').addEventListener('click', e => { if (e.target === e.currentTarget) closeV(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeV(); });
</script>
</body>
</html>
