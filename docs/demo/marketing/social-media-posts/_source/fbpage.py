"""Graphics for the DT Restora Facebook page: profile pictures, covers, event cover and intro post.
Run: python3 fbpage.py   then   node render_fbpage.mjs
"""
import os, json, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from common import *

BUILD = ROOT + 'social-media-posts/_build/'
OUT = 'facebook-page/assets/'
os.makedirs(BUILD, exist_ok=True)
SECS, MAN = [], []


def reg(aid, w, h):
    MAN.append({'id': aid, 'w': w, 'h': h, 'out': OUT + aid + '.png'})


def sec(aid, w, h, theme, inner, bn=False, extra=''):
    SECS.append(f'<section class="a {theme}{" bn" if bn else ""}" id="{aid}" style="width:{w}px;height:{h}px;{extra}">{inner}</section>')
    reg(aid, w, h)


def logo(size, t, r):
    return f'<svg style="width:{size}px;height:{size}px;--t:{t};--r:{r};display:block"><use href="#mk"/></svg>'


# ------------------------------------------------------------ profile pictures (1080 square, circle-safe)
sec('profile-picture-1-slate', 1080, 1080, 'dark', f'<div style="margin:auto">{logo(640, "#F59E0B", "#0F172A")}</div>')
sec('profile-picture-2-amber', 1080, 1080, 'amber', f'<div style="margin:auto">{logo(640, "#0F172A", "#F59E0B")}</div>')
sec('profile-picture-3-wordmark', 1080, 1080, 'dark',
    f'<div style="margin:auto;display:flex;flex-direction:column;align-items:center;gap:34px">{logo(430, "#F59E0B", "#0F172A")}'
    '<div style="font-size:112px;font-weight:800;letter-spacing:-.04em">DT <span style="font-weight:600">Restora</span></div></div>')

# preview of circular crops
circ = lambda sz, t, r, bg: f'<div style="width:{sz}px;height:{sz}px;border-radius:50%;overflow:hidden;background:{bg};display:grid;place-items:center">{logo(int(sz*0.6), t, r)}</div>'
prev = ''.join(f'<div style="display:flex;flex-direction:column;align-items:center;gap:14px;font-size:20px;font-weight:600">{circ(s, "#F59E0B", "#0F172A", "#0F172A")}<span>{s}px</span></div>' for s in (170, 128, 80, 40))
prev2 = ''.join(f'<div style="display:flex;flex-direction:column;align-items:center;gap:14px;font-size:20px;font-weight:600">{circ(s, "#0F172A", "#F59E0B", "#F59E0B")}<span>{s}px</span></div>' for s in (170, 128, 80, 40))
sec('profile-picture-preview', 1400, 640, 'light',
    '<div style="margin:auto;display:flex;flex-direction:column;gap:46px;align-items:center"><div style="font-size:30px;font-weight:800">How the profile picture looks when Facebook crops it to a circle</div>'
    f'<div style="display:flex;gap:56px;align-items:flex-end">{prev}</div><div style="display:flex;gap:56px;align-items:flex-end">{prev2}</div></div>')


# ------------------------------------------------------------ covers 1640x624 (2x of 820x312)
def device(spec, w, h, rot, left=None, right=None, top=60):
    side = f'left:{left}px' if left is not None else f'right:{right}px'
    return (f'<div style="position:absolute;{side};top:{top}px;transform:rotate({rot}deg)"><div class="browser" style="width:{w}px"><div class="bar"><i></i><i></i><i></i><span>dt-restora.datatechbd.com</span></div>'
            f'{crop(spec, w, h)}</div></div>')


def cover(aid, theme, title, sub, shot_l, shot_r, bn=False, price=None, chips=None):
    fs = (70 if len(plain(title)) <= 26 else 62) if not bn else 54
    pr = ''
    if price:
        pr = f'<div style="display:flex;align-items:baseline;gap:16px;justify-content:center"><span style="font-size:96px;font-weight:800;letter-spacing:-.05em;color:#0F172A;line-height:1">{price[0]}</span><span style="font-size:26px;font-weight:700;text-align:left;line-height:1.2">{price[1]}</span></div>'
    ch = ''
    if chips:
        ch = '<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">' + ''.join(f'<div class="chip" style="font-size:22px;padding:10px 20px">{ic(i)}{t}</div>' for i, t in chips) + '</div>'
    centre = (f'<div style="position:absolute;left:372px;width:896px;top:0;height:624px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;text-align:center;padding-bottom:70px">'
              f'<div class="brand" style="font-size:30px">{mark(52)}<div>DT <span>Restora</span></div></div>'
              f'<h2 class="big" style="font-size:{fs}px;line-height:1.08">{em(title)}</h2><p style="font-size:{26 if not bn else 24}px;line-height:1.4;font-weight:500;opacity:.85;max-width:780px">{em(sub)}</p>{pr}{ch}</div>')
    sec(aid, 1640, 624, theme, device(shot_l, 520, 330, -5, left=-120, top=90) + device(shot_r, 520, 330, 5, right=-120, top=90) + centre, bn)


cover('cover-1-profit', 'dark', 'Know your *real profit*.', 'POS, kitchen, stock, staff, delivery and accounts in one system.', 'pos-cart:0.5,0.5,1.0', 'dash:0.62,0.55,1.1')
cover('cover-2-offer', 'amber', 'Start free.\nThen *1,000 BDT* a month.', 'First month free after installation. Yearly plan: 2 months free.', 'pos-cart:0.88,0.8,1.6', 'accounting:0.3,0.17,1.5')
cover('cover-3-system', 'light', 'The *complete* restaurant system.', 'Built for restaurants and cafes in Bangladesh.', 'kds:0.5,0.4,1.1', 'w-home:0.5,0.4,1.1',
      chips=[('cart', 'POS'), ('screen', 'Kitchen'), ('box', 'Stock'), ('users', 'Staff'), ('globe', 'Website')])
cover('cover-4-bangla', 'dark', 'শুধু বিক্রি নয়,\nজানুন আপনার *আসল লাভ*।', 'পিওএস, রান্নাঘর, স্টক, কর্মী, লয়্যালটি, ডেলিভারি ও হিসাব, সব এক সিস্টেমে।', 'pos-cart:0.5,0.5,1.0', 'dash:0.62,0.55,1.1', bn=True)

# ------------------------------------------------------------ event cover 1920x1005
sec('event-cover-free-demo', 1920, 1005, 'dark',
    '<div style="display:flex;width:100%;height:100%;align-items:center;padding:0 110px;gap:70px">'
    f'<div style="flex:1;display:flex;flex-direction:column;gap:30px"><div class="brand" style="font-size:36px">{mark(64)}<div>DT <span>Restora</span></div></div>'
    '<h2 class="big" style="font-size:112px">Free live <em>demo</em> for restaurant owners</h2>'
    '<p style="font-size:38px;line-height:1.4;font-weight:500;opacity:.85">See the POS, kitchen screen, stock, payroll and profit dashboard working. Bring your questions.</p>'
    '<div class="swipe" style="align-self:flex-start;font-size:36px;padding:18px 40px">Book your slot</div></div>'
    f'<div style="width:760px;position:relative">{frame("dash:0.62,0.55,1.1", 760, 470)}</div></div>')

# ------------------------------------------------------------ intro post 1080x1350
pill = lambda i, t, s: (f'<div style="display:flex;gap:22px;align-items:center;background:#fff;border:4px solid #0F172A;border-radius:28px;padding:24px 28px;box-shadow:8px 8px 0 #0F172A;color:#0F172A">'
                        f'<div style="width:80px;height:80px;border-radius:22px;background:#F59E0B;border:4px solid #0F172A;display:grid;place-items:center;font-size:44px">{ic(i)}</div>'
                        f'<div><div style="font-size:38px;font-weight:800">{t}</div><div style="font-size:26px;font-weight:500;color:#475569;margin-top:4px">{s}</div></div></div>')
sec('intro-post-hello', 1080, 1350, 'amber',
    '<div style="padding:80px 72px 60px;display:flex;flex-direction:column;gap:30px;width:100%">'
    f'<div class="brand" style="font-size:34px">{mark(64)}<div>DT <span>Restora</span></div></div>'
    '<h2 class="big" style="font-size:104px;margin-top:20px">Hello,<br>we are <span style="color:#fff">DT Restora.</span></h2>'
    '<p style="font-size:34px;line-height:1.4;font-weight:600">The owner control system for restaurants and cafes in Bangladesh.</p>'
    '<div style="display:flex;flex-direction:column;gap:20px;margin-top:10px">'
    + pill('cart', 'Sell faster', 'POS, QR, website, delivery apps') + pill('box', 'Waste less', 'Recipes, stock, wastage log')
    + pill('chart', 'Know your profit', 'Dashboard, payroll, accounts') + '</div>'
    '<div style="margin-top:auto;font-size:30px;font-weight:800;display:flex;justify-content:space-between;border-top:4px solid #0F172A;padding-top:22px"><span>Follow for tips and demos</span><span>dt-restora.datatechbd.com</span></div></div>',
    extra='')

html = page_html = ('<!doctype html><html><head><meta charset="utf-8"><title>fbpage</title><style>' + CSS + '</style></head><body>' + SYMBOLS + ''.join(SECS) + '</body></html>')
open(BUILD + 'fbpage.html', 'w').write(html)
json.dump(MAN, open(BUILD + 'fbpage.json', 'w'))
print(len(MAN), 'assets')
