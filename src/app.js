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
