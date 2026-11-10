// Captures the Bangla marketing screenshots of the whole product into docs/screenshots/v2/raw.
// Needs the app running on SHOTS_URL (default http://127.0.0.1:8197) with IS_DEMO=false and a freshly seeded database (SHOTS_DB).
//   node docs/screenshots/v2/_source/capture.mjs [only-regex]
import { chromium } from '../../../../node_modules/@playwright/test/index.mjs';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../../../..');
const OUT = path.join(HERE, '../raw');
const BASE = process.env.SHOTS_URL || 'http://127.0.0.1:8197';
const DB = process.env.SHOTS_DB || path.join(ROOT, 'database/v2shots.sqlite');
const only = process.argv[2] ? new RegExp(process.argv[2]) : null;
const sql = (q) => execSync(`sqlite3 "${DB}" "${q}"`).toString().trim();

const MODULES = {
  '01-website': 'ওয়েবসাইট', '02-online-order': 'অনলাইন অর্ডার', '03-mobile': 'মোবাইল অ্যাপের মতো ওয়েবসাইট', '04-dashboard': 'ড্যাশবোর্ড',
  '05-pos': 'পিওএস', '06-orders': 'অর্ডার ম্যানেজমেন্ট', '07-kitchen': 'কিচেন ডিসপ্লে', '08-menu': 'মেনু ও ছবি', '09-tables': 'টেবিল ও কিউআর',
  '10-inventory': 'ইনভেন্টরি ও রেসিপি', '11-customers': 'গ্রাহক, লয়্যালটি ও কুপন', '12-delivery': 'ডেলিভারি ও অ্যাগ্রিগেটর', '13-staff': 'স্টাফ, হাজিরা ও বেতন',
  '14-accounts': 'খরচ, হিসাব ও রিপোর্ট', '15-system': 'শাখা, ব্লগ ও সেটিংস', '16-dark-mode': 'ডার্ক মোড',
};

if (!only) { rmSync(OUT, { recursive: true, force: true }); }
mkdirSync(OUT, { recursive: true });
const catalog = [];
const counters = {};
const failures = [];

const browser = await chromium.launch({ channel: 'chrome' });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, locale: 'bn-BD', timezoneId: 'Asia/Dhaka' });
const page = await ctx.newPage();
page.setDefaultTimeout(15000);

const settle = async (ms = 900) => { await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(ms); };
const go = async (p, ms) => { await page.goto(BASE + p); await settle(ms); };
const theme = async (mode) => { await page.evaluate((m) => { localStorage.setItem('theme', m); document.documentElement.classList.toggle('dark', m === 'dark'); }, mode); await page.waitForTimeout(350); };

async function shot(module, slug, caption, o = {}) {
  const key = `${module}/${slug}`;
  if (only && !only.test(key)) return;
  counters[module] = (counters[module] || 0) + 1;
  const nn = String(counters[module]).padStart(2, '0');
  const file = `${module}/${nn}-${slug}.png`;
  mkdirSync(path.join(OUT, module), { recursive: true });
  await (o.page || page).screenshot({ path: path.join(OUT, file), fullPage: !!o.full });
  catalog.push({ file, module, moduleTitle: MODULES[module], caption, device: o.device || 'desktop' });
  console.log('shot', file);
}
async function step(name, fn, always = false) {
  if (only && !always && !only.test(name)) return;
  try { await fn(); } catch (e) { failures.push(`${name}: ${String(e.message).split('\n')[0]}`); console.log('FAIL', name, String(e.message).split('\n')[0]); }
}
const control = (name, scope = page) => scope.getByRole('button', { name }).or(scope.getByRole('link', { name })).first();
const dialogOpen = () => page.locator('[role="dialog"], div.fixed.inset-0').first().isVisible().catch(() => false);

/** Puts sample values into the empty fields of the open dialog so the form looks in use. */
async function autofill() {
  await page.evaluate(() => {
    const root = document.querySelector('[role="dialog"]') || [...document.querySelectorAll('div.fixed')].pop();
    if (!root) return;
    const fire = (el) => { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); };
    const setter = (el, v) => { const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype; Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, v); fire(el); };
    root.querySelectorAll('input, textarea').forEach((el) => {
      if (el.value && el.value !== '0' && el.value !== '0.00') return;
      if (el.type === 'number') setter(el, '250');
      else if (el.type === 'tel') setter(el, '01712345678');
      else if (el.type === 'text' || el.tagName === 'TEXTAREA' || el.type === 'search') setter(el, el.tagName === 'TEXTAREA' ? 'গ্রাহকের সুবিধার জন্য সংক্ষিপ্ত বিবরণ' : 'স্পেশাল গ্রিলড ফিশ');
    });
    root.querySelectorAll('select').forEach((el) => { if (!el.value && el.options.length > 1) { el.selectedIndex = 1; fire(el); } });
  });
}

/** Opens a page, clicks a button, captures the dialog it opens, then leaves it by reloading. */
async function dialogShot(module, slug, caption, p, btn, o = {}) {
  if (only && !only.test(`${module}/${slug}`)) return;
  await step(`${module}/${slug}`, async () => {
    await go(p, 900);
    await control(btn).click();
    await page.waitForTimeout(o.wait ?? 700);
    if (o.fill) { await o.fill(); } else if (!/edit/.test(slug)) { await autofill(); }
    await page.waitForTimeout(400);
    await shot(module, slug, caption);
  });
}

// ---------- public website, signed out, Bangla
await go('/?lang=bn', 1800);
await theme('light');
await step('website', async () => {
  await go('/', 1800); await shot('01-website', 'home', 'রেস্টুরেন্টের নিজস্ব ওয়েবসাইট, যেখানে অর্ডার আসে সরাসরি');
  await page.evaluate(() => window.scrollTo(0, 900)); await page.waitForTimeout(700); await shot('01-website', 'home-popular', 'জনপ্রিয় আইটেম ও অফার একনজরে');
  await page.evaluate(() => window.scrollTo(0, 1800)); await page.waitForTimeout(700); await shot('01-website', 'home-more', 'শাখা, রিভিউ ও যোগাযোগ');
  await go('/menu', 1600); await shot('01-website', 'menu', 'ছবিসহ পূর্ণাঙ্গ মেনু, ক্যাটাগরি ধরে সাজানো');
  const dish = page.locator('a[href*="/items/"]').first();
  await dish.click(); await settle(1500); await shot('01-website', 'dish', 'আইটেমের বিস্তারিত, সাইজ ও অ্যাড-অন বেছে নেওয়া');
  await go('/about', 1200); await shot('01-website', 'about', 'আমাদের কথা');
  await go('/locations', 1200); await shot('01-website', 'locations', 'সব শাখার ঠিকানা ও সময়সূচি');
  const loc = page.locator('a[href*="/locations/"]').first();
  if (await loc.count()) { await loc.click(); await settle(1300); await shot('01-website', 'location-detail', 'প্রতিটি শাখার আলাদা পেজ'); }
  await go('/gallery', 1500); await shot('01-website', 'gallery', 'ছবির গ্যালারি');
  await go('/blog', 1200); await shot('01-website', 'blog', 'ব্লগ, যা সার্চ ইঞ্জিনে ট্রাফিক আনে');
  const post = page.locator('a[href*="/blog/"]').first();
  if (await post.count()) { await post.click(); await settle(1200); await shot('01-website', 'blog-post', 'ব্লগ পোস্টের পেজ'); }
  await go('/careers', 1200); await shot('01-website', 'careers', 'ক্যারিয়ার পেজে চাকরির বিজ্ঞপ্তি');
  const job = page.locator('a[href*="/careers/"]').first();
  if (await job.count()) { await job.click(); await settle(1200); await shot('01-website', 'career-detail', 'অনলাইনে আবেদন ও সিভি জমা'); }
  await go('/contact', 1200); await shot('01-website', 'contact', 'যোগাযোগের ফর্ম');
  await go('/faq', 1200); await shot('01-website', 'faq', 'সচরাচর জিজ্ঞাসা');
  await go('/customer/login', 1200); await shot('01-website', 'customer-login', 'গ্রাহকের লগইন, মোবাইল নম্বরে ওটিপি');
  await go('/privacy', 1000); await shot('01-website', 'privacy', 'গোপনীয়তা নীতি');
  await go('/terms', 1000); await shot('01-website', 'terms', 'ব্যবহারের শর্ত');
});

// ---------- online ordering flow
await step('ordering', async () => {
  await go('/menu', 1400);
  await page.locator('a[href*="/items/"]').nth(1).click(); await settle(1300);
  await step('add-to-cart', async () => {
    await control(/ঝুড়িতে যোগ/).click(); await page.waitForTimeout(800);
    await shot('02-online-order', 'cart-added', 'এক ক্লিকে কার্টে যোগ');
  });
  await go('/menu', 1200);
  await page.locator('a[href*="/items/"]').nth(3).click(); await settle(1200);
  await control(/ঝুড়িতে যোগ/).click().catch(() => {}); await page.waitForTimeout(700);
  await go('/checkout', 1500); await shot('02-online-order', 'checkout', 'চেকআউট, ডেলিভারি বা পিকআপ বেছে নিন');
  await shot('02-online-order', 'checkout-full', 'পুরো চেকআউট পেজ', { full: true });
});
await step('qr-order', async () => {
  const table = sql("select code from branches where code='BR01'") && sql('select id from restaurant_tables limit 1');
  await go(`/q/BR01/${table}`, 1500); await shot('02-online-order', 'qr-table-menu', 'টেবিলের কিউআর স্ক্যান করেই অর্ডার');
});
await step('track', async () => {
  const tokens = sql("select tracking_token from orders where channel='website' and tracking_token is not null order by id desc limit 6").split('\n').filter(Boolean);
  for (const tk of tokens) {
    const r = await page.goto(BASE + '/track/' + tk);
    if (r && r.ok()) { await settle(1500); await shot('02-online-order', 'track', 'লাইভ অর্ডার ট্র্যাকিং, রান্না থেকে ডেলিভারি পর্যন্ত'); break; }
  }
});

// ---------- mobile website
const mctx = await browser.newContext({ viewport: { width: 390, height: 800 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, locale: 'bn-BD' });
const mp = await mctx.newPage();
await mp.goto(BASE + '/?lang=bn'); await mp.waitForLoadState('networkidle').catch(() => {});
const mgo = async (p) => { await mp.goto(BASE + p); await mp.waitForLoadState('networkidle').catch(() => {}); await mp.waitForTimeout(1200); };
await step('mobile', async () => {
  await mgo('/'); await shot('03-mobile', 'home', 'ফোনে ওয়েবসাইট, ঠিক অ্যাপের মতো', { page: mp, device: 'phone' });
  await mgo('/menu'); await shot('03-mobile', 'menu', 'ফোনে মেনু', { page: mp, device: 'phone' });
  await mp.locator('a[href*="/items/"]').first().click(); await mp.waitForTimeout(1500); await shot('03-mobile', 'dish', 'ফোনে আইটেমের পেজ', { page: mp, device: 'phone' });
  await mp.getByRole('button', { name: /ঝুড়িতে যোগ/ }).first().click().catch(() => {}); await mp.waitForTimeout(700);
  await mgo('/checkout'); await shot('03-mobile', 'checkout', 'ফোনে চেকআউট', { page: mp, device: 'phone' });
});
await mctx.close();

// ---------- staff sign in
await step('login', async () => {
  await ctx.clearCookies();
  await go('/login?lang=bn', 1500); await shot('04-dashboard', 'login', 'স্টাফ লগইন, শুধু অনুমোদিত ব্যবহারকারীর জন্য');
  await page.locator('input[type="text"]').first().fill('owner');
  await page.locator('input[type="password"]').first().fill('Password123');
  await page.keyboard.press('Enter');
  await page.waitForURL(/\/admin/, { timeout: 30000 });
  await settle(1500);
  await theme('light');
}, true);

// ---------- dashboard
await step('dashboard', async () => {
  await go('/admin?period=month', 2000); await shot('04-dashboard', 'owner-dashboard', 'মালিকের ড্যাশবোর্ড, বিক্রি ও লাভ একনজরে');
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight)); await page.waitForTimeout(700); await shot('04-dashboard', 'dashboard-more', 'সেরা আইটেম, শাখাভিত্তিক বিক্রি ও সাম্প্রতিক অর্ডার');
  await go('/admin?period=month', 1500); await shot('04-dashboard', 'dashboard-full', 'ড্যাশবোর্ডের পুরো পাতা', { full: true });
  await go('/admin?period=today', 1500); await shot('04-dashboard', 'dashboard-today', 'আজকের বিক্রি');
});

// ---------- POS
await step('pos', async () => {
  await go('/admin/pos', 1800); await shot('05-pos', 'locked', 'শিফট না খুললে পিওএস চালু হয় না');
  await page.getByRole('button', { name: 'শিফট খুলুন' }).first().click(); await page.waitForTimeout(700);
  await shot('05-pos', 'open-shift', 'শুরুর নগদ লিখে শিফট খুলুন');
  const dlg = page.locator('[role="dialog"], div.fixed.inset-0').first();
  await dlg.locator('input').first().fill('5000').catch(() => {});
  await dlg.getByRole('button', { name: 'শিফট খুলুন' }).last().click(); await settle(1500);
  await shot('05-pos', 'ready', 'পিওএস প্রস্তুত, ক্যাটাগরি ও আইটেম এক স্ক্রিনে');
});
const dlg = () => page.locator('div.fixed.inset-0').last();
const addDish = async (name, o = {}) => {
  await page.locator('main button').filter({ hasText: name }).first().click();
  await page.waitForTimeout(600);
  const d = dlg();
  if (!(await d.isVisible().catch(() => false))) { return; }
  if (o.addon) { await d.getByText(o.addon).first().click().catch(() => {}); await page.waitForTimeout(300); }
  if (o.more) { await d.getByRole('button', { name: 'বাড়ান' }).first().click().catch(() => {}); await page.waitForTimeout(300); }
  if (o.shot) await shot('05-pos', o.shot, o.caption);
  const submit = d.locator('button').last();
  await submit.click({ timeout: 2500 }).catch(async () => {
    await d.getByRole('button', { name: 'বাড়ান' }).first().click();
    await page.waitForTimeout(300);
    await submit.click({ timeout: 4000 });
  });
  await page.waitForTimeout(500);
  if (await d.isVisible().catch(() => false)) { await page.keyboard.press('Escape'); await page.waitForTimeout(300); }
};
await step('pos-add', async () => {
  await page.getByRole('button', { name: 'Kacchi & Biryani' }).first().click().catch(() => {}); await page.waitForTimeout(600);
  await shot('05-pos', 'category', 'ক্যাটাগরি ধরে দ্রুত আইটেম খোঁজা');
  await addDish('কাচ্চি বিরিয়ানি', { addon: 'বোরহানি', more: true, shot: 'size-addon', caption: 'সাইজ, অ্যাড-অন ও পরিমাণ বেছে আইটেম যোগ' });
  await addDish('চিকেন বিরিয়ানি');
  await page.getByRole('button', { name: 'সব আইটেম' }).first().click().catch(() => {}); await page.waitForTimeout(500);
  await addDish('ডিম ফ্রাইড রাইস'); await addDish('বিফ ভুনা');
  await addDish('সাদা ভাত', { shot: 'multi-size', caption: 'একই আইটেমের একাধিক সাইজ একসঙ্গে যোগ' });
  await page.waitForTimeout(500);
  await shot('05-pos', 'items-added', 'আইটেম বেছে নিলেই কার্টে, সঙ্গে সঙ্গে হিসাব');
});
await step('pos-table', async () => {
  await page.getByRole('button', { name: 'টেবিল বাছাই করুন' }).first().click(); await page.waitForTimeout(700);
  await shot('05-pos', 'select-table', 'ডাইন-ইনের জন্য টেবিল বাছাই, খালি ও দখল করা টেবিল আলাদা');
  await page.locator('[role="dialog"]').getByRole('button').filter({ hasText: /[A-Z]\d+/ }).first().click();
  await page.waitForTimeout(500);
  await page.getByPlaceholder('01XXXXXXXXX').first().fill('01712345678').catch(() => {});
  await page.waitForTimeout(500);
  await shot('05-pos', 'table-customer', 'টেবিল ও গ্রাহকের মোবাইল যোগ, ডাইন-ইন বা টেকওয়ে');
});
await step('pos-discount', async () => {
  const input = page.getByRole('spinbutton').first();
  await input.fill('50'); await page.waitForTimeout(600);
  await shot('05-pos', 'discount', 'বিল ছাড় দেওয়া যায় সীমার মধ্যে, বেশি হলে ম্যানেজার পিন');
  await input.fill('0'); await page.waitForTimeout(300);
});
await step('pos-send', async () => {
  await page.getByRole('button', { name: 'কিচেনে পাঠান' }).first().click(); await settle(1200);
  await shot('05-pos', 'sent-to-kitchen', 'কিচেনে অর্ডার চলে গেল, প্রিন্ট ছাড়াই');
});
await step('pos-pay', async () => {
  await page.getByText('বিকাশ', { exact: true }).first().click().catch(() => {}); await page.waitForTimeout(500);
  await shot('05-pos', 'pay-methods', 'নগদ, বিকাশ, কার্ড বা ভাগ করে পরিশোধ');
  await page.getByText('নগদ', { exact: true }).first().click().catch(() => {}); await page.waitForTimeout(400);
  await page.getByRole('button', { name: 'ঠিক' }).first().click().catch(() => {}); await page.waitForTimeout(400);
  await shot('05-pos', 'pay-cash', 'নগদ গ্রহণ, ফেরত দেওয়ার হিসাব নিজে থেকে');
  await page.getByRole('button', { name: 'পরিশোধ নিশ্চিত করুন' }).first().click(); await settle(1500);
  await shot('05-pos', 'paid', 'পরিশোধ সম্পন্ন, রসিদ প্রস্তুত');
});
await step('pos-running', async () => {
  await go('/admin/pos', 1500);
  await page.getByRole('button', { name: /চলমান/ }).first().click().catch(() => {}); await page.waitForTimeout(700);
  await shot('05-pos', 'running-orders', 'চলমান টেবিল ও অর্ডার');
});

// ---------- orders
await step('orders', async () => {
  await go('/admin/orders', 1500); await shot('06-orders', 'list', 'সব চ্যানেলের অর্ডার এক তালিকায়');
  await control('অপেক্ষমাণ').click().catch(() => {}); await settle(900); await shot('06-orders', 'filter-pending', 'অবস্থা অনুযায়ী ছাঁকনি');
  await go('/admin/orders/incoming', 1500); await shot('06-orders', 'incoming', 'ওয়েবসাইট ও অ্যাগ্রিগেটরের নতুন অর্ডার');
  const id = sql("select id from orders where status='paid' order by id desc limit 1");
  await go(`/admin/orders/${id}/receipt`, 1500); await shot('06-orders', 'receipt', 'থার্মাল প্রিন্টারের জন্য রসিদ');
});
await dialogShot('06-orders', 'cancel-order', 'অর্ডার বাতিলে কারণ ও ম্যানেজার অনুমোদন', '/admin/orders', 'অর্ডার বাতিল করুন');
await dialogShot('06-orders', 'take-payment', 'বকেয়া অর্ডারের পরিশোধ নেওয়া', '/admin/orders', 'পরিশোধ');

// ---------- kitchen
await step('kds', async () => {
  await go('/admin/kds', 1800); await shot('07-kitchen', 'board', 'কিচেন ডিসপ্লে, নতুন থেকে পরিবেশিত পর্যন্ত চার ধাপ');
  await control('তৈরি শুরু করুন').click().catch(() => {}); await settle(900); await shot('07-kitchen', 'start-cooking', 'এক ট্যাপে তৈরি শুরু');
  await control('প্রস্তুত করুন').click().catch(() => {}); await settle(900); await shot('07-kitchen', 'mark-ready', 'প্রস্তুত হলে ওয়েটারের কাছে সংকেত');
  await theme('dark'); await settle(500); await shot('07-kitchen', 'dark', 'রান্নাঘরের জন্য ডার্ক মোড'); await theme('light');
});

// ---------- menu and media
await step('menu', async () => { await go('/admin/menu', 1500); await shot('08-menu', 'list', 'ক্যাটাগরি ও আইটেম, ছবি ও দামসহ'); });
await dialogShot('08-menu', 'add-category', 'নতুন ক্যাটাগরি যোগ', '/admin/menu', 'ক্যাটাগরি যোগ করুন');
await dialogShot('08-menu', 'add-item', 'নতুন আইটেম, দাম, ভ্যাট ও সাইজসহ', '/admin/menu', 'আইটেম যোগ করুন');
await dialogShot('08-menu', 'edit-item', 'আইটেম সম্পাদনা', '/admin/menu', 'সম্পাদনা');
await step('media', async () => { await go('/admin/media', 1800); await shot('08-menu', 'media', 'ছবি ও ভিডিও ব্যবস্থাপনা, ওয়েবসাইটে কোনটি দেখাবে ঠিক করুন'); });

// ---------- tables
await step('tables', async () => { await go('/admin/tables', 1500); await shot('09-tables', 'list', 'টেবিল, এলাকা ও আসন সংখ্যা'); });
await dialogShot('09-tables', 'add-table', 'নতুন টেবিল যোগ, কিউআর কোডসহ', '/admin/tables', 'টেবিল যোগ করুন');

// ---------- inventory
await step('inv-list', async () => {
  await go('/admin/ingredients', 1500); await shot('10-inventory', 'ingredients', 'উপকরণ, স্টক ও গড় ব্যয়');
  await go('/admin/recipes', 1500); await shot('10-inventory', 'recipes', 'প্রতিটি আইটেমের রেসিপি');
  await control('রেসিপি সম্পাদনা').click(); await settle(1400); await shot('10-inventory', 'recipe-edit', 'রেসিপি সম্পাদনা, উপকরণ ও পরিমাণ');
  const id = sql('select id from menu_items order by id limit 1');
  await go(`/admin/recipes/${id}/cost`, 1500); await shot('10-inventory', 'recipe-cost', 'রেসিপির খরচ ও লাভের হিসাব');
  await go('/admin/suppliers', 1300); await shot('10-inventory', 'suppliers', 'সরবরাহকারীর তালিকা');
  await go('/admin/purchases', 1300); await shot('10-inventory', 'purchases', 'ক্রয়ের হিসাব, স্টক নিজে থেকে বাড়ে');
  await go('/admin/stock-counts', 1300); await shot('10-inventory', 'stock-counts', 'স্টক গণনা ও পার্থক্য সমন্বয়');
  await go('/admin/stock-transfers', 1300); await shot('10-inventory', 'transfers', 'শাখা থেকে শাখায় স্টক স্থানান্তর');
  await go('/admin/wastage', 1300); await shot('10-inventory', 'wastage', 'অপচয়ের হিসাব ও কারণ');
});
await dialogShot('10-inventory', 'add-ingredient', 'নতুন উপকরণ যোগ', '/admin/ingredients', 'উপকরণ যোগ করুন');
await dialogShot('10-inventory', 'add-supplier', 'নতুন সরবরাহকারী যোগ', '/admin/suppliers', 'সরবরাহকারী যোগ করুন');
await dialogShot('10-inventory', 'new-purchase', 'নতুন ক্রয়ের এন্ট্রি', '/admin/purchases', 'নতুন ক্রয়');
await dialogShot('10-inventory', 'new-transfer', 'নতুন স্টক ট্রান্সফার', '/admin/stock-transfers', 'নতুন ট্রান্সফার');
await dialogShot('10-inventory', 'add-wastage', 'অপচয় লিখে রাখা', '/admin/wastage', 'অপচয় লিখুন');

// ---------- customers, loyalty, coupons
await step('customers', async () => {
  await go('/admin/customers', 1500); await shot('11-customers', 'customers', 'গ্রাহকের তালিকা ও কেনাকাটার ইতিহাস');
  await go('/admin/loyalty', 1500); await shot('11-customers', 'loyalty', 'লয়্যালটি পয়েন্ট ও নিয়ম');
  await go('/admin/coupons', 1500); await shot('11-customers', 'coupons', 'কুপন ও ডিসকাউন্ট কোড');
});
await dialogShot('11-customers', 'add-coupon', 'নতুন কুপন তৈরি', '/admin/coupons', 'কুপন যোগ করুন');

// ---------- delivery
await step('delivery', async () => {
  await go('/admin/delivery', 1500); await shot('12-delivery', 'board', 'ডেলিভারি, রাইডার নির্ধারণ থেকে সম্পন্ন পর্যন্ত');
  await go('/admin/aggregators', 1500); await shot('12-delivery', 'aggregators', 'ফুডপান্ডা ও অন্যান্য অ্যাগ্রিগেটরের অর্ডার এক জায়গায়');
});
await dialogShot('12-delivery', 'manual-aggregator-order', 'অ্যাগ্রিগেটরের অর্ডার হাতে লেখা', '/admin/aggregators', 'ম্যানুয়াল অর্ডার');
await dialogShot('12-delivery', 'aggregator-payout', 'অ্যাগ্রিগেটরের পেআউট', '/admin/aggregators', 'পেআউট');

// ---------- staff
await step('staff', async () => {
  await go('/admin/staff', 1500); await shot('13-staff', 'staff', 'কর্মচারী ও লগইনের ভূমিকা');
  await go('/admin/attendance', 1500); await shot('13-staff', 'attendance', 'দৈনিক হাজিরা ও কাজের ঘণ্টা');
  await go('/admin/payroll', 1500); await shot('13-staff', 'payroll', 'মাসিক বেতন ও অগ্রিম');
  await control('বেতন স্লিপ').click(); await settle(1500); await shot('13-staff', 'payslip', 'বেতন স্লিপ');
  await go('/admin/letters', 1300); await shot('13-staff', 'letters', 'চিঠি, নিয়োগপত্র থেকে অভিজ্ঞতার সনদ');
  await go('/admin/careers', 1300); await shot('13-staff', 'careers', 'চাকরির বিজ্ঞপ্তি ও আবেদনকারী');
});
await dialogShot('13-staff', 'add-employee', 'নতুন কর্মচারী যোগ', '/admin/staff', 'কর্মচারী যোগ করুন');
await dialogShot('13-staff', 'add-login', 'কর্মীর জন্য লগইন তৈরি', '/admin/staff', 'লগইন যোগ করুন');
await dialogShot('13-staff', 'edit-attendance', 'হাজিরা সম্পাদনা', '/admin/attendance', 'সম্পাদনা');
await dialogShot('13-staff', 'add-advance', 'বেতনের অগ্রিম লেখা', '/admin/payroll', 'অগ্রিম লিখুন');
await dialogShot('13-staff', 'new-letter', 'নতুন চিঠি লেখা', '/admin/letters', 'নতুন চিঠি');
await dialogShot('13-staff', 'new-opening', 'নতুন চাকরির বিজ্ঞপ্তি', '/admin/careers', 'নতুন পদ');
await step('applications', async () => {
  await go('/admin/careers', 1200); await page.getByRole('tab', { name: /আবেদন/ }).first().click(); await settle(1000); await shot('13-staff', 'applications', 'আবেদনকারীর তালিকা ও সিভি');
});

// ---------- accounts and reports
await step('accounts', async () => {
  await go('/admin/expenses', 1500); await shot('14-accounts', 'expenses', 'খরচের হিসাব');
  await go('/admin/accounting', 1800); await shot('14-accounts', 'accounting', 'অ্যাকাউন্টিং, জার্নাল ও হিসাবখাতা');
  for (const [tab, slug, cap] of [['ট্রায়াল ব্যালান্স', 'trial-balance', 'ট্রায়াল ব্যালান্স'], ['খতিয়ান', 'ledger', 'খতিয়ান'], ['নগদ প্রবাহ', 'cash-flow', 'নগদ প্রবাহ'], ['দেনা বয়স', 'payables-aging', 'দেনার বয়সভিত্তিক হিসাব'], ['ভাউচার', 'vouchers', 'ভাউচার']]) {
    await step('acc-' + slug, async () => { await control(tab).click(); await settle(1000); await shot('14-accounts', slug, cap); });
  }
  await go('/admin/reports', 1800); await shot('14-accounts', 'reports', 'রিপোর্ট, PDF, Excel ও CSV এ ডাউনলোড');
});
await dialogShot('14-accounts', 'add-expense', 'নতুন খরচ যোগ', '/admin/expenses', 'খরচ যোগ করুন');
await dialogShot('14-accounts', 'manual-journal', 'ম্যানুয়াল জার্নাল এন্ট্রি', '/admin/accounting', 'ম্যানুয়াল জার্নাল');

// ---------- system
await step('system', async () => {
  await go('/admin/branches', 1400); await shot('15-system', 'branches', 'একাধিক শাখা, প্রতিটির আলাদা ভ্যাট ও চার্জ');
  await go('/admin/blog', 1400); await shot('15-system', 'blog', 'ব্লগ ব্যবস্থাপনা');
  await go('/admin/settings', 1500); await shot('15-system', 'settings', 'সেটিংস');
  await shot('15-system', 'settings-full', 'পুরো সেটিংস পাতা', { full: true });
  await go('/admin/sync-conflicts', 1300); await shot('15-system', 'sync-conflicts', 'অফলাইনে কাজ, ইন্টারনেট এলে সিঙ্ক');
  await go('/admin/activity-log', 1400); await shot('15-system', 'activity-log', 'কার্যক্রম লগ, কে কী করল');
});
await dialogShot('15-system', 'add-branch', 'নতুন শাখা যোগ', '/admin/branches', 'শাখা যোগ করুন');
await dialogShot('15-system', 'new-post', 'নতুন ব্লগ লেখা', '/admin/blog', 'নতুন লেখা');

// ---------- dark mode
await step('dark', async () => {
  await go('/admin?period=month', 1800); await theme('dark'); await shot('16-dark-mode', 'dashboard', 'ডার্ক মোডে ড্যাশবোর্ড');
  await go('/admin/pos', 1600); await theme('dark'); await shot('16-dark-mode', 'pos', 'ডার্ক মোডে পিওএস');
  await go('/admin/orders', 1500); await theme('dark'); await shot('16-dark-mode', 'orders', 'ডার্ক মোডে অর্ডার');
  await go('/', 1500); await theme('dark'); await shot('16-dark-mode', 'website', 'ডার্ক মোডে ওয়েবসাইট');
  await theme('light');
});

// ---------- rider
await step('rider', async () => {
  const rctx = await browser.newContext({ viewport: { width: 390, height: 800 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, locale: 'bn-BD' });
  const rp = await rctx.newPage();
  await rp.goto(BASE + '/login?lang=bn');
  await rp.locator('input[type="text"]').first().fill('rider');
  await rp.locator('input[type="password"]').first().fill('Password123');
  await rp.keyboard.press('Enter');
  await rp.waitForURL(/\/(rider|admin)/, { timeout: 30000 });
  await rp.waitForLoadState('networkidle').catch(() => {}); await rp.waitForTimeout(1500);
  await shot('12-delivery', 'rider-app', 'রাইডারের মোবাইল পেজ, ডেলিভারি ও ক্যাশ অন ডেলিভারি', { page: rp, device: 'phone' });
  await rctx.close();
});

writeFileSync(path.join(OUT, 'catalog.json'), JSON.stringify({ modules: MODULES, shots: catalog }, null, 1));
writeFileSync(path.join(OUT, 'failures.txt'), failures.join('\n'));
await browser.close();
console.log(`capture done: ${catalog.length} shots, ${failures.length} failures`);
