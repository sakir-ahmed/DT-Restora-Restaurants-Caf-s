// Turns every raw screenshot into a 1920 x 1080 marketing card with a Bangla caption (docs/screenshots/v2/framed).
//   node docs/screenshots/v2/_source/frame.mjs
import { chromium } from '../../../../node_modules/@playwright/test/index.mjs';
import { readFileSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const RAW = path.join(HERE, '../raw');
const OUT = path.join(HERE, '../framed');
const { shots } = JSON.parse(readFileSync(path.join(RAW, 'catalog.json'), 'utf8'));
rmSync(OUT, { recursive: true, force: true });

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
const html = (s) => {
  const src = pathToFileURL(path.join(RAW, s.file)).href;
  const phone = s.device === 'phone';
  return `<!doctype html><html><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@500;700&family=Poppins:wght@600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0}
body{width:1920px;height:1080px;overflow:hidden;font-family:'Hind Siliguri','Kohinoor Bangla','Bangla Sangam MN',sans-serif;color:#fff;
 background:radial-gradient(900px 600px at 85% 0%,rgba(56,168,255,.45),transparent 60%),radial-gradient(700px 500px at 0% 100%,rgba(11,99,206,.55),transparent 60%),linear-gradient(135deg,#0A1428 0%,#0B2450 55%,#0B63CE 140%);position:relative}
.frame{position:absolute;inset:22px;border:1.5px solid rgba(255,255,255,.14);border-radius:26px}
.c{position:absolute;width:46px;height:46px;border:4px solid #38A8FF}
.c.tl{top:22px;left:22px;border-right:0;border-bottom:0;border-radius:26px 0 0 0}.c.tr{top:22px;right:22px;border-left:0;border-bottom:0;border-radius:0 26px 0 0}
.c.bl{bottom:22px;left:22px;border-right:0;border-top:0;border-radius:0 0 0 26px}.c.br{bottom:22px;right:22px;border-left:0;border-top:0;border-radius:0 0 26px 0}
.chip{position:absolute;top:64px;left:100px;background:rgba(56,168,255,.18);border:1.5px solid rgba(56,168,255,.6);color:#BFE3FF;padding:6px 22px;border-radius:999px;font-size:26px;font-weight:500}
.cap{position:absolute;left:100px;top:${phone ? '200px' : '118px'};${phone ? 'width:820px;' : 'right:100px;'}font-size:${phone ? '66px' : '54px'};font-weight:700;line-height:1.3}
.brand{position:absolute;left:100px;bottom:62px;font-family:Poppins,sans-serif;font-weight:600;font-size:34px}
.brand b{color:#38A8FF;font-weight:600}
.brand small{display:block;font-family:'Hind Siliguri',sans-serif;font-weight:500;font-size:22px;color:#9FB6D6;margin-top:2px}
.win{position:absolute;left:50%;transform:translateX(-50%);top:218px;width:1344px;height:${Math.round(1344 * 0.625) + 38}px;border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 40px 90px rgba(0,0,0,.55),0 0 0 1.5px rgba(255,255,255,.18)}
.bar{height:38px;background:#E8EDF4;display:flex;align-items:center;gap:9px;padding:0 16px}
.bar i{width:12px;height:12px;border-radius:50%;display:block}
.win img{display:block;width:1344px;height:840px;object-fit:cover;object-position:top}
.phone{position:absolute;right:170px;top:92px;width:420px;height:896px;border-radius:64px;background:#0b0f1a;padding:14px;box-shadow:0 40px 90px rgba(0,0,0,.6),0 0 0 3px #2b3345}
.phone img{width:392px;height:868px;border-radius:50px;display:block;object-fit:cover;object-position:top}
.notch{position:absolute;top:24px;left:50%;transform:translateX(-50%);width:120px;height:30px;border-radius:20px;background:#0b0f1a}
</style></head><body>
<div class="frame"></div><div class="c tl"></div><div class="c tr"></div><div class="c bl"></div><div class="c br"></div>
<div class="chip">${esc(s.moduleTitle)}</div>
<div class="cap">${esc(s.caption)}</div>
${phone ? `<div class="phone"><img src="${src}"><div class="notch"></div></div>` : `<div class="win"><div class="bar"><i style="background:#FF5F57"></i><i style="background:#FEBC2E"></i><i style="background:#28C840"></i></div><img src="${src}"></div>`}
<div class="brand">DT <b>Restora</b><small>Datatech BD Ltd · সফটওয়্যার যা ব্যবসা চালায়</small></div>
</body></html>`;
};

const browser = await chromium.launch({ channel: 'chrome' });
const page = await (await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 })).newPage();
const tmp = path.join(HERE, '.frame.html');
let n = 0;
for (const s of shots) {
  writeFileSync(tmp, html(s));
  await page.goto(pathToFileURL(tmp).href);
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(200);
  mkdirSync(path.join(OUT, s.module), { recursive: true });
  await page.screenshot({ path: path.join(OUT, s.file) });
  n += 1;
}
rmSync(tmp, { force: true });
await browser.close();
console.log(`framed ${n} cards`);
