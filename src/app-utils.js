// Shared JS helpers for parsing and inference.
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.appUtils = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
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

  // Infer a device class based on CSS size and DPR against reference presets.
  function inferDeviceClass(size, dpr, presets) {
    const list = presets || (typeof window !== 'undefined' ? window.DEVICE_PRESETS : null);
    if (!size || !list) return null;
    const parts = size.split('×').map((v) => Number(v));
    if (parts.length !== 2 || !parts[0] || !parts[1]) return null;
    const sw = Math.min(parts[0], parts[1]);
    const sh = Math.max(parts[0], parts[1]);
    const tolerance = 20;

    let best = null;
    let bestScore = Infinity;
    list.forEach((preset) => {
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

  return {
    parseAcceptLanguageHeader,
    compareLanguageLists,
    buildOsmUrl,
    inferDeviceClass
  };
}));
