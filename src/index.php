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

/**
 * Parse a weighted header list like "en-US,en;q=0.9".
 * Returns tokens sorted by descending weight.
 */
function parse_weighted_header(?string $header): array {
  if (!$header) return [];
  $parts = array_map('trim', explode(',', $header));
  $out = [];
  foreach ($parts as $part) {
    if ($part === '') continue;
    $q = 1.0;
    $token = $part;
    if (strpos($part, ';') !== false) {
      [$token, $params] = array_map('trim', explode(';', $part, 2));
      if (preg_match('/q=([0-9.]+)/i', $params, $m)) {
        $q = (float) $m[1];
      }
    }
    $out[] = ['token' => $token, 'q' => $q];
  }
  usort($out, function ($a, $b) {
    return $a['q'] <=> $b['q'];
  });
  return array_reverse($out);
}

/**
 * Extract ordered language tags from Accept-Language.
 */
function parse_accept_language(?string $header): array {
  $items = parse_weighted_header($header);
  $seen = [];
  $out = [];
  foreach ($items as $item) {
    $key = strtolower($item['token']);
    if ($key === '' || isset($seen[$key])) continue;
    $seen[$key] = true;
    $out[] = $item['token'];
  }
  return $out;
}

/**
 * Extract ordered encoding tokens from Accept-Encoding.
 */
function parse_accept_encoding(?string $header): array {
  $items = parse_weighted_header($header);
  $seen = [];
  $out = [];
  foreach ($items as $item) {
    $key = strtolower($item['token']);
    if ($key === '' || isset($seen[$key])) continue;
    $seen[$key] = true;
    $out[] = $item['token'];
  }
  return $out;
}

/**
 * Reverse an IPv4 address for DNS-based Tor exit checks.
 */
function reverse_ipv4(?string $ip): ?string {
  if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return null;
  return implode('.', array_reverse(explode('.', $ip)));
}

/**
 * Check if the client IP is listed as a Tor exit for this server/port.
 * Returns true/false when a DNS lookup is possible, null otherwise.
 */
function tor_exit_dns_check(?string $client_ip, ?string $server_ip, $server_port): ?bool {
  $client_rev = reverse_ipv4($client_ip);
  $server_rev = reverse_ipv4($server_ip);
  $port = (int) $server_port;
  if (!$client_rev || !$server_rev || $port <= 0) return null;

  $host = $client_rev . '.' . $port . '.' . $server_rev . '.ip-port.exitlist.torproject.org';
  if (function_exists('checkdnsrr')) {
    return checkdnsrr($host, 'A');
  }
  if (function_exists('dns_get_record')) {
    $records = dns_get_record($host, DNS_A);
    return !empty($records);
  }
  return null;
}

/**
 * Heuristic: detect explicit Tor Browser tokens in the User-Agent.
 */
function is_tor_browser_ua(?string $ua): bool {
  if (!$ua) return false;
  return (stripos($ua, 'TorBrowser') !== false) || (stripos($ua, 'Tor Browser') !== false);
}

/**
 * Lightweight user-agent parsing to extract browser and OS labels.
 */
function parse_user_agent(?string $ua): array {
  if (!$ua) return ['browser' => null, 'os' => null];
  $browser = null;
  $os = null;

  if (preg_match('/Edg\/([\d\.]+)/', $ua, $m)) {
    $browser = 'Edge ' . $m[1];
  } elseif (preg_match('/OPR\/([\d\.]+)/', $ua, $m)) {
    $browser = 'Opera ' . $m[1];
  } elseif (preg_match('/Chrome\/([\d\.]+)/', $ua, $m)) {
    $browser = 'Chrome ' . $m[1];
  } elseif (preg_match('/Firefox\/([\d\.]+)/', $ua, $m)) {
    $browser = 'Firefox ' . $m[1];
  } elseif (preg_match('/Version\/([\d\.]+).*Safari/', $ua, $m)) {
    $browser = 'Safari ' . $m[1];
  }

  if (preg_match('/Windows NT 10\.0/', $ua)) {
    $os = 'Windows 10/11';
  } elseif (preg_match('/Windows NT 6\.3/', $ua)) {
    $os = 'Windows 8.1';
  } elseif (preg_match('/Windows NT 6\.2/', $ua)) {
    $os = 'Windows 8';
  } elseif (preg_match('/Windows NT 6\.1/', $ua)) {
    $os = 'Windows 7';
  } elseif (preg_match('/Android ([\d\.]+)/', $ua, $m)) {
    $os = 'Android ' . $m[1];
  } elseif (preg_match('/iPhone OS ([\d_]+)/', $ua, $m)) {
    $os = 'iOS ' . str_replace('_', '.', $m[1]);
  } elseif (preg_match('/iPad.*OS ([\d_]+)/', $ua, $m)) {
    $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
  } elseif (preg_match('/Mac OS X ([\d_]+)/', $ua, $m)) {
    $os = 'macOS ' . str_replace('_', '.', $m[1]);
  } elseif (preg_match('/Linux/', $ua)) {
    $os = 'Linux';
  }

  return ['browser' => $browser, 'os' => $os];
}

// JSON helper to keep output readable and Unicode-friendly.
function j($x): string {
  return json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// Extract key HTTP fingerprinting signals for the UI summary.
$ip = $http['remote_addr'] ?? null;
$accept_language = $headers_norm['accept-language'] ?? null;
$accept_encoding = $headers_norm['accept-encoding'] ?? null;
$user_agent = $headers_norm['user-agent'] ?? null;
$language_tags = parse_accept_language($accept_language);
$encoding_tags = parse_accept_encoding($accept_encoding);
$ua_info = parse_user_agent($user_agent);
// Detect zstd support for a browser-corroboration hint.
$has_zstd = false;
foreach ($encoding_tags as $tag) {
  if (strtolower($tag) === 'zstd') {
    $has_zstd = true;
    break;
  }
}

// Proxy/VPN heuristic: presence of forwarding headers.
$forward_headers = ['x-forwarded-for','x-real-ip','forwarded','via','cf-connecting-ip','true-client-ip','x-forwarded-proto','x-forwarded-port','x-remote-ip'];
$forward_present = [];
foreach ($forward_headers as $h) {
  if (isset($headers_norm[$h]) && $headers_norm[$h] !== '') $forward_present[$h] = $headers_norm[$h];
}
$vpn_status = count($forward_present) > 0 ? 'maybe' : 'unknown';

// Tor checks: DNS exit list + UA token heuristic.
$server_ip = $_SERVER['SERVER_ADDR'] ?? null;
$server_port = $_SERVER['SERVER_PORT'] ?? ($http['https'] ? 443 : 80);
$tor_exit = tor_exit_dns_check($ip, $server_ip, $server_port);
$tor_ua = is_tor_browser_ua($user_agent);
$tor_status = ($tor_exit === true) ? 'yes' : ($tor_ua ? 'maybe' : 'unknown');
$tor_dns_status = ($tor_exit === null) ? 'failed' : 'success';

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
    details.accordion { margin-bottom: 12px; }
    details.accordion > summary { cursor: pointer; font-weight: 600; margin-bottom: 8px; }
    details.accordion > summary::marker { color: #666; }
    .http-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .card { background: #fff; border: 1px solid #e6e6e6; border-radius: 12px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .card-title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: #666; margin-bottom: 6px; }
    .card-value { font-size: 14px; font-weight: 600; margin-bottom: 6px; word-break: break-word; }
    .card-hint { font-size: 12px; color: #666; }
    .pills { display: flex; flex-wrap: wrap; gap: 6px; }
    .pill { background: #f0f2f5; border-radius: 999px; padding: 2px 8px; font-size: 12px; }
    .pill.highlight { background: #ffedd5; color: #7c2d12; border: 1px solid #fed7aa; }
    .kv { display: flex; gap: 8px; font-size: 13px; margin: 4px 0; }
    .kv-label { color: #666; min-width: 70px; }
    .muted { color: #888; font-weight: 400; }
    .status-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid transparent; }
    .status-yes { background: #fee2e2; color: #7f1d1d; border-color: #fecaca; }
    .status-no { background: #dcfce7; color: #14532d; border-color: #bbf7d0; }
    .status-unknown { background: #f3f4f6; color: #374151; border-color: #e5e7eb; }
    .status-maybe { background: #fef3c7; color: #78350f; border-color: #fde68a; }
    .geo-card { background: #fff; border: 1px solid #e6e6e6; border-radius: 12px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); margin-bottom: 12px; }
    .geo-details { margin-top: 8px; }
    .geo-map { margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid #eee; }
    .geo-map iframe { width: 100%; height: 260px; border: 0; display: block; }
    .hidden { display: none; }
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
  <div class="http-cards">
    <div class="card">
      <div class="card-title" data-i18n="http_ip">IP</div>
      <div class="card-value">
        <?php if ($ip): ?>
          <?= htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        <?php else: ?>
          <span class="muted" data-i18n="value_unavailable">Indisponible</span>
        <?php endif; ?>
      </div>
      <div class="card-hint" data-i18n="http_ip_hint">Adresse IP vue par le serveur.</div>
      <!-- Triggers a client-side GeoIP lookup (ipapi.co) on demand. -->
      <button id="geo-run" data-i18n="geo_button">Afficher la geolocalisation</button>
      <div id="geo-status" class="card-hint"></div>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="http_accept_language">Accept-Language</div>
      <?php if ($language_tags): ?>
        <div class="pills">
          <?php foreach ($language_tags as $tag): ?>
            <span class="pill"><?= htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card-value"><span class="muted" data-i18n="value_unavailable">Indisponible</span></div>
      <?php endif; ?>
      <div class="card-hint" data-i18n="http_accept_language_hint">Codes de langue preferes declares par le navigateur.</div>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="http_user_agent">User-Agent</div>
      <div class="kv">
        <span class="kv-label" data-i18n="ua_browser_label">Navigateur</span>
        <span class="kv-value">
          <?php if (!empty($ua_info['browser'])): ?>
            <?= htmlspecialchars($ua_info['browser'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          <?php else: ?>
            <span class="muted" data-i18n="value_unavailable">Indisponible</span>
          <?php endif; ?>
        </span>
      </div>
      <div class="kv">
        <span class="kv-label" data-i18n="ua_os_label">OS</span>
        <span class="kv-value">
          <?php if (!empty($ua_info['os'])): ?>
            <?= htmlspecialchars($ua_info['os'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          <?php else: ?>
            <span class="muted" data-i18n="value_unavailable">Indisponible</span>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-hint" data-i18n="http_user_agent_hint">Extrait du User-Agent HTTP.</div>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="http_accept_encoding">Accept-Encoding</div>
      <?php if ($encoding_tags): ?>
        <div class="pills">
          <?php foreach ($encoding_tags as $tag): ?>
            <?php $is_zstd = strtolower($tag) === 'zstd'; ?>
            <span class="pill<?= $is_zstd ? ' highlight' : '' ?>"><?= htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card-value"><span class="muted" data-i18n="value_unavailable">Indisponible</span></div>
      <?php endif; ?>
      <div class="card-hint" data-i18n="http_accept_encoding_hint">Methodes de compression supportees par le navigateur.</div>
      <?php if ($has_zstd): ?>
        <div class="card-hint" data-i18n="encoding_zstd_hint">Le support de zstd est souvent actif sur Firefox, utile pour corroborer le navigateur.</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="http_vpn">VPN / Proxy</div>
      <div class="card-value">
        <span id="vpn-status" class="status-pill status-unknown" data-status="<?= htmlspecialchars($vpn_status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-i18n="vpn_unknown">Inconnu</span>
      </div>
      <div class="card-hint" data-i18n="vpn_hint">Heuristique basee sur des headers de transfert.</div>
      <div class="card-hint">
        <span data-i18n="vpn_headers_label">Headers detectes</span>:
        <?php if ($forward_present): ?>
          <span class="pills">
            <?php foreach (array_keys($forward_present) as $h): ?>
              <span class="pill"><?= htmlspecialchars($h, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <?php endforeach; ?>
          </span>
        <?php else: ?>
          <span class="muted" data-i18n="vpn_headers_none">Aucun</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="http_tor">Tor</div>
      <div class="card-value">
        <span id="tor-status" class="status-pill status-unknown" data-status="<?= htmlspecialchars($tor_status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-i18n="tor_unknown">Inconnu</span>
      </div>
      <div class="card-hint" data-i18n="tor_hint">Sortie Tor via DNS + motif User-Agent explicite.</div>
      <div class="card-hint">
        <span data-i18n="tor_dns_label">Verification DNS</span>:
        <?php if ($tor_dns_status === 'success'): ?>
          <span data-i18n="tor_dns_success">reussie</span>
        <?php else: ?>
          <span data-i18n="tor_dns_failed">echouee</span>
        <?php endif; ?>
      </div>
      <?php if ($tor_ua && $tor_exit !== true): ?>
        <div class="card-hint" data-i18n="tor_ua_hint">User-Agent semblable a Tor Browser (heuristique).</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- GeoIP result panel; hidden until the user clicks the button. -->
  <div class="geo-card hidden" id="geo-card">
    <div id="geo-details" class="geo-details hidden">
      <div class="kv">
        <span class="kv-label" data-i18n="geo_country_label">Pays</span>
        <span id="geo-country" class="kv-value"></span>
      </div>
      <div class="kv">
        <span class="kv-label" data-i18n="geo_org_label">Organisation</span>
        <span id="geo-org" class="kv-value"></span>
      </div>
      <div class="kv">
        <span class="kv-label" data-i18n="geo_net_label">Reseau</span>
        <span id="geo-net" class="kv-value"></span>
      </div>
    </div>
  </div>

  <details class="accordion">
    <summary data-i18n="server_section_summary">Afficher les détails HTTP (JSON)</summary>
    <pre id="http"><?= htmlspecialchars(j($http), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
  </details>

  <h2 data-i18n="js_section">Depuis JavaScript (côté navigateur)</h2>
  <details class="accordion">
    <summary data-i18n="js_section_summary">Afficher les détails navigateur (JSON)</summary>
    <pre id="js">(collecte en cours…)</pre>
  </details>

<script src="app.js"></script>
</body>
</html>
