// Captures the product screenshots used by the posts and reels (docs/marketing/assets/shots/*.jpg).
// Needs the app running on SHOTS_URL (default http://127.0.0.1:8098) with a freshly seeded database and an admin login:
//   SHOTS_LOGIN=owner@dtrestora.test SHOTS_PASSWORD=... node docs/marketing/capture-shots.mjs [only-regex]
import { chromium } from '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/node_modules/@playwright/test/index.mjs';
import { mkdirSync } from 'node:fs';
import { execSync } from 'node:child_process';

const BASE = process.env.SHOTS_URL || 'http://127.0.0.1:8098';
const OUT = '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/docs/marketing/assets/shots/';
const only = process.argv[2] ? new RegExp(process.argv[2]) : null;
mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1600, height: 880 }, deviceScaleFactor: 1, locale: 'en-US' });
const page = await ctx.newPage();
page.setDefaultTimeout(30000);
const settle = async (ms = 900) => { await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(ms); };
const theme = async (mode) => { await page.evaluate((m) => { localStorage.setItem('theme', m); document.documentElement.classList.toggle('dark', m === 'dark'); }, mode); await page.waitForTimeout(300); };
const snap = async (name, o = {}) => {
  if (only && !only.test(name)) return;
  await page.screenshot({ path: OUT + name + '.jpg', type: 'jpeg', quality: 90, clip: o.clip });
  console.log('shot', name);
};
const go = async (path, o = {}) => { await page.goto(BASE + path); await settle(o.wait); };

// ---- demo letters for the Letters screen (the seed has none)
const sql = (q) => execSync(`sqlite3 ${process.env.SHOTS_DB} "${q}"`);
if (Number(sql('select count(*) from letters').toString().trim()) === 0) {
  sql(`insert into letters (branch_id, author_id, title, recipient, subject, body, letter_date, created_at, updated_at) values (1,1,'Appointment letter','Mizanur Rahman','Appointment as Kitchen Manager','We are pleased to offer you the position of Kitchen Manager.','2026-10-18',datetime('now'),datetime('now')),(1,1,'Experience certificate','Karim Uddin','Experience certificate','This is to certify that Karim Uddin worked with us as Head Chef.','2026-10-15',datetime('now'),datetime('now')),(1,1,'Salary increment','Farhana Akter','Salary increment from November','Your monthly salary is revised with effect from 1 November 2026.','2026-10-12',datetime('now'),datetime('now'))`);
}
const attendanceDay = sql('select max(work_date) from staff_attendances').toString().trim().slice(0, 10);

// ---- sign in
await page.goto(BASE + '/login');
await page.locator('input[type="text"]').first().fill(process.env.SHOTS_LOGIN);
await page.locator('input[type="password"]').first().fill(process.env.SHOTS_PASSWORD);
await page.keyboard.press('Enter');
await page.waitForURL(/\/admin/, { timeout: 30000 });
await settle(1500);
await theme('light');

// ---- admin pages (light)
const admin = {
  dash: '/admin?period=month', accounting: '/admin/accounting', activity: '/admin/activity-log', aggr: '/admin/aggregators', attendance: '/admin/attendance?date=' + attendanceDay,
  blogadmin: '/admin/blog', branches: '/admin/branches', careers: '/admin/careers', coupons: '/admin/coupons', customers: '/admin/customers',
  delivery: '/admin/delivery', dishmedia: '/admin/media', expenses: '/admin/expenses', ingredients: '/admin/ingredients', kds: '/admin/kds',
  letters: '/admin/letters', loyalty: '/admin/loyalty', menu: '/admin/menu', orders: '/admin/orders', payroll: '/admin/payroll',
  purchases: '/admin/purchases', recipes: '/admin/recipes', reports: '/admin/reports', settings: '/admin/settings', staff: '/admin/staff',
  stockcount: '/admin/stock-counts', sync: '/admin/sync-conflicts', tables: '/admin/tables', transfers: '/admin/stock-transfers', wastage: '/admin/wastage',
};
for (const [name, path] of Object.entries(admin)) { if (only && !only.test(name)) continue; await go(path, { wait: 1400 }); await snap(name); }
if (!only || only.test('dash-bottom')) { await go('/admin?period=month', { wait: 1400 }); await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight)); await page.waitForTimeout(600); await snap('dash-bottom'); }
if (!only || only.test('dash-crop')) { await go('/admin?period=month', { wait: 1400 }); await page.screenshot({ path: OUT + 'dash-crop.jpg', type: 'jpeg', quality: 90, clip: { x: 0, y: 0, width: 1600, height: 665 } }); console.log('shot dash-crop'); }

// ---- dark
await theme('dark');
await go('/admin?period=month', { wait: 1400 }); await theme('dark'); await snap('dash-dark');

// ---- POS
await theme('light');
await go('/admin/pos', { wait: 1600 });
await snap('pos-empty');
await page.evaluate(() => [...document.querySelectorAll('button')].find((b) => /open shift/i.test(b.textContent))?.click());
await page.waitForTimeout(700);
const shiftModal = page.locator('div.fixed.inset-0.z-50');
if (await shiftModal.count()) {
  await shiftModal.locator('input').first().fill('5000').catch(() => {});
  await shiftModal.getByRole('button', { name: /open shift/i }).click().catch(() => {});
  await settle(1200);
}
const tiles = page.locator('main button:has(img), main [role="button"]:has(img)');
const n = await tiles.count();
for (const i of [0, 1, 4, 7, 9]) {
  if (i >= n) continue;
  await tiles.nth(i).click().catch(() => {});
  await page.waitForTimeout(500);
  const dialog = page.locator('[role="dialog"]');
  if (await dialog.isVisible().catch(() => false)) {
    await dialog.getByRole('button', { name: /increase/i }).first().click().catch(() => {});
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
  }
}
await snap('pos-cart');
const split = page.getByRole('radio', { name: /split/i }).first();
if (await split.count()) { await split.click().catch(() => {}); await page.waitForTimeout(500); }
await snap('pos-split');
await theme('dark'); await snap('pos-dark'); await theme('light');
const takeaway = page.getByRole('radio', { name: /takeaway/i }).first();
if (await takeaway.count()) { await takeaway.click().catch(() => {}); await page.waitForTimeout(300); }
const sendKitchen = page.getByRole('button', { name: /send to kitchen/i }).first();
if (await sendKitchen.count()) { await sendKitchen.click().catch(() => {}); await settle(1000); }
const cash = page.getByRole('radio', { name: /cash/i }).first();
if (await cash.count()) { await cash.click().catch(() => {}); await page.waitForTimeout(300); }
const exact = page.getByRole('button', { name: /^exact$/i }).first();
if (await exact.count()) { await exact.click().catch(() => {}); await page.waitForTimeout(300); }
const confirm = page.getByRole('button', { name: /confirm payment/i }).first();
if (await confirm.count()) { await confirm.click().catch(() => {}); await settle(1500); }
await snap('pos-paid');

// ---- public website (signed out)
await ctx.clearCookies();
await go('/', { wait: 1500 }); await snap('w-home');
await go('/menu', { wait: 1500 }); await snap('w-menu');
const dish = page.locator('a[href*="/items/"]').first();
if (await dish.count()) { await dish.click(); await settle(1500); await snap('w-dish'); }
await go('/gallery', { wait: 1500 }); await snap('w-gallery');
await go('/blog', { wait: 1200 }); await snap('w-blog');
await go('/locations', { wait: 1200 }); await snap('w-locations');
await go('/careers', { wait: 1200 }); await snap('w-careers');
await go('/menu', { wait: 1200 });
const d2 = page.locator('a[href*="/items/"]').first();
if (await d2.count()) { await d2.click(); await settle(1200); const addBtn = page.getByRole('button', { name: /add to cart/i }).first(); if (await addBtn.count()) { await addBtn.click().catch(() => {}); await page.waitForTimeout(600); } }
await go('/checkout', { wait: 1400 }); await snap('w-checkout');
const tokens = execSync(`sqlite3 ${process.env.SHOTS_DB} "select tracking_token from orders where channel='website' and tracking_token is not null order by id desc limit 6"`).toString().trim().split('\n').filter(Boolean);
const good = [];
for (const tk of tokens) { const r = await page.goto(BASE + '/track/' + tk); if (r && r.ok()) good.push(tk); if (good.length === 2) break; }
if (good[0]) { await go('/track/' + good[0], { wait: 1500 }); await snap('w-track'); }
if (good[0]) { await go('/track/' + (good[1] || good[0]), { wait: 1500 }); await snap('w-placed'); }

// ---- mobile (390 x 794 at 2x)
const mctx = await browser.newContext({ viewport: { width: 390, height: 794 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
const mp = await mctx.newPage();
const msnap = async (name) => { if (only && !only.test(name)) return; await mp.screenshot({ path: OUT + name + '.jpg', type: 'jpeg', quality: 90 }); console.log('shot', name); };
const mgo = async (path) => { await mp.goto(BASE + path); await mp.waitForLoadState('networkidle').catch(() => {}); await mp.waitForTimeout(1200); };
await mgo('/'); await msnap('m-home');
await mgo('/menu'); await msnap('m-menu');
const mdish = mp.locator('a[href*="/items/"]').first();
if (await mdish.count()) { await mdish.click(); await mp.waitForTimeout(1500); await msnap('m-dish'); }
await mgo('/menu');
const md2 = mp.locator('a[href*="/items/"]').first();
if (await md2.count()) { await md2.click(); await mp.waitForTimeout(1500); const mb = mp.getByRole('button', { name: /add to cart/i }).first(); if (await mb.count()) { await mb.click().catch(() => {}); await mp.waitForTimeout(600); } }
await mgo('/checkout'); await msnap('m-checkout');

await browser.close();
console.log('capture done');
