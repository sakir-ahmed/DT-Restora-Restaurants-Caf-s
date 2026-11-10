import { chromium } from '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/node_modules/@playwright/test/index.mjs';
import { readFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
const base = '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/docs/marketing/';
const man = JSON.parse(readFileSync(base + 'social-media-posts/_build/fbpage.json', 'utf8'));
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 2000, height: 1200 }, deviceScaleFactor: 1 });
await p.goto('file://' + base + 'social-media-posts/_build/fbpage.html');
await p.evaluate(() => document.fonts.ready);
await p.waitForTimeout(1200);
for (const m of man) {
  mkdirSync(dirname(base + m.out), { recursive: true });
  await p.locator('#' + m.id).screenshot({ path: base + m.out });
  console.log('ok', m.out);
}
await b.close();
