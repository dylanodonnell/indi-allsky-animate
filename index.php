<?php
declare(strict_types=1);

date_default_timezone_set('Australia/Sydney');

/**
 * =========================
 * CONFIG
 * =========================
 */

// If empty or commented out, defaults to this script directory (recommended).
$BASEDIR = ''; // e.g. '/home/byronbayobservatory.com.au/allsky';

// How many most-recent images to include in the loop
$NUMBER_OF_IMAGES_TO_LOOP = 48;

/**
 * =========================
 * PATH SETUP
 * =========================
 */

$rootDir = trim((string)$BASEDIR);
if ($rootDir === '') {
    $rootDir = __DIR__;
}

$baseRel = 'images';
$baseAbs = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseRel;

// Collect jpg/jpeg files under ALL subfolders of /images, then pick most recent by mtime
$limit = max(1, (int)$NUMBER_OF_IMAGES_TO_LOOP);

$files = [];
if (is_dir($baseAbs)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseAbs, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $f) {
        if (!$f->isFile()) continue;

        $ext = strtolower($f->getExtension());
        if ($ext !== 'jpg' && $ext !== 'jpeg') continue;

        $absPath = $f->getPathname();

        // Build a web path relative to this index.php location: "images/...."
        $relFromImages = substr($absPath, strlen($baseAbs)); // includes leading slash/backslash
        $relFromImages = str_replace(DIRECTORY_SEPARATOR, '/', $relFromImages);
        $relPath = $baseRel . $relFromImages;

        $files[] = ['path' => $relPath, 'mtime' => $f->getMTime()];
    }
}

// newest first
usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

// take top N newest, then reverse so playback is older -> newer
$files = array_slice($files, 0, $limit);
$files = array_reverse($files);

$imagePaths = array_map(fn($x) => $x['path'], $files);

// Cache-bust based on mtime so browser doesn't “stick” on same image
$imagePathsBusted = array_map(function($x) use ($files) {
    foreach ($files as $f) {
        if ($f['path'] === $x) return $x . '?v=' . $f['mtime'];
    }
    return $x;
}, $imagePaths);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Indi Allsky (Live)</title>
  <style>
    :root{
      --bg:#666;
      --panel:#444;
      --text:#fff;
    }
    html, body {
      height: 100%;
      margin: 0;
      background: var(--bg);
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      color: var(--text);
    }
    .wrap {
      min-height: 100%;
      display: grid;
      place-items: center;
      padding: 18px 16px 22px;
      box-sizing: border-box;
      gap: 14px;
    }
    h1{
      margin: 0;
      font-size: clamp(22px, 2.2vw, 34px);
      font-weight: 700;
      letter-spacing: 0.2px;
      text-align: center;
      text-shadow: 0 6px 18px rgba(0,0,0,.25);
    }
    .frame {
      width: min(1200px, 96vw);
      aspect-ratio: 16 / 9;
      background: var(--panel);
      border-radius: 14px;
      overflow: hidden;
      position: relative;
      box-shadow: 0 10px 30px rgba(0,0,0,.35);
      display: grid;
      place-items: center;
    }
    img#viewer {
      width: 100%;
      height: 100%;
      object-fit: contain;
      transform: translateZ(0);
      backface-visibility: hidden;
    }
    .hud {
      width: min(1200px, 96vw);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      font-size: 13px;
      opacity: 0.92;
    }
    .pill {
      background: rgba(0,0,0,.35);
      padding: 6px 10px;
      border-radius: 999px;
      backdrop-filter: blur(4px);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 70%;
    }
    .small { opacity: 0.85; }
    code { color:#fff; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Indi Allsky (Live)</h1>

    <div class="frame">
      <?php if (count($imagePathsBusted) === 0): ?>
        <div style="text-align:center; padding: 16px;">
          <div style="font-size:18px;font-weight:600;">No images found</div>
          <div class="small">
            Looked under <code><?php echo htmlspecialchars($baseRel, ENT_QUOTES); ?>/</code>
          </div>
        </div>
      <?php else: ?>
        <img id="viewer" src="<?php echo htmlspecialchars($imagePathsBusted[0], ENT_QUOTES); ?>" alt="AllSky">
      <?php endif; ?>
    </div>

    <div class="hud">
      <div class="pill" id="label">Loading…</div>
      <div class="pill small" id="counter"></div>
    </div>
  </div>

<script>
(() => {
  const paths = <?php echo json_encode($imagePathsBusted, JSON_UNESCAPED_SLASHES); ?>;
  if (!paths || paths.length === 0) return;

  const viewer = document.getElementById('viewer');
  const label = document.getElementById('label');
  const counter = document.getElementById('counter');

  // 12 fps => 83.333ms per frame
  const fps = 12;
  const frameMs = Math.round(1000 / fps);

  let idx = 0;
  const cache = new Map();

  function hud() {
    const p = paths[idx].split('?')[0];
    label.textContent = p.split('/').slice(-2).join('/');
    counter.textContent = `${idx + 1} / ${paths.length} — ${fps} fps`;
  }

  // Preload + decode (decode is key to avoiding “white flash”)
  async function preloadAll() {
    const jobs = paths.map((p) => new Promise((resolve) => {
      const img = new Image();
      img.decoding = 'async';
      img.loading = 'eager';
      img.src = p;
      img.onload = async () => {
        try { if (img.decode) await img.decode(); } catch (e) {}
        cache.set(p, img);
        resolve(true);
      };
      img.onerror = () => resolve(false);
    }));
    await Promise.all(jobs);
  }

  let last = 0;
  function tick(ts) {
    if (!last) last = ts;

    if (ts - last >= frameMs) {
      last = ts;

      const next = (idx + 1) % paths.length;
      const p = paths[next];

      if (cache.has(p)) {
        idx = next;
        viewer.src = p;
        hud();
      }
    }

    requestAnimationFrame(tick);
  }

  hud();
  preloadAll().then(() => requestAnimationFrame(tick));
})();
</script>
</body>
</html>
