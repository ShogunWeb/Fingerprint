// I18N lives in src/i18n.js as window.I18N.
const i18n = window.I18N || {};

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
  const titleKey = lang === 'fr' ? 'titleFr' : 'titleEn';
  const htmlEl = document.documentElement;
  if (htmlEl) {
    const title = htmlEl.dataset[titleKey];
    if (title) document.title = title;
  }
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

function updateLanguageMatchLabel(dict) {
  const el = document.getElementById('js-lang-match');
  if (!el) return;
  const state = el.dataset.status || 'unknown';
  const key = state === 'yes' ? 'js_language_match_yes' : state === 'no' ? 'js_language_match_no' : 'js_language_match_unknown';
  el.textContent = dict[key];
  el.classList.remove('status-yes', 'status-no', 'status-unknown');
  el.classList.add(`status-${state}`);
}

// Infer a device class based on CSS size and DPR against reference presets.
function inferDeviceClass(size, dpr) {
  if (!size || !window.DEVICE_PRESETS) return null;
  const [w, h] = size.split('×').map((v) => Number(v));
  if (!w || !h) return null;
  const sw = Math.min(w, h);
  const sh = Math.max(w, h);
  const tolerance = 20;

  let best = null;
  let bestScore = Infinity;
  window.DEVICE_PRESETS.forEach((preset) => {
    const pw = Math.min(preset.width, preset.height);
    const ph = Math.max(preset.width, preset.height);
    const sizeDelta = Math.abs(sw - pw) + Math.abs(sh - ph);
    const dprMatch = dpr >= preset.dprMin && dpr <= preset.dprMax;
    if (sizeDelta <= tolerance && dprMatch) {
      if (sizeDelta < bestScore) {
        best = preset;
        bestScore = sizeDelta;
      }
    }
  });

  return best;
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
  const deviceEl = document.getElementById('js-device');
  if (!langsEl || !matchEl || !gpuEl || !gpuMetaEl || !cpuEl || !screenEl) return;

  const jsLangs = info?.navigator?.languages || [];
  const httpLangs = window.appUtils?.parseAcceptLanguageHeader(document.body?.dataset?.httpAcceptLanguage) || [];

  langsEl.textContent = '';
  jsLangs.forEach((lang) => {
    const pill = document.createElement('span');
    pill.className = 'pill';
    pill.textContent = lang;
    langsEl.appendChild(pill);
  });

  const match = window.appUtils?.compareLanguageLists(jsLangs, httpLangs) || 'unknown';
  matchEl.dataset.status = match;
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

  if (deviceEl) {
    const device = window.appUtils?.inferDeviceClass(size, dprValue);
    const label = device ? `${device.group} · ${device.name}` : dict.js_device_unknown;
    deviceEl.textContent = `${dict.js_device_hint}: ${label}`;
  }
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
      const url = window.appUtils?.buildOsmUrl(Number(data.latitude), Number(data.longitude));
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
      if (map && !map.querySelector('iframe') && url) {
        const frame = document.createElement('iframe');
        frame.id = 'geo-map-frame';
        frame.title = 'OpenStreetMap';
        frame.loading = 'lazy';
        frame.src = url;
        map.appendChild(frame);
      } else if (map && url) {
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
