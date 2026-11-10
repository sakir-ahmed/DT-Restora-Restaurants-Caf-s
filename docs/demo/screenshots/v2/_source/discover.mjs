// Lists the visible buttons and links of each admin page so the capture script can target them.
import { chromium } from '../../../../node_modules/@playwright/test/index.mjs';
import { writeFileSync } from 'node:fs';

const BASE = process.env.SHOTS_URL || 'http://127.0.0.1:8197';
const browser = await chromium.launch({ channel: 'chrome' });
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await page.goto(BASE + '/login?lang=bn');
await page.locator('input[type="text"]').first().fill('owner');
await page.locator('input[type="password"]').first().fill('Password123');
await page.keyboard.press('Enter');
await page.waitForURL(/\/admin/, { timeout: 30000 });

const paths = ['/admin', '/admin/pos', '/admin/orders', '/admin/orders/incoming', '/admin/kds', '/admin/menu', '/admin/media', '/admin/tables', '/admin/recipes', '/admin/ingredients', '/admin/suppliers', '/admin/purchases', '/admin/stock-counts', '/admin/stock-transfers', '/admin/wastage', '/admin/customers', '/admin/loyalty', '/admin/coupons', '/admin/delivery', '/admin/aggregators', '/admin/staff', '/admin/attendance', '/admin/payroll', '/admin/expenses', '/admin/accounting', '/admin/reports', '/admin/branches', '/admin/blog', '/admin/careers', '/admin/letters', '/admin/activity-log', '/admin/settings', '/admin/sync-conflicts'];
const out = {};
for (const p of paths) {
  await page.goto(BASE + p);
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(800);
  out[p] = await page.evaluate((path) => ({
    title: document.title,
    buttons: [...document.querySelectorAll('main button, main a')].map((e) => (e.getAttribute('aria-label') || e.textContent || '').trim().replace(/\s+/g, ' ')).filter((t) => t && t.length < 60).slice(0, 60),
    nav: path === '/admin' ? [...document.querySelectorAll('aside a')].map((e) => e.textContent.trim() + ' -> ' + e.getAttribute('href')) : undefined,
  }), p);
}
writeFileSync(new URL('./discover.json', import.meta.url), JSON.stringify(out, null, 1));
await browser.close();
console.log('discover done');
