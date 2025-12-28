// UI copy for FR/EN and status messages.
const i18n = {
  fr: {
    title: 'Informations visibles sur vous',
    lang_label: 'Langue',
    note: 'Cette page affiche ce que le serveur reçoit via HTTP + ce que le navigateur expose via JavaScript. Rien n\u2019est \u201cpiraté\u201d : ce sont des signaux standards. Certains champs peuvent être masqués par des protections (VPN, anti-fingerprint, etc.).',
    server_section: 'Depuis la connexion HTTP (côté serveur)',
    server_section_summary: 'Afficher les détails HTTP (JSON)',
    http_ip: 'IP',
    http_ip_hint: 'Adresse IP vue par le serveur.',
    http_accept_language: 'Accept-Language',
    http_accept_language_hint: 'Codes de langue préférés déclarés par le navigateur.',
    http_user_agent: 'User-Agent',
    http_user_agent_hint: 'Extrait du User-Agent HTTP.',
    http_accept_encoding: 'Accept-Encoding',
    http_accept_encoding_hint: 'Méthodes de compression supportées par le navigateur.',
    encoding_zstd_hint: 'Le support de zstd est souvent actif sur Firefox, utile pour corroborer le navigateur.',
    http_vpn: 'VPN / Proxy',
    vpn_hint: 'Heuristique basée sur des headers de transfert.',
    vpn_unknown: 'Inconnu',
    vpn_detected: 'Détecté',
    vpn_not_detected: 'Non détecté',
    vpn_possible: 'Possible',
    vpn_headers_label: 'Headers détectés',
    vpn_headers_none: 'Aucun',
    http_tor: 'Tor',
    tor_hint: 'Sortie Tor via DNS + motif User-Agent explicite.',
    tor_unknown: 'Inconnu',
    tor_detected: 'Détecté',
    tor_not_detected: 'Non détecté',
    tor_possible: 'Possible',
    tor_ua_hint: 'User-Agent semblable à Tor Browser (heuristique).',
    tor_dns_label: 'Vérification DNS',
    tor_dns_success: 'réussie',
    tor_dns_failed: 'échouée',
    ua_browser_label: 'Navigateur',
    ua_os_label: 'OS',
    value_unavailable: 'Indisponible',
    geo_button: 'Afficher la géolocalisation',
    geo_country_label: 'Pays',
    geo_org_label: 'Organisation',
    geo_net_label: 'Réseau',
    geo_loading: 'Requête en cours...',
    geo_error: 'Impossible de récupérer les données de géolocalisation.',
    js_section: 'Depuis JavaScript (côté navigateur)',
    js_section_summary: 'Afficher les détails navigateur (JSON)',
    collecting_placeholder: '(collecte en cours...)',
    js_languages: 'Langues (JS)',
    js_language_match_label: 'Cohérence HTTP',
    js_language_match_yes: 'Concorde',
    js_language_match_no: 'Différent',
    js_language_match_unknown: 'Inconnu',
    js_gpu: 'GPU (WebGL)',
    js_cpu: 'CPU logiques',
    js_cpu_hint: 'navigator.hardwareConcurrency',
    js_screen: 'Écran',
    js_screen_size_label: 'Taille',
    js_screen_dpr_label: 'DPR',
    js_screen_color_label: 'Profondeur couleur',
    js_screen_touch_label: 'Points tactiles',
    js_screen_dpr_help: 'DPR = ratio entre pixels physiques et CSS.'
  },
  en: {
    title: 'Information visible about you',
    lang_label: 'Language',
    note: 'This page shows what the server receives via HTTP and what the browser exposes via JavaScript. Nothing is "hacked": these are standard signals. Some fields may be masked by protections (VPN, anti-fingerprint, etc.).',
    server_section: 'From the HTTP connection (server side)',
    server_section_summary: 'Show HTTP details (JSON)',
    http_ip: 'IP',
    http_ip_hint: 'IP address as seen by the server.',
    http_accept_language: 'Accept-Language',
    http_accept_language_hint: 'Preferred language codes declared by the browser.',
    http_user_agent: 'User-Agent',
    http_user_agent_hint: 'Extracted from the HTTP User-Agent.',
    http_accept_encoding: 'Accept-Encoding',
    http_accept_encoding_hint: 'Compression methods supported by the browser.',
    encoding_zstd_hint: 'zstd support is often enabled in Firefox and can help corroborate the browser.',
    http_vpn: 'VPN / Proxy',
    vpn_hint: 'Heuristic based on forwarding headers.',
    vpn_unknown: 'Unknown',
    vpn_detected: 'Detected',
    vpn_not_detected: 'Not detected',
    vpn_possible: 'Possible',
    vpn_headers_label: 'Detected headers',
    vpn_headers_none: 'None',
    http_tor: 'Tor',
    tor_hint: 'Tor exit via DNS + explicit User-Agent token.',
    tor_unknown: 'Unknown',
    tor_detected: 'Detected',
    tor_not_detected: 'Not detected',
    tor_possible: 'Possible',
    tor_ua_hint: 'User-Agent resembles Tor Browser (heuristic).',
    tor_dns_label: 'DNS check',
    tor_dns_success: 'success',
    tor_dns_failed: 'failed',
    ua_browser_label: 'Browser',
    ua_os_label: 'OS',
    value_unavailable: 'Unavailable',
    geo_button: 'Show geolocation',
    geo_country_label: 'Country',
    geo_org_label: 'Organization',
    geo_net_label: 'Network',
    geo_loading: 'Request in progress...',
    geo_error: 'Unable to fetch geolocation data.',
    js_section: 'From JavaScript (browser side)',
    js_section_summary: 'Show browser details (JSON)',
    collecting_placeholder: '(collecting...)',
    js_languages: 'Languages (JS)',
    js_language_match_label: 'HTTP match',
    js_language_match_yes: 'Matches',
    js_language_match_no: 'Different',
    js_language_match_unknown: 'Unknown',
    js_gpu: 'GPU (WebGL)',
    js_cpu: 'Logical CPU',
    js_cpu_hint: 'navigator.hardwareConcurrency',
    js_screen: 'Screen',
    js_screen_size_label: 'Size',
    js_screen_dpr_label: 'DPR',
    js_screen_color_label: 'Color depth',
    js_screen_touch_label: 'Touch points',
    js_screen_dpr_help: 'DPR = ratio of physical to CSS pixels.'
  }
};

// Cache latest JS signals so labels can re-render on language switch.
let lastJsInfo = null;

// Detect preferred UI language (stored choice wins, else browser locale).
function detectLang() {
  const stored = localStorage.getItem('ui_lang');
  if (stored && i18n[stored]) return stored;
  const nav = (navigator.language || 'en').toLowerCase();
  return nav.startsWith('fr') ? 'fr' : 'en';
}

// Apply translations to elements marked with data-i18n.
function applyTranslations(lang) {
  const dict = i18n[lang] || i18n.en;
  document.querySelectorAll('[data-i18n]').forEach((el) => {
    const key = el.getAttribute('data-i18n');
    if (Object.prototype.hasOwnProperty.call(dict, key)) {
      el.textContent = dict[key];
    }
  });
  document.documentElement.lang = lang;
  localStorage.setItem('ui_lang', lang);
  const select = document.getElementById('lang');
  if (select && select.value !== lang) select.value = lang;

  const jsPre = document.getElementById('js');
  if (jsPre && !jsPre.dataset.collected) {
    jsPre.textContent = dict.collecting_placeholder;
  }

  updateDetectionLabels(dict);
  updateLanguageMatchLabel(dict);
  if (lastJsInfo) renderJsSummary(lastJsInfo, dict);
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

// Collect immediately on page load.
async function runCollection() {
  const data = await collectAll();
  const jsPre = document.getElementById('js');
  if (!jsPre) return;
  jsPre.dataset.collected = 'true';
  jsPre.textContent = JSON.stringify(data, null, 2);
  lastJsInfo = data;
  renderJsSummary(data);
}

// Initialize UI language from browser or stored choice.
document.getElementById('lang').addEventListener('change', (e) => {
  applyTranslations(e.target.value);
});
applyTranslations(detectLang());
runCollection();

// GeoIP helpers (triggered by button click).
function setGeoStatus(message) {
  const status = document.getElementById('geo-status');
  if (status) status.textContent = message || '';
}

function showGeoDetails() {
  document.getElementById('geo-card')?.classList.remove('hidden');
  document.getElementById('geo-details')?.classList.remove('hidden');
  const map = document.getElementById('geo-map');
  if (map) map.classList.remove('hidden');
}

function setText(id, value, fallback) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = value || fallback || '';
}

function updateDetectionLabels(dict) {
  const vpn = document.getElementById('vpn-status');
  const tor = document.getElementById('tor-status');
  if (vpn) {
    const state = vpn.dataset.status || 'unknown';
    const key = state === 'yes' ? 'vpn_detected' : state === 'no' ? 'vpn_not_detected' : state === 'maybe' ? 'vpn_possible' : 'vpn_unknown';
    vpn.textContent = dict[key];
    vpn.classList.remove('status-yes', 'status-no', 'status-unknown', 'status-maybe');
    vpn.classList.add(`status-${state}`);
  }
  if (tor) {
    const state = tor.dataset.status || 'unknown';
    const key = state === 'yes' ? 'tor_detected' : state === 'no' ? 'tor_not_detected' : state === 'maybe' ? 'tor_possible' : 'tor_unknown';
    tor.textContent = dict[key];
    tor.classList.remove('status-yes', 'status-no', 'status-unknown', 'status-maybe');
    tor.classList.add(`status-${state}`);
  }
}

// Parse Accept-Language and keep ordered language tags.
function parseAcceptLanguageHeader(header) {
  if (!header) return [];
  return header
    .split(',')
    .map((part) => part.split(';')[0].trim())
    .filter((token, idx, arr) => token && arr.indexOf(token) === idx);
}

// Compare JS vs HTTP language order for a simple match/mismatch signal.
function compareLanguageLists(jsList, httpList) {
  if (!jsList.length || !httpList.length) return 'unknown';
  const len = Math.min(jsList.length, httpList.length);
  for (let i = 0; i < len; i += 1) {
    if (jsList[i].toLowerCase() !== httpList[i].toLowerCase()) return 'no';
  }
  return 'yes';
}

function updateLanguageMatchLabel(dict) {
  const el = document.getElementById('js-lang-match');
  if (!el) return;
  const state = el.dataset.status || 'unknown';
  const key = state === 'yes' ? 'js_language_match_yes' : state === 'no' ? 'js_language_match_no' : 'js_language_match_unknown';
  el.textContent = dict[key];
  el.classList.remove('status-yes', 'status-no', 'status-unknown');
  el.classList.add(`status-${state}`);
}

// Render the JS summary cards from collected client signals.
function renderJsSummary(info, dictOverride) {
  const dict = dictOverride || i18n[detectLang()] || i18n.en;
  const langsEl = document.getElementById('js-lang-list');
  const matchEl = document.getElementById('js-lang-match');
  const gpuEl = document.getElementById('js-gpu');
  const gpuMetaEl = document.getElementById('js-gpu-meta');
  const cpuEl = document.getElementById('js-cpu');
  const screenEl = document.getElementById('js-screen');
  if (!langsEl || !matchEl || !gpuEl || !gpuMetaEl || !cpuEl || !screenEl) return;

  const jsLangs = info?.navigator?.languages || [];
  const httpLangs = parseAcceptLanguageHeader(document.body?.dataset?.httpAcceptLanguage);

  langsEl.textContent = '';
  jsLangs.forEach((lang) => {
    const pill = document.createElement('span');
    pill.className = 'pill';
    pill.textContent = lang;
    langsEl.appendChild(pill);
  });

  matchEl.dataset.status = compareLanguageLists(jsLangs, httpLangs);
  updateLanguageMatchLabel(dict);

  const gpu = info?.webgl;
  if (gpu?.available) {
    gpuEl.textContent = gpu.unmaskedRenderer || gpu.renderer || 'WebGL';
    const vendor = gpu.unmaskedVendor || '';
    gpuMetaEl.textContent = vendor ? vendor : '';
  } else {
    gpuEl.textContent = dict.value_unavailable || 'Unavailable';
    gpuMetaEl.textContent = '';
  }

  cpuEl.textContent = info?.navigator?.hardwareConcurrency ?? (dict.value_unavailable || 'Unavailable');

  const s = info?.screen || {};
  const dpr = s.devicePixelRatio ?? window.devicePixelRatio ?? 1;
  const mtp = info?.navigator?.maxTouchPoints ?? null;
  const colorDepth = s.colorDepth ?? null;
  const size = (s.width && s.height) ? `${s.width}×${s.height}` : null;
  const dprValue = Math.round(dpr * 100) / 100;

  screenEl.textContent = '';
  const addMetric = (label, value, hint) => {
    const line = document.createElement('div');
    line.className = 'metric-line';

    const labelEl = document.createElement('span');
    labelEl.className = 'metric-label';
    labelEl.textContent = label;
    line.appendChild(labelEl);

    if (hint) {
      const hintEl = document.createElement('span');
      hintEl.className = 'hint-icon';
      hintEl.textContent = '?';
      hintEl.title = hint;
      hintEl.setAttribute('aria-label', hint);
      line.appendChild(hintEl);
    }

    const sep = document.createElement('span');
    sep.textContent = ':';
    line.appendChild(sep);

    const valueEl = document.createElement('span');
    valueEl.className = 'metric-value';
    valueEl.textContent = value;
    line.appendChild(valueEl);

    screenEl.appendChild(line);
  };

  if (size) addMetric(dict.js_screen_size_label, `${size}px`);
  addMetric(dict.js_screen_dpr_label, String(dprValue), dict.js_screen_dpr_help);
  if (colorDepth != null) addMetric(dict.js_screen_color_label, String(colorDepth));
  if (mtp != null) addMetric(dict.js_screen_touch_label, String(mtp));
}

// Build an OpenStreetMap embed URL centered on the IP location.
function buildOsmUrl(lat, lon) {
  const delta = 0.001;
  const left = lon - delta;
  const right = lon + delta;
  const top = lat + delta;
  const bottom = lat - delta;
  const bbox = [left, bottom, right, top].join('%2C');
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&marker=${lat}%2C${lon}&layer=mapnik`;
}

document.getElementById('geo-run')?.addEventListener('click', async () => {
  const dict = i18n[detectLang()] || i18n.en;
  setGeoStatus(dict.geo_loading);

  try {
    const res = await fetch('https://ipapi.co/json/');
    if (!res.ok) throw new Error('geo request failed');
    const data = await res.json();

    setText('geo-country', data.country_name, dict.value_unavailable);
    setText('geo-org', data.org, dict.value_unavailable);
    setText('geo-net', data.network, dict.value_unavailable);

    if (data.latitude != null && data.longitude != null) {
      const url = buildOsmUrl(Number(data.latitude), Number(data.longitude));
      let map = document.getElementById('geo-map');
      if (!map) {
        const card = document.getElementById('geo-card');
        if (card) {
          map = document.createElement('div');
          map.id = 'geo-map';
          map.className = 'geo-map hidden';
          card.appendChild(map);
        }
      }
      if (map && !map.querySelector('iframe')) {
        const frame = document.createElement('iframe');
        frame.id = 'geo-map-frame';
        frame.title = 'OpenStreetMap';
        frame.loading = 'lazy';
        frame.src = url;
        map.appendChild(frame);
      } else if (map) {
        const frame = map.querySelector('iframe');
        if (frame) frame.src = url;
      }
    }

    showGeoDetails();
    setGeoStatus('');
  } catch (err) {
    setGeoStatus(dict.geo_error);
  }
});
