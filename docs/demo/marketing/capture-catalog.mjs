// Re-captures the product screenshot catalogue (docs/marketing/product-screenshots) at 1920x1080 and rewrites manifest.csv.
// Needs the app running on SHOTS_URL with a freshly seeded database. Usage:
//   SHOTS_LOGIN=... SHOTS_PASSWORD=... SHOTS_DB=/path/to/db.sqlite node docs/marketing/capture-catalog.mjs
import { chromium } from '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/node_modules/@playwright/test/index.mjs';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { execSync } from 'node:child_process';

const BASE = process.env.SHOTS_URL || 'http://127.0.0.1:8098';
const ROOT = '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/docs/marketing/product-screenshots/';
for (const d of ['01-website-light', '02-ordering-flow', '03-website-dark', '04-website-bangla', '05-mobile', '06-admin', '07-pos']) { rmSync(ROOT + d, { recursive: true, force: true }); mkdirSync(ROOT + d, { recursive: true }); }

const browser = await chromium.launch();
const rows = [['order', 'file', 'section', 'title']];
const counters = {};
let order = 0;
const save = async (pg, section, title, opts = {}) => {
  counters[section] = (counters[section] || 0) + 1;
  const file = `${section}/${String(counters[section]).padStart(3, '0')}-${title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}.png`;
  await pg.screenshot({ path: ROOT + file, ...opts });
  rows.push([++order, file, section, `"${title}"`]);
  console.log('shot', file);
};

async function newPage(vp, o = {}) {
  const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: 1, ...o });
  const p = await ctx.newPage();
  p.setDefaultTimeout(30000);
  return { ctx, p };
}
const settle = async (p, ms = 900) => { await p.waitForLoadState('networkidle').catch(() => {}); await p.waitForTimeout(ms); };
const setTheme = async (p, mode) => { await p.evaluate((m) => { localStorage.setItem('theme', m); document.documentElement.classList.toggle('dark', m === 'dark'); }, mode); await p.waitForTimeout(300); };
const parts = async (p, section, title, max) => {
  const h = await p.evaluate(() => document.documentElement.scrollHeight);
  const n = Math.min(max, Math.max(1, Math.ceil(h / 1080)));
  for (let i = 0; i < n; i++) { await p.evaluate((y) => window.scrollTo(0, y), i * 1000); await p.waitForTimeout(500); await save(p, section, `${title} part ${i + 1}`); }
  await p.evaluate(() => window.scrollTo(0, 0));
};

// ---------------- public website (light, dark, Bangla) and ordering flow
const dishUrl = async (p) => { await p.goto(BASE + '/menu'); await settle(p); return p.locator('a[href*="/items/"]').first().getAttribute('href'); };
{
  const { ctx, p } = await newPage({ width: 1920, height: 1080 });
  const site = [['home', '/', 6], ['menu', '/menu', 3], ['gallery', '/gallery', 2], ['blog', '/blog', 1], ['about', '/about', 2], ['locations', '/locations', 2], ['careers', '/careers', 1], ['contact', '/contact', 1], ['faq', '/faq', 1]];
  for (const [title, path, max] of site) { await p.goto(BASE + path); await settle(p, 1200); await setTheme(p, 'light'); await parts(p, '01-website-light', title, max); }
  const dish = await dishUrl(p);
  if (dish) { await p.goto(BASE + dish); await settle(p, 1200); await save(p, '01-website-light', 'dish page'); }
  await p.goto(BASE + '/blog'); await settle(p); const post = await p.locator('a[href^="/blog/"]').first().getAttribute('href').catch(() => null);
  if (post) { await p.goto(BASE + post); await settle(p, 1000); await save(p, '01-website-light', 'blog post'); }

  // ordering flow
  await p.goto(BASE + '/menu'); await settle(p, 1000); await save(p, '02-ordering-flow', 'choose dishes');
  if (dish) { await p.goto(BASE + dish); await settle(p); await p.getByRole('button', { name: /add to cart/i }).first().click().catch(() => {}); await p.waitForTimeout(700); }
  await save(p, '02-ordering-flow', 'dish added to cart');
  await p.goto(BASE + '/checkout'); await settle(p, 1000); await save(p, '02-ordering-flow', 'checkout');
  await p.getByRole('button', { name: /place order/i }).first().click().catch(() => {}); await p.waitForTimeout(800); await save(p, '02-ordering-flow', 'checkout validation');
  const tokens = execSync(`sqlite3 ${process.env.SHOTS_DB} "select tracking_token from orders where channel='website' and tracking_token is not null order by id desc limit 6"`).toString().trim().split('\n').filter(Boolean);
  for (const tk of tokens) { const r = await p.goto(BASE + '/track/' + tk); if (r && r.ok()) { await settle(p, 1200); await save(p, '02-ordering-flow', 'order tracking'); break; } }

  // dark
  for (const [title, path] of [['home', '/'], ['menu', '/menu'], ['gallery', '/gallery'], ['locations', '/locations'], ['about', '/about']]) { await p.goto(BASE + path); await settle(p, 1000); await setTheme(p, 'dark'); await parts(p, '03-website-dark', title, title === 'home' ? 3 : 1); }
  if (dish) { await p.goto(BASE + dish); await settle(p); await setTheme(p, 'dark'); await save(p, '03-website-dark', 'dish page'); }
  await p.goto(BASE + '/checkout'); await settle(p); await setTheme(p, 'dark'); await save(p, '03-website-dark', 'checkout');

  // Bangla
  await p.goto(BASE + '/'); await settle(p); await setTheme(p, 'light');
  await p.locator('button:has-text("বাং"), a:has-text("বাং")').first().click().catch(() => {}); await settle(p, 1500);
  for (const [title, path, max] of [['home', '/', 3], ['menu', '/menu', 2], ['about', '/about', 1], ['locations', '/locations', 1], ['contact', '/contact', 1], ['faq', '/faq', 1]]) { await p.goto(BASE + path); await settle(p, 1200); await parts(p, '04-website-bangla', title, max); }
  if (dish) { await p.goto(BASE + dish); await settle(p); await save(p, '04-website-bangla', 'dish page'); }
  await ctx.close();
}

// ---------------- mobile (390x844 at 2x = 780x1688)
{
  const { ctx, p } = await newPage({ width: 390, height: 844 }, { deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const dish = await dishUrl(p);
  for (const [title, path, max] of [['home', '/', 4], ['menu', '/menu', 3], ['gallery', '/gallery', 2], ['locations', '/locations', 1], ['about', '/about', 2], ['contact', '/contact', 1]]) {
    await p.goto(BASE + path); await settle(p, 1200);
    const h = await p.evaluate(() => document.documentElement.scrollHeight);
    const n = Math.min(max, Math.max(1, Math.ceil(h / 844)));
    for (let i = 0; i < n; i++) { await p.evaluate((y) => window.scrollTo(0, y), i * 800); await p.waitForTimeout(500); await save(p, '05-mobile', `${title} part ${i + 1}`); }
  }
  if (dish) { await p.goto(BASE + dish); await settle(p); await save(p, '05-mobile', 'dish page'); await p.getByRole('button', { name: /add to cart/i }).first().click().catch(() => {}); await p.waitForTimeout(600); }
  await p.goto(BASE + '/checkout'); await settle(p); await save(p, '05-mobile', 'checkout');
  await p.goto(BASE + '/'); await setTheme(p, 'dark'); await p.waitForTimeout(300); await save(p, '05-mobile', 'home dark');
  await ctx.close();
}

// ---------------- admin and POS (signed in)
{
  const { ctx, p } = await newPage({ width: 1920, height: 1080 });
  await p.goto(BASE + '/login');
  await p.locator('input[type="text"]').first().fill(process.env.SHOTS_LOGIN);
  await p.locator('input[type="password"]').first().fill(process.env.SHOTS_PASSWORD);
  await save(p, '06-admin', 'staff sign in');
  await p.keyboard.press('Enter');
  await p.waitForURL(/\/admin/);
  await settle(p, 1500);
  await setTheme(p, 'light');
  const pages = [['dashboard', '/admin?period=month'], ['tables', '/admin/tables'], ['orders', '/admin/orders'], ['kitchen display', '/admin/kds'], ['menu', '/admin/menu'], ['photos and videos', '/admin/media'], ['ingredients', '/admin/ingredients'], ['recipes', '/admin/recipes'], ['purchases', '/admin/purchases'], ['suppliers', '/admin/suppliers'], ['wastage', '/admin/wastage'], ['stock counts', '/admin/stock-counts'], ['transfers', '/admin/stock-transfers'], ['customers', '/admin/customers'], ['loyalty', '/admin/loyalty'], ['coupons', '/admin/coupons'], ['staff', '/admin/staff'], ['attendance', '/admin/attendance'], ['letters', '/admin/letters'], ['careers', '/admin/careers'], ['blog', '/admin/blog'], ['delivery', '/admin/delivery'], ['accounting', '/admin/accounting'], ['payroll', '/admin/payroll'], ['reports', '/admin/reports'], ['expenses', '/admin/expenses'], ['branches', '/admin/branches'], ['aggregator channels', '/admin/aggregators'], ['settings', '/admin/settings'], ['sync conflicts', '/admin/sync-conflicts'], ['activity log', '/admin/activity-log']];
  const createRx = /^\s*(new|add|create|record|log|start)\b/i;
  for (const [title, path] of pages) {
    await p.goto(BASE + path); await settle(p, 1400); await save(p, '06-admin', title);
    const btn = p.locator('main button, main a').filter({ hasText: createRx }).first();
    if (!['dashboard', 'orders', 'kitchen display', 'reports', 'settings', 'activity log', 'sync conflicts'].includes(title) && await btn.count()) {
      await btn.click().catch(() => {}); await p.waitForTimeout(900);
      if (await p.locator('div.fixed.inset-0, [role="dialog"]').count()) { await save(p, '06-admin', `${title} create form`); await p.keyboard.press('Escape'); await p.waitForTimeout(300); }
    }
  }
  await setTheme(p, 'dark');
  for (const [title, path] of [['dashboard dark', '/admin?period=month'], ['menu dark', '/admin/menu'], ['reports dark', '/admin/reports']]) { await p.goto(BASE + path); await settle(p, 1400); await setTheme(p, 'dark'); await save(p, '06-admin', title); }
  await setTheme(p, 'light');

  // POS
  await p.goto(BASE + '/admin/pos'); await settle(p, 1600);
  await save(p, '07-pos', 'pos empty');
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => /open shift/i.test(b.textContent))?.click());
  await p.waitForTimeout(700);
  const modal = p.locator('div.fixed.inset-0.z-50');
  if (await modal.count()) { await modal.locator('input').first().fill('5000').catch(() => {}); await modal.getByRole('button', { name: /open shift/i }).click().catch(() => {}); await settle(p, 1200); }
  const tiles = p.locator('main button:has(img), main [role="button"]:has(img)');
  const n = await tiles.count();
  await save(p, '07-pos', 'pos shift open');
  for (const i of [0, 1, 4, 7, 9]) {
    if (i >= n) continue;
    await tiles.nth(i).click().catch(() => {}); await p.waitForTimeout(500);
    const dlg = p.locator('[role="dialog"]');
    if (await dlg.isVisible().catch(() => false)) { if (i === 0) await save(p, '07-pos', 'pos size picker'); await dlg.getByRole('button', { name: /increase/i }).first().click().catch(() => {}); await p.keyboard.press('Escape'); await p.waitForTimeout(400); }
  }
  await save(p, '07-pos', 'pos items in cart');
  await p.getByRole('radio', { name: /split/i }).first().click().catch(() => {}); await p.waitForTimeout(500); await save(p, '07-pos', 'pos split payment');
  await p.getByRole('radio', { name: /wallet/i }).first().click().catch(() => {}); await p.waitForTimeout(500); await save(p, '07-pos', 'pos wallet payment');
  await p.getByRole('radio', { name: /takeaway/i }).first().click().catch(() => {});
  await p.getByRole('radio', { name: /cash/i }).first().click().catch(() => {}); await p.getByRole('button', { name: /^exact$/i }).first().click().catch(() => {}); await p.waitForTimeout(400); await save(p, '07-pos', 'pos cash exact');
  await p.getByRole('button', { name: /send to kitchen/i }).first().click().catch(() => {}); await settle(p, 1000); await save(p, '07-pos', 'pos sent to kitchen');
  await p.getByRole('button', { name: /confirm payment/i }).first().click().catch(() => {}); await settle(p, 1500); await save(p, '07-pos', 'pos payment confirmed');
  await p.goto(BASE + '/admin/kds'); await settle(p, 1500); await save(p, '07-pos', 'kitchen display after the order');
  await p.goto(BASE + '/admin/pos'); await settle(p, 1500); await setTheme(p, 'dark'); await save(p, '07-pos', 'pos dark');
  await ctx.close();
}

await browser.close();
writeFileSync(ROOT + 'manifest.csv', rows.map((r) => r.join(',')).join('\n') + '\n');
console.log('catalogue done:', order, 'screenshots');
