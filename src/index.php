<?php
// index.php — "What can this site see about you?"
// Deployment target: shared hosting (FTP), plain PHP.

/**
 * Fallback for environments that do not provide getallheaders().
 * Normalizes HTTP_* server keys into a lowercased header map.
 */
function get_all_headers_fallback(): array {
  $headers = [];
  foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
      $name = str_replace('_', '-', strtolower(substr($k, 5)));
      $headers[$name] = $v;
    }
  }
  // Some headers are not exposed under HTTP_*.
  foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $sk => $hk) {
    if (isset($_SERVER[$sk])) $headers[$hk] = $_SERVER[$sk];
  }
  ksort($headers);
  return $headers;
}

// Collect request headers with a consistent, case-insensitive map.
$headers = function_exists('getallheaders') ? getallheaders() : get_all_headers_fallback();
$headers_norm = [];
foreach ($headers as $k => $v) {
  $headers_norm[strtolower($k)] = $v;
}
ksort($headers_norm);

// HTTP request snapshot as seen by the server.
$http = [
  'timestamp_server_utc' => gmdate('c'),
  'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
  'remote_port' => $_SERVER['REMOTE_PORT'] ?? null,
  'server_name' => $_SERVER['SERVER_NAME'] ?? null,
  'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  'method' => $_SERVER['REQUEST_METHOD'] ?? null,
  'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
  'query_string' => $_SERVER['QUERY_STRING'] ?? null,
  'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? null,
  'headers' => $headers_norm,
];

// Heuristics for a "proxy/CDN likely in path" hint.
$forward_headers = ['x-forwarded-for','x-real-ip','forwarded','cf-connecting-ip','true-client-ip','x-forwarded-proto','x-forwarded-port','x-remote-ip'];
$present = [];
foreach ($forward_headers as $h) {
  if (isset($headers_norm[$h]) && $headers_norm[$h] !== '') $present[$h] = $headers_norm[$h];
}

// Derived fields not directly from server vars.
$other = [
  'proxy_or_cdn_hint' => count($present) > 0,
  'forwarding_headers_present' => $present,
  'user_agent_server_seen' => $headers_norm['user-agent'] ?? null,
  'accept_language' => $headers_norm['accept-language'] ?? null,
];

// JSON helper to keep output readable and Unicode-friendly.
function j($x): string {
  return json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// Prevent caching so every reload reflects current request/client state.
header('Cache-Control: no-store');
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Infos visibles (HTTP / JS)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 16px; line-height: 1.35; }
    h1 { font-size: 20px; margin: 0 0 12px; }
    h2 { font-size: 16px; margin: 18px 0 8px; }
    pre { background: #f6f6f6; padding: 12px; border-radius: 10px; overflow: auto; }
    .note { font-size: 12px; color: #444; margin-bottom: 12px; }
    button { padding: 10px 12px; border-radius: 10px; border: 1px solid #ccc; background: #fff; cursor: pointer; }
  </style>
</head>
<body>
  <div style="display:flex; gap:12px; align-items:center; margin-bottom:12px;">
    <h1 style="margin:0;" data-i18n="title">Informations visibles sur vous</h1>
    <label style="font-size:12px;">
      <span data-i18n="lang_label">Langue</span>:
      <select id="lang">
        <option value="fr">FR</option>
        <option value="en">EN</option>
      </select>
    </label>
  </div>
  <div class="note" data-i18n="note">
    Cette page affiche ce que le serveur reçoit via HTTP + ce que le navigateur expose via JavaScript.
    Rien n’est “piraté” : ce sont des signaux standards. Certains champs peuvent être masqués par des protections (VPN, anti-fingerprint, etc.).
  </div>

  <h2 data-i18n="server_section">Depuis la connexion HTTP (côté serveur)</h2>
  <pre id="http"><?= htmlspecialchars(j($http), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>

  <h2 data-i18n="js_section">Depuis JavaScript (côté navigateur)</h2>
  <button id="run" data-i18n="collect_button">Collecter les détails navigateur</button>
  <pre id="js" data-i18n="collect_placeholder">(clique sur “Collecter les détails navigateur”)</pre>

  <h2 data-i18n="other_section">Autres / dérivé</h2>
  <pre id="other"><?= htmlspecialchars(j($other), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>

<script>
const i18n = {
  fr: {
    title: 'Informations visibles sur vous',
    lang_label: 'Langue',
    note: 'Cette page affiche ce que le serveur reçoit via HTTP + ce que le navigateur expose via JavaScript. Rien n\u2019est \u201cpirate\u201d : ce sont des signaux standards. Certains champs peuvent etre masques par des protections (VPN, anti-fingerprint, etc.).',
    server_section: 'Depuis la connexion HTTP (cote serveur)',
    js_section: 'Depuis JavaScript (cote navigateur)',
    collect_button: 'Collecter les details navigateur',
    collect_placeholder: '(clique sur "Collecter les details navigateur")',
    other_section: 'Autres / derive'
  },
  en: {
    title: 'Information visible about you',
    lang_label: 'Language',
    note: 'This page shows what the server receives via HTTP and what the browser exposes via JavaScript. Nothing is "hacked": these are standard signals. Some fields may be masked by protections (VPN, anti-fingerprint, etc.).',
    server_section: 'From the HTTP connection (server side)',
    js_section: 'From JavaScript (browser side)',
    collect_button: 'Collect browser details',
    collect_placeholder: '(click "Collect browser details")',
    other_section: 'Other / derived'
  }
};

function detectLang() {
  const stored = localStorage.getItem('ui_lang');
  if (stored && i18n[stored]) return stored;
  const nav = (navigator.language || 'en').toLowerCase();
  return nav.startsWith('fr') ? 'fr' : 'en';
}

function applyTranslations(lang) {
  const dict = i18n[lang] || i18n.en;
  document.querySelectorAll('[data-i18n]').forEach((el) => {
    const key = el.getAttribute('data-i18n');
    if (dict[key]) el.textContent = dict[key];
  });
  document.documentElement.lang = lang;
  localStorage.setItem('ui_lang', lang);
  const select = document.getElementById('lang');
  if (select && select.value !== lang) select.value = lang;
}

// WebGL renderer info can reveal GPU model and driver details.
function webglInfo() {
  const canvas = document.createElement('canvas');
  const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
  if (!gl) return { available: false };

  const dbg = gl.getExtension('WEBGL_debug_renderer_info');
  const vendor = dbg ? gl.getParameter(dbg.UNMASKED_VENDOR_WEBGL) : null;
  const renderer = dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : null;

  return {
    available: true,
    unmaskedVendor: vendor,
    unmaskedRenderer: renderer,
    version: gl.getParameter(gl.VERSION),
    shadingLanguageVersion: gl.getParameter(gl.SHADING_LANGUAGE_VERSION),
  };
}

// Storage availability + quota (where supported).
async function storageInfo() {
  const out = {};
  const safe = async (fn, fallback=null) => { try { return await fn(); } catch { return fallback; } };

  out.localStorage = await safe(async () => (localStorage.setItem('__t','1'), localStorage.removeItem('__t'), true), false);
  out.sessionStorage = await safe(async () => (sessionStorage.setItem('__t','1'), sessionStorage.removeItem('__t'), true), false);
  out.indexedDB = await safe(async () => !!window.indexedDB, false);

  out.storageEstimate = await safe(async () => {
    if (!navigator.storage?.estimate) return null;
    return await navigator.storage.estimate(); // {usage, quota}
  }, null);

  return out;
}

// Device enumeration (counts only; labels may be hidden without permission).
async function mediaDevicesInfo() {
  const safe = async (fn, fallback=null) => { try { return await fn(); } catch { return fallback; } };
  return await safe(async () => {
    if (!navigator.mediaDevices?.enumerateDevices) return null;
    const devices = await navigator.mediaDevices.enumerateDevices();
    const counts = devices.reduce((acc, d) => ((acc[d.kind] = (acc[d.kind] || 0) + 1), acc), {});
    return { counts, hasLabels: devices.some(d => !!d.label) };
  }, null);
}

// Basic, non-permissioned browser signals.
function basicJSInfo() {
  const n = navigator;
  const s = screen;

  return {
    timestamp_client: new Date().toISOString(),
    location: { href: location.href, origin: location.origin, pathname: location.pathname },
    navigator: {
      userAgent: n.userAgent,
      platform: n.platform,
      language: n.language,
      languages: n.languages,
      cookieEnabled: n.cookieEnabled,
      doNotTrack: n.doNotTrack,
      vendor: n.vendor,
      deviceMemory_GB: n.deviceMemory ?? null,
      hardwareConcurrency: n.hardwareConcurrency ?? null,
      maxTouchPoints: n.maxTouchPoints ?? null,
      pdfViewerEnabled: n.pdfViewerEnabled ?? null,
      webdriver: n.webdriver ?? null,
    },
    screen: {
      width: s.width, height: s.height,
      availWidth: s.availWidth, availHeight: s.availHeight,
      colorDepth: s.colorDepth, pixelDepth: s.pixelDepth,
      devicePixelRatio: window.devicePixelRatio
    },
    viewport: { innerWidth: innerWidth, innerHeight: innerHeight },
    timezone: {
      tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
      offsetMinutes: new Date().getTimezoneOffset()
    }
  };
}

// Aggregate all client-side signals (some are async).
async function collectAll() {
  const info = basicJSInfo();
  info.webgl = webglInfo();
  info.storage = await storageInfo();
  info.mediaDevices = await mediaDevicesInfo();
  return info;
}

// Run collection on demand to make behavior explicit.
document.getElementById('run').addEventListener('click', async () => {
  const data = await collectAll();
  document.getElementById('js').textContent = JSON.stringify(data, null, 2);
});

// Initialize UI language from browser or stored choice.
document.getElementById('lang').addEventListener('change', (e) => {
  applyTranslations(e.target.value);
});
applyTranslations(detectLang());
</script>
</body>
</html>
