// Captures the product screenshots used by the reels into ../_library/screenshots/*.
// Needs the app running (default http://localhost:8000) and a staff login:
//   SHOTS_LOGIN=... SHOTS_PASSWORD=... node marketing-assets/november-2026/reel/_tools/capture.mjs [only-regex]
import { chromium } from '../../../../web-app/node_modules/playwright/index.mjs';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const OUT = join(here, '..', '_library', 'screenshots');
const BASE = process.env.SHOTS_URL || 'http://localhost:8000';
const only = process.argv[2] ? new RegExp(process.argv[2]) : null;

for (const d of ['admin-desktop', 'admin-desktop-bangla', 'settings', 'invoice-print', 'website-desktop', 'website-mobile', 'admin-mobile']) {
    mkdirSync(join(OUT, d), { recursive: true });
}

const browser = await chromium.launch({ channel: process.env.SHOTS_CHANNEL || undefined });
const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'en-US' });
const page = await desktop.newPage();
page.setDefaultTimeout(30000);

// The demo banner, the demo guide button and the SMS demo card are not part of the product, so they are hidden in the shots.
const HIDE = '.demo-banner{display:none!important} [style*="--demo-fab"]{display:none!important} [data-sonner-toaster]{display:none!important} :root{--demo-banner-h:0px!important}';
const settle = async (ms = 1200) => { await page.waitForLoadState('networkidle').catch(() => {}); await page.addStyleTag({ content: HIDE }).catch(() => {}); await page.waitForTimeout(ms); };
const snap = async (dir, name) => {
    if (only && !only.test(`${dir}/${name}`)) return;
    await page.screenshot({ path: join(OUT, dir, `${name}.png`) });
    console.log('shot', `${dir}/${name}`);
};
const go = async (path, wait) => { await page.goto(BASE + path); await settle(wait); };
const light = async () => { await page.evaluate(() => { localStorage.setItem('theme', 'light'); document.documentElement.classList.remove('dark'); }); };
const click = async (text) => { await page.getByRole('button', { name: text, exact: false }).first().click().catch(() => page.getByText(text).first().click()); await page.waitForTimeout(700); };

// ---- sign in (staff)
await page.goto(BASE + '/login');
await page.locator('input[name="login"]').fill(process.env.SHOTS_LOGIN);
await page.locator('input[name="password"]').fill(process.env.SHOTS_PASSWORD);
await page.keyboard.press('Enter');
await page.waitForURL(/\/admin/, { timeout: 30000 });
await settle(1500);
await light();

// ---- admin pages, English
const admin = {
    '01-dashboard': '/admin?period=month', '02-pos': '/admin/pos', '03-orders': '/admin/orders', '04-dues': '/admin/dues',
    '05-kitchen-display': '/admin/kds', '06-reservations': '/admin/reservations', '07-tables-qr': '/admin/tables', '08-menu': '/admin/menu',
    '09-photos-videos': '/admin/media', '10-ingredients': '/admin/ingredients', '11-suppliers': '/admin/suppliers', '12-recipes': '/admin/recipes',
    '13-purchases': '/admin/purchases', '14-wastage': '/admin/wastage', '15-stock-count': '/admin/stock-counts', '16-stock-transfers': '/admin/stock-transfers',
    '17-customers': '/admin/customers', '18-loyalty': '/admin/loyalty', '19-sms-history': '/admin/sms', '20-coupons': '/admin/coupons',
    '21-staff': '/admin/staff', '22-attendance': '/admin/attendance', '23-letters': '/admin/letters', '24-delivery': '/admin/delivery',
    '25-aggregators': '/admin/aggregators', '26-accounting': '/admin/accounting', '27-payroll': '/admin/payroll', '28-reports': '/admin/reports',
    '29-expenses': '/admin/expenses', '30-branches': '/admin/branches', '31-activity-log': '/admin/activity-log', '32-sync-conflicts': '/admin/sync-conflicts',
};
for (const [name, path] of Object.entries(admin)) {
    if (only && !only.test(`admin-desktop/${name}`)) continue;
    await go(path);
    await snap('admin-desktop', name);
}

// POS with a few items in the ticket
if (!only || only.test('admin-desktop/02-pos-cart')) {
    await go('/admin/pos');
    for (const item of ['French Fry', 'Chicken Fry (1 Pc)', 'Crispy Wings']) {
        await page.getByText(item, { exact: true }).first().click().catch(() => {});
        await page.waitForTimeout(400);
    }
    await snap('admin-desktop', '02-pos-cart');
}

// Purchase form with the unit picker
if (!only || only.test('admin-desktop/13-purchases-new')) {
    await go('/admin/purchases');
    await click('New purchase');
    const row = page.locator('tbody tr').first();
    const ingredient = row.locator('select').nth(1);
    await ingredient.selectOption({ index: 1 }).catch(() => {});
    await page.waitForTimeout(500);
    const unit = row.locator('select').nth(3);
    const unitOptions = await unit.locator('option').allTextContents().catch(() => []);
    if (unitOptions.length > 2) await unit.selectOption({ index: unitOptions.length - 1 }).catch(() => {});
    await row.locator('input').nth(1).fill('10').catch(() => {});
    await page.waitForTimeout(600);
    await snap('admin-desktop', '13-purchases-new');
}

// dashboard, dark
if (!only || only.test('admin-desktop/01-dashboard-dark')) {
    await go('/admin?period=month');
    await page.evaluate(() => { localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark'); });
    await page.waitForTimeout(500);
    await snap('admin-desktop', '01-dashboard-dark');
    await light();
}

// ---- settings tabs
const tabs = [['01-general', 'General'], ['02-payment-methods', 'Payment methods'], ['03-pos-and-billing', 'POS and billing'], ['04-invoice-print', 'Invoice print'], ['05-sms', 'SMS'], ['06-units', 'Units'], ['07-delivery-areas', 'Delivery areas']];
for (const [name, label] of tabs) {
    if (only && !only.test(`settings/${name}`)) continue;
    await go('/admin/settings');
    await page.getByRole('tab', { name: label, exact: true }).click();
    await page.waitForTimeout(1200);
    await snap('settings', name);
}

// ---- invoice print styles (sample invoice)
for (const layout of ['classic', 'compact', 'bold', 'modern', 'detailed', 'minimal']) {
    if (only && !only.test(`invoice-print/${layout}`)) continue;
    await page.goto(`${BASE}/admin/settings/invoice-preview?layout=${layout}&paper=80mm`);
    await page.waitForTimeout(700);
    await page.screenshot({ path: join(OUT, 'invoice-print', `${layout}-80mm.png`), clip: { x: 520, y: 0, width: 400, height: 900 } });
    console.log('shot', `invoice-print/${layout}-80mm`);
}
for (const [layout, paper] of [['modern', 'a5'], ['detailed', 'a4']]) {
    if (only && !only.test(`invoice-print/${layout}-${paper}`)) continue;
    await page.goto(`${BASE}/admin/settings/invoice-preview?layout=${layout}&paper=${paper}`);
    await page.waitForTimeout(700);
    await page.screenshot({ path: join(OUT, 'invoice-print', `${layout}-${paper}.png`) });
    console.log('shot', `invoice-print/${layout}-${paper}`);
}

// ---- Bangla interface
await go('/admin?period=month');
await page.getByRole('button', { name: 'বাং' }).first().click();
await page.waitForTimeout(1500);
const bn = { '01-dashboard': '/admin?period=month', '02-pos': '/admin/pos', '03-orders': '/admin/orders', '04-dues': '/admin/dues', '05-kitchen-display': '/admin/kds', '08-menu': '/admin/menu', '10-ingredients': '/admin/ingredients', '12-recipes': '/admin/recipes', '13-purchases': '/admin/purchases', '17-customers': '/admin/customers', '28-reports': '/admin/reports', '30-branches': '/admin/branches', '99-settings': '/admin/settings' };
for (const [name, path] of Object.entries(bn)) {
    if (only && !only.test(`admin-desktop-bangla/${name}`)) continue;
    await go(path);
    await snap('admin-desktop-bangla', name);
}
await go('/admin?period=month');
await page.getByRole('button', { name: 'EN' }).first().click();
await page.waitForTimeout(800);

// ---- public website (desktop)
const site = { '01-home': '/', '02-menu': '/menu', '03-locations': '/locations', '04-contact': '/contact' };
const publicCtx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'en-US' });
const pub = await publicCtx.newPage();
for (const [name, path] of Object.entries(site)) {
    if (only && !only.test(`website-desktop/${name}`)) continue;
    await pub.goto(BASE + path);
    await pub.waitForLoadState('networkidle').catch(() => {});
    await pub.addStyleTag({ content: HIDE }).catch(() => {});
    await pub.waitForTimeout(1200);
    await pub.screenshot({ path: join(OUT, 'website-desktop', `${name}.png`) });
    console.log('shot', `website-desktop/${name}`);
}

// ---- phone views (website and admin)
const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'en-US' });
const mob = await phone.newPage();
for (const [name, path] of Object.entries({ '01-home': '/', '02-menu': '/menu', '03-contact': '/contact' })) {
    if (only && !only.test(`website-mobile/${name}`)) continue;
    await mob.goto(BASE + path);
    await mob.waitForLoadState('networkidle').catch(() => {});
    await mob.addStyleTag({ content: HIDE }).catch(() => {});
    await mob.waitForTimeout(1200);
    await mob.screenshot({ path: join(OUT, 'website-mobile', `${name}.png`) });
    console.log('shot', `website-mobile/${name}`);
}
await mob.goto(BASE + '/login');
await mob.locator('input[name="login"]').fill(process.env.SHOTS_LOGIN);
await mob.locator('input[name="password"]').fill(process.env.SHOTS_PASSWORD);
await mob.keyboard.press('Enter');
await mob.waitForURL(/\/admin/, { timeout: 30000 });
for (const [name, path] of Object.entries({ '01-dashboard': '/admin?period=month', '02-pos': '/admin/pos', '03-orders': '/admin/orders', '04-menu': '/admin/menu' })) {
    if (only && !only.test(`admin-mobile/${name}`)) continue;
    await mob.goto(BASE + path);
    await mob.waitForLoadState('networkidle').catch(() => {});
    await mob.addStyleTag({ content: HIDE }).catch(() => {});
    await mob.waitForTimeout(1500);
    await mob.screenshot({ path: join(OUT, 'admin-mobile', `${name}.png`) });
    console.log('shot', `admin-mobile/${name}`);
}

await browser.close();
console.log('done');
