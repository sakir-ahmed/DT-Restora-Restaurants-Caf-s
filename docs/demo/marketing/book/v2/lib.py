"""Helpers and styles for the DT Restora Bangla product book (v2)."""
import json
import os
import base64
import io
from PIL import Image, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
ICONS = json.load(open(os.path.join(HERE, 'icons.json')))
MARK = open(os.path.join(HERE, '../../../../public/favicon.svg')).read()
_DIMS = {}

BN = str.maketrans('0123456789', '০১২৩৪৫৬৭৮৯')


def bn(n):
    return str(n).translate(BN)


def dims(name):
    if name not in _DIMS:
        _DIMS[name] = Image.open(os.path.join(HERE, 'shots', name + '.jpg')).size
    return _DIMS[name]


def ic(name, cls=''):
    i = ICONS[''.join(p.capitalize() for p in name.split('-'))]
    return f'<svg class="ic {cls}" viewBox="0 0 {i["w"]} {i["h"]}" fill="currentColor"><path d="{i["d"]}"/></svg>'


def mark(size=30):
    return f'<span class="mk" style="width:{size}px;height:{size}px">{MARK}</span>'


def crop(name, x, y, w, h, dw, cls='', style=''):
    """Shows the region (x, y, w, h) of a screenshot at display width dw."""
    W, _ = dims(name)
    s = dw / w
    dh = round(h * s)
    return (f'<div class="crop {cls}" style="width:{dw}px;height:{dh}px;{style}">'
            f'<img src="shots/{name}.jpg" style="width:{round(W * s)}px;left:{-round(x * s)}px;top:{-round(y * s)}px"></div>')


_SH = {}


def shadow(w, h, radius=14, blur=24, alpha=0.36, drop=24, m=52):
    """Pre-blurred shadow image (PDF viewers draw CSS blur shadows as grey blocks)."""
    key = (round(w / 8), round(h / 8), radius)
    if key not in _SH:
        k = 0.5
        W, H = round((w + 2 * m) * k), round((h + 2 * m) * k)
        im = Image.new('L', (W, H), 0)
        ImageDraw.Draw(im).rounded_rectangle([m * k, m * k, (m + w) * k, (m + h) * k], radius * k, fill=255)
        im = im.filter(ImageFilter.GaussianBlur(blur * k))
        out = Image.new('RGBA', (W, H), (10, 20, 40, 0))
        out.putalpha(im.point(lambda v: int(v * alpha)))
        buf = io.BytesIO()
        out.save(buf, 'PNG', optimize=True)
        _SH[key] = 'data:image/png;base64,' + base64.b64encode(buf.getvalue()).decode()
    uri = _SH[key]
    return (f'<div class="sh" style="left:{-m}px;top:{-m + drop}px;width:{w + 2 * m}px;height:{h + 2 * m}px;'
            f'background-image:url({uri})"></div>')


def card(name, x, y, w, h, dw, chrome=True, cls='', style=''):
    """Screenshot region as a floating card, optionally with a small window bar."""
    bar = '<div class="bar"><i></i><i></i><i></i></div>' if chrome else ''
    dh = round(h * dw / w) + (15 if chrome else 0)
    return (f'<div class="cw" style="width:{dw}px;{style}">{shadow(dw, dh)}'
            f'<div class="card {cls}">{bar}{crop(name, x, y, w, h, dw)}</div></div>')


def full(name, dw, **k):
    W, H = dims(name)
    return card(name, 0, 0, W, H, dw, **k)


def phone(name, dw, y=0, h=None, style='', cls=''):
    W, H = dims(name)
    h = h or H - y
    inner = dw - 14
    ph = round(h * inner / W) + 14
    return (f'<div class="cw" style="width:{dw}px;{style}">{shadow(dw, ph, 34, 26, 0.45, 26)}'
            f'<div class="phone {cls}"><div class="notch"></div>{crop(name, 0, y, W, h, inner, "pc")}</div></div>')


def chip(icon, text, style, tone=''):
    return f'<div class="chip {tone}" style="{style}"><span class="ci">{ic(icon)}</span>{text}</div>'


def stage(inner, w, h, style=''):
    return f'<div class="stage" style="width:{w}px;height:{h}px;{style}">{inner}</div>'


def bullets(items, cols=2):
    out = []
    for icon, title, desc in items:
        out.append(f'<div class="bl"><div class="bi">{ic(icon)}</div><div><h4>{title}</h4><p>{desc}</p></div></div>')
    return f'<div class="bls c{cols}">' + ''.join(out) + '</div>'


def callout(icon, title, text, dark=False):
    return (f'<div class="co {"dk" if dark else ""}"><div class="coi">{ic(icon)}</div>'
            f'<div><b>{title}</b><span>{text}</span></div></div>')


CSS = r"""
@font-face{font-family:'Hind Siliguri';font-weight:400;src:url(../../assets/fonts/hind-siliguri-bengali-400.woff2) format('woff2')}
@font-face{font-family:'Hind Siliguri';font-weight:500;src:url(../../assets/fonts/hind-siliguri-bengali-500.woff2) format('woff2')}
@font-face{font-family:'Hind Siliguri';font-weight:600;src:url(../../assets/fonts/hind-siliguri-bengali-600.woff2) format('woff2')}
@font-face{font-family:'Hind Siliguri';font-weight:700;src:url(../../assets/fonts/hind-siliguri-bengali-700.woff2) format('woff2')}
@font-face{font-family:'Inter';font-weight:400;src:url(../../assets/fonts/inter-latin-400-normal.woff2) format('woff2')}
@font-face{font-family:'Inter';font-weight:600;src:url(../../assets/fonts/inter-latin-600-normal.woff2) format('woff2')}
@font-face{font-family:'Inter';font-weight:800;src:url(../../assets/fonts/inter-latin-800-normal.woff2) format('woff2')}
@font-face{font-family:'Poppins';font-weight:600;src:url(../../assets/fonts/poppins-latin-600.woff2) format('woff2')}
@font-face{font-family:'Poppins';font-weight:700;src:url(../../assets/fonts/poppins-latin-700.woff2) format('woff2')}
:root{--navy:#0A1428;--navy2:#0B2450;--blue:#0B63CE;--blue2:#1A78E6;--sky:#38A8FF;--sky-soft:#E4F2FF;--cloud:#F5F7FA;--ink:#0A1428;--ink3:#3E4C66;--muted:#6B7A94;--line:#E1E8F2;--green:#0E9F5B;--green-soft:#E3F6EC;--red:#E5484D;--red-soft:#FDECEC}
@page{size:A4;margin:0}
*{box-sizing:border-box;margin:0;padding:0}
html{background:#8794ad}
body{font-family:'Hind Siliguri','Inter',sans-serif;color:var(--ink);display:flex;flex-direction:column;align-items:center;gap:24px;padding:24px 0;-webkit-font-smoothing:antialiased}
@media print{html{background:none}body{display:block;padding:0}.pg{break-after:page;margin:0!important}}
.pg{width:794px;height:1123px;position:relative;overflow:hidden;background:var(--cloud);padding:48px 48px 44px;font-size:14px;line-height:1.55;flex:none}
.pg::before{content:'';position:absolute;right:-160px;top:-160px;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,rgba(56,168,255,.22),transparent 68%)}
.pg.dark{background:radial-gradient(700px 520px at 100% 0%,rgba(56,168,255,.38),transparent 62%),radial-gradient(640px 480px at 0% 100%,rgba(11,99,206,.5),transparent 62%),linear-gradient(160deg,#0A1428 0%,#0B2450 60%,#0A3A82 130%);color:#fff}
.pg.dark::before{display:none}
.top{position:relative;display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:600;color:var(--muted);padding-bottom:12px;border-bottom:1px solid var(--line)}
.dark .top{color:#9FB6D6;border-color:rgba(255,255,255,.16)}
.top .lg{display:flex;align-items:center;gap:8px;color:var(--ink);font-family:Poppins,sans-serif;font-weight:600;font-size:14px}
.top .lg b{color:var(--blue);font-weight:600}.dark .top .lg{color:#fff}.dark .top .lg b{color:var(--sky)}
.mk{display:inline-block;flex:none}.mk svg{width:100%;height:100%;display:block}
.foot{position:absolute;left:48px;right:48px;bottom:20px;display:flex;justify-content:space-between;font-size:11px;color:var(--muted);font-weight:500}
.dark .foot{color:#8FA6C7}
.corner{position:absolute;width:34px;height:34px;border:3px solid var(--sky)}
.corner.tl{left:20px;top:20px;border-right:0;border-bottom:0;border-radius:14px 0 0 0}.corner.br{right:20px;bottom:20px;border-left:0;border-top:0;border-radius:0 0 14px 0}
.kick{position:relative;display:inline-flex;align-items:center;gap:8px;margin-top:24px;font-size:13px;font-weight:700;letter-spacing:.04em;color:var(--blue)}
.kick::before{content:'';width:26px;height:3px;border-radius:3px;background:var(--sky)}
.dark .kick{color:var(--sky)}
h1{position:relative;font-size:36px;line-height:1.22;font-weight:700;letter-spacing:-.01em;margin-top:6px}
h1 em{font-style:normal;color:var(--blue)}.dark h1 em{color:var(--sky)}
.lead{position:relative;font-size:16px;line-height:1.6;color:var(--ink3);margin-top:10px;max-width:640px}
.dark .lead{color:#C4D3EA}
h4{font-size:15px;font-weight:700;line-height:1.35}
p{font-size:13px;color:var(--ink3);line-height:1.55}.dark p{color:#B7C7E0}
.ic{width:1em;height:1em;display:block}
.stage{position:relative}
.cw{position:absolute}.sh{position:absolute;background-repeat:no-repeat;background-size:100% 100%;pointer-events:none}
.card{position:relative;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 0 0 1px rgba(10,20,40,.1)}
.card .bar{height:15px;background:#E9EEF6;display:flex;align-items:center;gap:5px;padding:0 9px}
.card .bar i{width:6px;height:6px;border-radius:50%;background:#C3CDDC;display:block}
.card .bar i:nth-child(1){background:#FF6B62}.card .bar i:nth-child(2){background:#FEBC2E}.card .bar i:nth-child(3){background:#28C840}
.crop{position:relative;overflow:hidden;background:#fff}.crop img{position:absolute;display:block;max-width:none}
.phone{position:relative;background:#0b0f1a;border-radius:34px;padding:7px;box-shadow:0 0 0 2px #2b3345}
.phone .pc{border-radius:27px}.notch{position:absolute;z-index:3;top:11px;left:50%;transform:translateX(-50%);width:34%;height:12px;border-radius:9px;background:#0b0f1a}
.chip{position:absolute;z-index:5;display:flex;align-items:center;gap:8px;background:#fff;color:var(--ink);font-size:12.5px;font-weight:700;border-radius:999px;padding:7px 14px 7px 8px;box-shadow:0 0 0 1px rgba(10,20,40,.1),0 3px 0 rgba(10,20,40,.07);white-space:nowrap}
.chip .ci{width:24px;height:24px;border-radius:50%;background:var(--blue);color:#fff;display:grid;place-items:center;font-size:12px;flex:none}
.chip.g .ci{background:var(--green)}.chip.s .ci{background:var(--sky)}.chip.n .ci{background:var(--navy)}
.bls{display:grid;gap:12px 16px}.bls.c2{grid-template-columns:1fr 1fr}.bls.c1{grid-template-columns:1fr}.bls.c3{grid-template-columns:repeat(3,1fr)}
.bl{display:flex;gap:11px;align-items:flex-start;background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px 13px}
.dark .bl{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.14)}
.bi{width:34px;height:34px;border-radius:10px;background:var(--sky-soft);color:var(--blue);display:grid;place-items:center;font-size:16px;flex:none}
.dark .bi{background:rgba(56,168,255,.2);color:var(--sky)}
.bl h4{font-size:14px}.bl p{font-size:12.2px;margin-top:2px;line-height:1.45}
.co{display:flex;gap:13px;align-items:center;border-radius:16px;padding:14px 18px;background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff}
.co .coi{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:20px;flex:none}
.co b{display:block;font-size:15px;font-weight:700}.co span{display:block;font-size:12.8px;opacity:.92;line-height:1.45;margin-top:1px}
.co.dk{background:linear-gradient(135deg,#0A1428,#0B2450);border:1px solid rgba(56,168,255,.35)}
.pill{display:inline-block;border-radius:999px;background:var(--sky-soft);color:var(--blue);font-weight:700;font-size:12px;padding:3px 12px}
.dark .pill{background:rgba(56,168,255,.2);color:var(--sky)}
.num{font-family:'Hind Siliguri',sans-serif;font-weight:700}
.abs{position:absolute}
"""
