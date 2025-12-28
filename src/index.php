<?php
// index.php — "What can this site see about you?"
// Deployment target: shared hosting (FTP), plain PHP.

require_once __DIR__ . '/lib.php';

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

// Log each access to a daily file in /logs (timestamp, IP, user-agent).
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
  @mkdir($log_dir, 0775, true);
}
$log_date = gmdate('ymd');
$log_file = $log_dir . '/' . $log_date . '.log';
$log_ts = $http['timestamp_server_utc'] ?? gmdate('c');
$log_ip = $ip ?? '-';
$log_ua = $user_agent ?? '-';
$log_ua = str_replace(["\t", "\r", "\n"], ' ', $log_ua);
$log_line = $log_ts . "\t" . $log_ip . "\t" . $log_ua . "\n";
@file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);

// Prevent caching so every reload reflects current request/client state.
header('Cache-Control: no-store');
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Infos visibles (HTTP / JS)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="styles.css" />
</head>
<body data-http-accept-language="<?= htmlspecialchars($accept_language ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
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
  <!-- Privacy notice for logging and GeoIP usage. -->
  <div class="note" data-i18n="privacy_note">
    Cette page enregistre l’IP et le User-Agent (logs locaux). La géolocalisation IP est effectuée via ipapi.co uniquement si vous cliquez sur le bouton.
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
  <!-- Client-side summary cards for key JS fingerprinting signals. -->
  <div class="http-cards" id="js-summary">
    <div class="card">
      <div class="card-title" data-i18n="js_languages">Langues (JS)</div>
      <div id="js-lang-list" class="pills"></div>
      <div class="card-hint">
        <span data-i18n="js_language_match_label">Cohérence HTTP</span>:
        <span id="js-lang-match" class="status-pill status-unknown">-</span>
      </div>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="js_gpu">GPU (WebGL)</div>
      <div id="js-gpu" class="card-value"><span class="muted" data-i18n="collecting_placeholder">(collecte en cours...)</span></div>
      <div id="js-gpu-meta" class="card-hint"></div>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="js_cpu">CPU logiques</div>
      <div id="js-cpu" class="card-value"><span class="muted" data-i18n="collecting_placeholder">(collecte en cours...)</span></div>
      <div class="card-hint" data-i18n="js_cpu_hint">navigator.hardwareConcurrency</div>
    </div>

    <div class="card">
      <div class="card-title" data-i18n="js_screen">Écran</div>
      <div id="js-screen" class="card-value"><span class="muted" data-i18n="collecting_placeholder">(collecte en cours...)</span></div>
      <div id="js-device" class="card-hint"></div>
    </div>
  </div>
  <details class="accordion">
    <summary data-i18n="js_section_summary">Afficher les détails navigateur (JSON)</summary>
    <pre id="js">(collecte en cours…)</pre>
  </details>

<script src="app-utils.js"></script>
<script src="device-presets.js"></script>
<script src="i18n.js"></script>
<script src="app.js"></script>
</body>
</html>
