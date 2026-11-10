import { chromium } from '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/node_modules/@playwright/test/index.mjs';
import { readFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
const root = '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/docs/marketing/social-media-posts/';
const manifest = JSON.parse(readFileSync(root + '_build/manifest.json', 'utf8'));
const only = process.env.ONLY ? new RegExp(process.env.ONLY) : null;
const byFile = {};
for (const m of manifest) { if (only && !only.test(m.out)) continue; (byFile[m.file] ??= []).push(m); }
const b = await chromium.launch();
let n = 0;
for (const [file, items] of Object.entries(byFile)) {
  const p = await b.newPage({ viewport: { width: 1200, height: 1500 }, deviceScaleFactor: 1 });
  await p.goto('file://' + root + '_build/' + file);
  await p.evaluate(() => document.fonts.ready);
  await p.waitForTimeout(1200);
  for (const m of items) {
    mkdirSync(dirname(root + m.out), { recursive: true });
    const el = p.locator('#' + m.id);
    await el.screenshot({ path: root + m.out });
    const o = await el.evaluate((e) => { const kids = [...e.children]; const last = kids[kids.length - 1].getBoundingClientRect(); const r = e.getBoundingClientRect(); return { over: Math.round(last.bottom - r.bottom), sh: e.scrollHeight - e.clientHeight }; });
    if (o.over > 0 || o.sh > 6) console.log('OVERFLOW', m.out, JSON.stringify(o));
    n++;
  }
  await p.close();
  console.log('done', file, items.length);
}
await b.close();
console.log('rendered', n);
