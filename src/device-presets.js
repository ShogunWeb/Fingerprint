// Reference device classes for screen-size/DPR inference.
// Values are CSS pixels in portrait orientation unless noted.
window.DEVICE_PRESETS = [
  { group: 'Phones', name: 'Small phone', width: 360, height: 640, dprMin: 2, dprMax: 3 },
  { group: 'Phones', name: 'iPhone SE / mini class', width: 375, height: 667, dprMin: 2, dprMax: 2 },
  { group: 'Phones', name: 'Modern iPhone', width: 390, height: 844, dprMin: 3, dprMax: 3 },
  { group: 'Phones', name: 'Large iPhone Pro Max', width: 430, height: 932, dprMin: 3, dprMax: 3 },
  { group: 'Phones', name: 'Android compact', width: 360, height: 800, dprMin: 2, dprMax: 3 },
  { group: 'Phones', name: 'Android large', width: 412, height: 915, dprMin: 3, dprMax: 3 },

  { group: 'Tablets', name: 'Small tablet', width: 768, height: 1024, dprMin: 2, dprMax: 2 },
  { group: 'Tablets', name: 'iPad 10–11″', width: 820, height: 1180, dprMin: 2, dprMax: 2 },
  { group: 'Tablets', name: 'iPad Pro 12.9″', width: 1024, height: 1366, dprMin: 2, dprMax: 2 },
  { group: 'Tablets', name: 'Android tablet', width: 800, height: 1280, dprMin: 2, dprMax: 2 },

  { group: 'Mac laptops', name: 'Older MacBook', width: 1280, height: 800, dprMin: 2, dprMax: 2 },
  { group: 'Mac laptops', name: 'MacBook Air', width: 1440, height: 900, dprMin: 2, dprMax: 2 },
  { group: 'Mac laptops', name: 'MacBook Pro 14″', width: 1512, height: 982, dprMin: 2, dprMax: 2 },
  { group: 'Mac laptops', name: 'MacBook Pro 16″', width: 1728, height: 1117, dprMin: 2, dprMax: 2 },
  { group: 'Mac laptops', name: '“More space” mode', width: 1680, height: 1050, dprMin: 2, dprMax: 2 },

  { group: 'Windows laptops', name: '1366 × 768 @ 100%', width: 1366, height: 768, dprMin: 1, dprMax: 1 },
  { group: 'Windows laptops', name: '1536 × 864 @ 125%', width: 1536, height: 864, dprMin: 1, dprMax: 1 },
  { group: 'Windows laptops', name: '1920 × 1080 @ 100%', width: 1920, height: 1080, dprMin: 1, dprMax: 1 },
  { group: 'Windows laptops', name: '2560 × 1440 @ 150%', width: 2560, height: 1440, dprMin: 1, dprMax: 1 },

  { group: 'Desktops', name: '1080p monitor', width: 1920, height: 1080, dprMin: 1, dprMax: 1 },
  { group: 'Desktops', name: '1440p monitor', width: 2560, height: 1440, dprMin: 1, dprMax: 1 },
  { group: 'Desktops', name: '4K @ 100%', width: 3840, height: 2160, dprMin: 1, dprMax: 1 },
  { group: 'Desktops', name: '4K @ 150%', width: 2560, height: 1440, dprMin: 1, dprMax: 1 },
  { group: 'Desktops', name: 'macOS scaled 4K', width: 3008, height: 1692, dprMin: 2, dprMax: 2 },

  { group: 'iMac', name: 'iMac 24″', width: 2240, height: 1260, dprMin: 2, dprMax: 2 }
];
