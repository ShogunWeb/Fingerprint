<?php
// Shared helpers for HTTP parsing and heuristics.

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
