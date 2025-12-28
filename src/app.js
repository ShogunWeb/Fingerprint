// UI copy for FR/EN and status messages.
const i18n = {
  fr: {
    title: 'Informations visibles sur vous',
    lang_label: 'Langue',
    note: 'Cette page affiche ce que le serveur reçoit via HTTP + ce que le navigateur expose via JavaScript. Rien n\u2019est \u201cpirate\u201d : ce sont des signaux standards. Certains champs peuvent etre masques par des protections (VPN, anti-fingerprint, etc.).',
    server_section: 'Depuis la connexion HTTP (cote serveur)',
    server_section_summary: 'Afficher les details HTTP (JSON)',
    http_ip: 'IP',
    http_ip_hint: 'Adresse IP vue par le serveur.',
    http_accept_language: 'Accept-Language',
    http_accept_language_hint: 'Codes de langue preferes declares par le navigateur.',
    http_user_agent: 'User-Agent',
    http_user_agent_hint: 'Extrait du User-Agent HTTP.',
    http_accept_encoding: 'Accept-Encoding',
    http_accept_encoding_hint: 'Methodes de compression supportees par le navigateur.',
    encoding_zstd_hint: 'Le support de zstd est souvent actif sur Firefox, utile pour corroborer le navigateur.',
    ua_browser_label: 'Navigateur',
    ua_os_label: 'OS',
    value_unavailable: 'Indisponible',
    geo_button: 'Afficher la geolocalisation',
    geo_country_label: 'Pays',
    geo_org_label: 'Organisation',
    geo_net_label: 'Reseau',
    geo_loading: 'Requete en cours...',
    geo_error: 'Impossible de recuperer les donnees de geolocalisation.',
    js_section: 'Depuis JavaScript (cote navigateur)',
    js_section_summary: 'Afficher les details navigateur (JSON)',
    collecting_placeholder: '(collecte en cours...)'
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
    collecting_placeholder: '(collecting...)'
  }
};

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
    if (dict[key]) el.textContent = dict[key];
  });
  document.documentElement.lang = lang;
  localStorage.setItem('ui_lang', lang);
  const select = document.getElementById('lang');
  if (select && select.value !== lang) select.value = lang;

  const jsPre = document.getElementById('js');
  if (jsPre && !jsPre.dataset.collected) {
    jsPre.textContent = dict.collecting_placeholder;
  }
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
