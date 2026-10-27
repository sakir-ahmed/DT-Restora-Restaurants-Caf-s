// node docs/marketing/book/v2/render.mjs [png|pdf|all] [pageRegex]
import { chromium } from '../../../../node_modules/@playwright/test/index.mjs';
import { mkdirSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const mode = process.argv[2] || 'png';
const only = process.argv[3] ? new Set(process.argv[3].split(',').map(Number)) : null;
const file = path.join(HERE, 'dt-restora-product-book-bn.html');
const browser = await chromium.launch({ channel: 'chrome' });
const page = await (await browser.newContext({ viewport: { width: 900, height: 1200 }, deviceScaleFactor: mode === 'pdf' ? 1 : 1.5 })).newPage();
await page.goto(pathToFileURL(file).href);
await page.waitForLoadState('networkidle');
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(600);
if (mode === 'png' || mode === 'all') {
  mkdirSync(path.join(HERE, '_tmp'), { recursive: true });
  const pages = page.locator('section.pg');
  const n = await pages.count();
  for (let i = 0; i < n; i++) {
    if (only && !only.has(i + 1)) continue;
    await pages.nth(i).screenshot({ path: path.join(HERE, '_tmp', `p${String(i + 1).padStart(2, '0')}.png`) });
  }
  console.log('png done', n);
}
if (mode === 'pdf' || mode === 'all') {
  await page.pdf({ path: path.join(HERE, '..', 'DT-Restora-Product-Book-BN-v2.pdf'), width: '794px', height: '1123px', printBackground: true, preferCSSPageSize: true });
  console.log('pdf done');
}
await browser.close();
