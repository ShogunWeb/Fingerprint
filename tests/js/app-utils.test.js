import { describe, it, expect } from 'vitest';
import appUtils from '../../src/app-utils.js';

const {
  parseAcceptLanguageHeader,
  compareLanguageLists,
  buildOsmUrl,
  inferDeviceClass
} = appUtils;

describe('app-utils', () => {
  it('parses Accept-Language and de-dupes', () => {
    const out = parseAcceptLanguageHeader('fr-FR, fr;q=0.9, fr-FR;q=0.8');
    expect(out).toEqual(['fr-FR', 'fr']);
  });

  it('compares language lists for match', () => {
    expect(compareLanguageLists(['fr', 'en'], ['fr', 'en'])).toBe('yes');
    expect(compareLanguageLists(['fr'], ['en'])).toBe('no');
    expect(compareLanguageLists([], ['fr'])).toBe('unknown');
  });

  it('builds an OSM embed URL', () => {
    const url = buildOsmUrl(48.8566, 2.3522);
    expect(url).toContain('openstreetmap.org/export/embed.html');
    expect(url).toContain('marker=48.8566%2C2.3522');
  });

  it('infers device class from size and DPR', () => {
    const presets = [
      { group: 'Phones', name: 'Small phone', width: 360, height: 640, dprMin: 2, dprMax: 3 },
      { group: 'Tablets', name: 'Small tablet', width: 768, height: 1024, dprMin: 2, dprMax: 2 }
    ];
    const device = inferDeviceClass('360×640', 2, presets);
    expect(device?.name).toBe('Small phone');
  });
});
