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

<script src="app.js"></script>
</body>
</html>
