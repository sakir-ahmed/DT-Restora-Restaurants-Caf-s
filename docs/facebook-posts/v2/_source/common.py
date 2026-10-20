"""Templates and helpers for the DT Restora post generator, v2: parent company (Datatech BD Ltd.) colours and contact details."""
import re, html as _h

ROOT = '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/docs/marketing/'
SITE = 'https://dt-restora.datatechbd.com'
WA = ''  # no WhatsApp link in v2
ASPECT = 0.55  # desktop screenshots are 1600x880

# symbols (logo mark + icons) shared with the cards
_cards = open(ROOT + 'cards/cards.html').read()
_a = _cards.index('<svg width="0"')
SYMBOLS = _cards[_a:_cards.index('</defs></svg>', _a) + len('</defs></svg>')]
import re as _re
SYMBOLS = _re.sub(r'#F59E0B', '#38A8FF', _re.sub(r'#0F172A', '#0A1428', SYMBOLS, flags=_re.I), flags=_re.I)
SYMBOLS = _re.sub(r'<symbol id="mk".*?</symbol>', '<symbol id="mk" viewBox="0 0 120 120"><defs><linearGradient id="mkg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1A78E6"/><stop offset="1" stop-color="#0B52B0"/></linearGradient></defs><rect width="120" height="120" rx="28" fill="url(#mkg)"/><g transform="translate(18 18) scale(0.84)"><path d="M20 62a30 30 0 0 1 60 0z" fill="#fff"/><rect x="16" y="64" width="68" height="8" rx="4" fill="#fff"/><circle cx="50" cy="27" r="5.5" fill="#7DD3FC"/><rect x="36" y="52" width="7" height="10" rx="3.5" fill="#0B63CE"/><rect x="47" y="46" width="7" height="16" rx="3.5" fill="#0B63CE"/><rect x="58" y="40" width="7" height="22" rx="3.5" fill="#0B63CE"/></g></symbol>', SYMBOLS, flags=_re.S)
SYMBOLS = SYMBOLS.replace('<symbol id="i-phone"', '<symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></symbol><symbol id="i-phone"', 1)

BD = str.maketrans('0123456789', '০১২৩৪৫৬৭৮৯')


def bn_digits(s):
    return s.translate(BD)


def em(s):
    """*word* -> <em>word</em>, and escape the rest."""
    parts = re.split(r'(\*[^*]+\*)', s)
    out = ''
    for p in parts:
        if p.startswith('*') and p.endswith('*') and len(p) > 2:
            out += '<em>' + _h.escape(p[1:-1]) + '</em>'
        else:
            out += _h.escape(p)
    return out.replace('\n', '<br>')


def plain(s):
    return s.replace('*', '')


def ic(n, cls=''):
    return f'<svg class="ic {cls}"><use href="#i-{n}"/></svg>'


def mark(size=48, cls=''):
    return f'<svg class="mk {cls}" style="width:{size}px;height:{size}px"><use href="#mk"/></svg>'


def parse_shot(spec):
    """'dash:0.78,0.62,2.2' -> (name, fx, fy, zoom)."""
    if not spec:
        return None
    if ':' in spec:
        n, rest = spec.split(':')
        fx, fy, z = [float(x) for x in rest.split(',')]
        return n, fx, fy, z
    return spec, 0.55, 0.4, 1.0


def crop(spec, w, h, radius=0):
    """Absolutely positioned crop of a screenshot filling a w x h box."""
    s = parse_shot(spec)
    if not s:
        return ''
    n, fx, fy, z = s
    if n.startswith('m-'):
        ratio = 2.036
    else:
        ratio = ASPECT
    z = max(z, h / (w * ratio), 1.0)
    iw = w * z
    ih = iw * ratio
    left = min(0, max(-(iw - w), -(fx * iw - w / 2)))
    top = min(0, max(-(ih - h), -(fy * ih - h / 2)))
    return (f'<div class="crop" style="width:{w}px;height:{h}px;border-radius:{radius}px"><img src="../../../marketing/assets/shots/{n}.jpg" '
            f'style="width:{iw:.0f}px;left:{left:.0f}px;top:{top:.0f}px"></div>')


def frame(spec, w=936, h=520, url='dt-restora.datatechbd.com', tilt=False):
    return (f'<div class="browser fr" style="width:{w}px"><div class="bar"><i></i><i></i><i></i><span>{url}</span></div>'
            f'{crop(spec, w, h)}</div>')


def phone(spec, w=300):
    n = parse_shot(spec)[0]
    return f'<div class="phone" style="width:{w}px"><div class="scr"><img src="../../../marketing/assets/shots/{n}.jpg"></div></div>'


def fsize(text, bn=False, base=(88, 78, 68, 60)):
    n = len(plain(text))
    if bn:
        n = int(n * 1.15)
    s = base[0] if n <= 26 else base[1] if n <= 42 else base[2] if n <= 60 else base[3]
    return int(s * (0.88 if bn else 0.92))


CSS = """
@font-face{font-family:'Poppins';font-weight:500;src:url('../../../marketing/assets/fonts/poppins-latin-500.woff2') format('woff2')}
@font-face{font-family:'Poppins';font-weight:600;src:url('../../../marketing/assets/fonts/poppins-latin-600.woff2') format('woff2')}
@font-face{font-family:'Poppins';font-weight:700;src:url('../../../marketing/assets/fonts/poppins-latin-700.woff2') format('woff2')}
@font-face{font-family:'Hind Siliguri';font-weight:400;src:url('../../../marketing/assets/fonts/hind-siliguri-bengali-400.woff2') format('woff2')}
@font-face{font-family:'Hind Siliguri';font-weight:500;src:url('../../../marketing/assets/fonts/hind-siliguri-bengali-500.woff2') format('woff2')}
@font-face{font-family:'Hind Siliguri';font-weight:600;src:url('../../../marketing/assets/fonts/hind-siliguri-bengali-600.woff2') format('woff2')}
@font-face{font-family:'Hind Siliguri';font-weight:700;src:url('../../../marketing/assets/fonts/hind-siliguri-bengali-700.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:400;src:url('../../../marketing/assets/fonts/inter-latin-400-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:500;src:url('../../../marketing/assets/fonts/inter-latin-500-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:600;src:url('../../../marketing/assets/fonts/inter-latin-600-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:700;src:url('../../../marketing/assets/fonts/inter-latin-700-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:800;src:url('../../../marketing/assets/fonts/inter-latin-800-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:400;src:url('../../../marketing/assets/fonts/noto-sans-bengali-bengali-400-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:500;src:url('../../../marketing/assets/fonts/noto-sans-bengali-bengali-500-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:600;src:url('../../../marketing/assets/fonts/noto-sans-bengali-bengali-600-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:700;src:url('../../../marketing/assets/fonts/noto-sans-bengali-bengali-700-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:800;src:url('../../../marketing/assets/fonts/noto-sans-bengali-bengali-800-normal.woff2') format('woff2')}
:root{--ink:#0A1428;--ink2:#13213D;--ink3:#334155;--muted:#64748B;--line:#E2E8F0;--paper:#F5F7FA;--amber:#38A8FF;--amberD:#0B63CE;--amberS:#E3F0FF;--green:#059669;--greenS:#D1FAE5;--rose:#E11D48;--roseS:#FFE4E6}
*{box-sizing:border-box;margin:0;padding:0}
body{background:#AFC0D6;font-family:'Inter','Hind Siliguri',system-ui,sans-serif;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;gap:30px;align-items:flex-start;padding:20px}
.s{position:relative;overflow:hidden;display:flex;flex-direction:column;flex:none;font-family:'Inter','Hind Siliguri',sans-serif}
.s.f{width:1080px;height:1350px;padding:70px 72px 56px}
.s.dark{background:#0A1428;color:#fff}.s.light{background:#F5F7FA;color:#0A1428}.s.amber{background:#0B63CE;color:#fff}
.s.f::before{content:"";position:absolute;right:-230px;top:-230px;width:640px;height:640px;border-radius:50%;border:72px solid rgba(56,168,255,.11)}
.s.amber::before{border-color:rgba(255,255,255,.22)}
.s>*{position:relative}
.bn,.bn *{letter-spacing:0!important;text-transform:none!important}
.bn h2{line-height:1.3!important}
.hd{display:flex;justify-content:space-between;align-items:center}
.brand{display:flex;align-items:center;gap:12px;font-weight:800;font-size:28px;letter-spacing:-.02em}
.brand span{font-weight:600}
.pill{font-size:19px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:9px 18px;border-radius:999px;background:rgba(56,168,255,.16);color:#38A8FF}
.light .pill{background:#E3F0FF;color:#0B63CE}.amber .pill{background:rgba(255,255,255,.18);color:#fff}
.mk{display:block}
.dark .mk,.dark.mkd .mk{--t:#38A8FF;--r:#0A1428}
.amber .mk{--t:#fff;--r:#0B63CE}
h2.t{font-weight:800;letter-spacing:-.035em;line-height:1.04;margin-top:34px}
h2.t em{font-style:normal;color:#38A8FF}.light h2.t em{color:#0B63CE}.amber h2.t em{color:#BFE0FF}
.sub{font-size:30px;line-height:1.42;margin-top:20px;color:#AFC0D6;font-weight:500}
.light .sub{color:#475569}.amber .sub{color:rgba(255,255,255,.88)}
.body{flex:1;display:flex;flex-direction:column;justify-content:center;gap:18px;min-height:0}
.ft{display:flex;justify-content:space-between;align-items:center;font-size:23px;font-weight:700;padding-top:22px;border-top:2px solid rgba(148,163,184,.3);color:#AFC0D6}
.light .ft{color:#64748B;border-color:#E2E8F0}.amber .ft{color:#fff;border-color:rgba(255,255,255,.3)}
.ft b{color:#38A8FF}.light .ft b{color:#0B63CE}.amber .ft b{color:#fff}
.ic{width:1em;height:1em;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.browser{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 28px 54px -20px rgba(2,6,23,.55),0 0 0 1px rgba(10,20,40,.12)}
.browser .bar{height:32px;background:#E8EDF3;display:flex;align-items:center;gap:7px;padding:0 14px;border-bottom:1px solid #D5DCE6}
.browser .bar i{width:10px;height:10px;border-radius:50%;display:block;background:#F87171}.browser .bar i:nth-child(2){background:#38A8FF}.browser .bar i:nth-child(3){background:#4ADE80}
.browser .bar span{margin-left:12px;height:20px;border-radius:10px;background:#fff;font-size:11px;line-height:20px;padding:0 12px;color:#64748B;font-weight:500}
.crop{position:relative;overflow:hidden;background:#fff}.crop img{position:absolute;display:block;max-width:none}
.phone{background:#08101F;border-radius:44px;padding:11px;box-shadow:0 30px 56px -18px rgba(2,6,23,.6),0 0 0 2px #13213D;position:relative}
.phone .scr{border-radius:34px;overflow:hidden;background:#fff;padding-top:20px}.phone img{display:block;width:100%}
.phone::before{content:"";position:absolute;top:15px;left:50%;transform:translateX(-50%);width:22%;height:10px;border-radius:6px;background:#08101F;z-index:2}
.chips{display:flex;flex-wrap:wrap;gap:12px}
.chip{display:inline-flex;align-items:center;gap:10px;font-size:24px;font-weight:700;border-radius:999px;padding:12px 22px;background:rgba(56,168,255,.16);color:#38A8FF}
.light .chip{background:#fff;border:1.5px solid #E2E8F0;color:#0A1428}.amber .chip{background:rgba(255,255,255,.16);color:#fff}
.chip .ic{font-size:26px}
.tag{position:absolute;background:#0A1428;color:#fff;font-weight:700;font-size:22px;padding:10px 20px;border-radius:999px;box-shadow:0 10px 22px rgba(2,6,23,.4);z-index:3}
.dark .tag{background:#38A8FF;color:#0A1428}
.fwrap{position:relative;align-self:center}
.grid6{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.tile{border-radius:22px;padding:22px 24px;display:flex;align-items:center;gap:18px;font-size:27px;font-weight:700;line-height:1.2}
.dark .tile{background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.12)}.light .tile{background:#fff;border:1.5px solid #E2E8F0}.amber .tile{background:rgba(255,255,255,.14)}
.tile .ic{font-size:40px;color:#38A8FF}.light .tile .ic{color:#0B63CE}.amber .tile .ic{color:#fff}
.vsr{display:grid;grid-template-columns:1fr 54px 1fr;align-items:center}
.vsr .l,.vsr .r{border-radius:20px;padding:20px 24px;font-size:27px;font-weight:700;line-height:1.28;min-height:96px;display:flex;align-items:center}
.vsr .l{background:#FFE4E6;color:#9F1239}.vsr .r{background:#D1FAE5;color:#065F46}.vsr .a{text-align:center;font-size:34px;display:grid;place-items:center}
.vh{display:grid;grid-template-columns:1fr 54px 1fr;font-size:20px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.vh span:first-child{color:#E11D48}.vh span:last-child{color:#10B981}
.cl{display:flex;flex-direction:column;gap:14px}
.cli{display:flex;gap:22px;align-items:center;border-radius:22px;padding:15px 24px;font-size:31px;font-weight:700;line-height:1.25}
.dark .cli{background:rgba(255,255,255,.07)}.light .cli{background:#fff;border:1.5px solid #E2E8F0}.amber .cli{background:rgba(255,255,255,.14)}
.num{width:54px;height:54px;border-radius:50%;background:#38A8FF;color:#0A1428;font-weight:800;font-size:30px;display:grid;place-items:center;flex:none}
.amber .num{background:#fff;color:#0B63CE}
.opt{display:flex;gap:22px;align-items:center;border-radius:26px;padding:26px 30px;font-size:34px;font-weight:700;border:2px solid rgba(56,168,255,.5)}
.light .opt{border-color:#0B63CE;background:#fff}.amber .opt{border-color:#fff;background:rgba(255,255,255,.14)}
.opt b{width:58px;height:58px;border-radius:16px;background:#38A8FF;color:#0A1428;display:grid;place-items:center;font-size:30px;flex:none}.amber .opt b{background:#fff;color:#0B63CE}
.price{font-size:210px;font-weight:800;letter-spacing:-.06em;line-height:.88;color:#38A8FF}.light .price{color:#0B63CE}.amber .price{color:#fff}
.pr-u{font-size:36px;font-weight:700;line-height:1.25}
.pr-u s{display:block;font-size:34px;opacity:.65;font-weight:600}
.bar-cta{background:#38A8FF;color:#0A1428;border-radius:24px;padding:26px 34px;font-size:36px;font-weight:800;display:flex;align-items:center;justify-content:space-between}
.amber .bar-cta{background:#fff;color:#0B63CE}
.big{font-weight:800;letter-spacing:-.04em;line-height:1.02}
.big em{font-style:normal;color:#38A8FF}.light .big em{color:#0B63CE}.amber .big em{color:#BFE0FF}
.cnt{font-size:22px;font-weight:700;color:#AFC0D6}.swipe{font-size:22px;font-weight:700;background:#38A8FF;color:#0A1428;border-radius:999px;padding:10px 20px;display:inline-flex;gap:8px;align-items:center}
.stepn{font-size:150px;font-weight:800;line-height:.9;color:#38A8FF;letter-spacing:-.05em}.light .stepn{color:#0B63CE}.amber .stepn{color:#fff}
.ctab{border-radius:30px;padding:36px 40px;display:flex;flex-direction:column;gap:20px}
.dark .ctab{background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.14)}
.cline{display:flex;align-items:center;gap:20px;font-size:32px;font-weight:700}.cline .ic{font-size:42px;color:#38A8FF}
/* assets */
.a{position:relative;overflow:hidden;flex:none;font-family:'Inter','Hind Siliguri',sans-serif;display:flex}
.a.dark{background:#0A1428;color:#fff}.a.light{background:#F8FAFC;color:#0A1428}.a.amber{background:#0B63CE;color:#fff}
.a>*{position:relative}
"""


FRAME_CSS = """
/* Datatech brand layer: Poppins headlines, wordmark colours as in the DT Restora logo, common minimal frame */
h2.t,.big,.q .qt,.q .mf .tx,.q .stamp,.price,.stepn,.brand,.pill,.pr-u,.bar-cta,.num,.q .logo,.q .lab,.q .stk .cap,.tag{font-family:'Poppins','Hind Siliguri',sans-serif}
h2.t,.big,.q .qt,.q .mf .tx{font-weight:600;letter-spacing:-.015em}
.bn h2.t,.bn .big,.bn .qt,.bn .tx{font-weight:700;letter-spacing:0}
.price,.stepn{font-weight:700;letter-spacing:-.03em}
.brand,.q .logo{font-weight:700;letter-spacing:-.01em}
.brand span,.q .logo span{font-weight:700;color:#0B63CE}
.dark .brand span{color:#38A8FF}.amber .brand span{color:#BFE0FF}
.pill,.q .lab{font-weight:600;letter-spacing:.08em}
.s.f,.q{--fr:rgba(10,20,40,.16);--fa:#0B63CE}
.s.f.dark{--fr:rgba(255,255,255,.20);--fa:#38A8FF}
.s.f.amber{--fr:rgba(255,255,255,.38);--fa:#fff}
.s.f::before{display:none}
.s.f::after,.q::after{content:"";position:absolute;inset:26px;border:2px solid var(--fr);pointer-events:none;z-index:6;
 background:
 linear-gradient(var(--fa),var(--fa)) 0 0/68px 6px no-repeat,linear-gradient(var(--fa),var(--fa)) 0 0/6px 68px no-repeat,
 linear-gradient(var(--fa),var(--fa)) 100% 0/68px 6px no-repeat,linear-gradient(var(--fa),var(--fa)) 100% 0/6px 68px no-repeat,
 linear-gradient(var(--fa),var(--fa)) 0 100%/68px 6px no-repeat,linear-gradient(var(--fa),var(--fa)) 0 100%/6px 68px no-repeat,
 linear-gradient(var(--fa),var(--fa)) 100% 100%/68px 6px no-repeat,linear-gradient(var(--fa),var(--fa)) 100% 100%/6px 68px no-repeat}
.q.cream,.q.mint,.q.sky,.q.rose,.q.lilac,.q.white{background:#EEF4FC}
.q.sun{background:#38A8FF}
.q.sun::after{--fr:rgba(10,20,40,.3);--fa:#0A1428}
"""


def page(sections, bn_default=False, extra_css=''):
    return ('<!doctype html><html><head><meta charset="utf-8"><title>social</title><style>' + CSS + extra_css + FRAME_CSS + '</style></head><body>'
            + SYMBOLS + ''.join(sections) + '</body></html>')


def header(label, theme='dark'):
    return f'<div class="hd"><div class="brand">{mark(48)}<div>DT <span>Restora</span></div></div><div class="pill">{_h.escape(label)}</div></div>'


def footer(left=None, right=None, bn=False):
    left = left or ('বাংলাদেশের রেস্টুরেন্ট ও ক্যাফের জন্য' if bn else 'Restaurant software for Bangladesh')
    right = right or 'dt-restora.datatechbd.com'
    return f'<div class="ft"><span>{left}</span><b>{right}</b></div>'


def wrap(pid, theme, bn, inner, extra=''):
    cls = f's f {theme}{" bn" if bn else ""}{extra}'
    return f'<section class="{cls}" id="{pid}">{inner}</section>'


def title_html(p, bn, base=(88, 78, 68, 60)):
    fs = fsize(p['title'], bn, base)
    return f'<h2 class="t" style="font-size:{fs}px">{em(p["title"])}</h2>'


# ---------------------------------------------------------------- templates
def t_hero(p, pid, bn):
    shot = frame(p['shot'], 936, p.get('h', 500))
    chips = ''.join(f'<div class="chip">{ic(p.get("icons", ["check"] * 3)[i % len(p.get("icons", ["check"]))])}{_h.escape(b)}</div>' for i, b in enumerate(p['bullets'][:3]))
    tag = f'<div class="tag" style="left:60px;top:-18px">{_h.escape(p["tag"])}</div>' if p.get('tag') else ''
    inner = (header(p['label']) + title_html(p, bn) + f'<p class="sub">{em(p["sub"])}</p>'
             + f'<div class="body"><div class="fwrap">{tag}{shot}</div><div class="chips">{chips}</div></div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_statement(p, pid, bn):
    shot = frame(p['shot'], 936, p.get('h', 330))
    inner = (header(p['label']) + f'<div class="body" style="justify-content:center"><h2 class="big" style="font-size:{fsize(p["title"], bn, (116, 104, 92, 80))}px">{em(p["title"])}</h2>'
             + f'<p class="sub" style="margin-top:6px">{em(p["sub"])}</p></div><div class="fwrap" style="margin-bottom:26px">{shot}</div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_grid(p, pid, bn):
    icons = p.get('icons', ['check'] * 6)
    tiles = ''.join(f'<div class="tile">{ic(icons[i % len(icons)])}<span>{_h.escape(b)}</span></div>' for i, b in enumerate(p['bullets'][:6]))
    shot = frame(p['shot'], 936, p.get('h', 270)) if p.get('shot') else ''
    inner = (header(p['label']) + title_html(p, bn) + f'<p class="sub">{em(p["sub"])}</p>'
             + f'<div class="body"><div class="grid6">{tiles}</div><div class="fwrap" style="margin-top:8px">{shot}</div></div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_vs(p, pid, bn):
    bad, good = p.get('cols', ('Without a system', 'With DT Restora') if not bn else ('সিস্টেম ছাড়া', 'DT Restora দিয়ে'))
    rows = ''.join(f'<div class="vsr"><div class="l">{_h.escape(a)}</div><div class="a">{ic("arrow")}</div><div class="r">{_h.escape(b)}</div></div>' for a, b in p['pairs'][:4])
    shot = frame(p['shot'], 936, p.get('h', 250)) if p.get('shot') else ''
    inner = (header(p['label']) + title_html(p, bn) + f'<p class="sub">{em(p["sub"])}</p>'
             + f'<div class="body"><div class="vh"><span>{bad}</span><span></span><span>{good}</span></div>{rows}<div class="fwrap" style="margin-top:6px">{shot}</div></div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_check(p, pid, bn):
    items = ''.join(f'<div class="cli"><div class="num">{(bn_digits(str(i + 1)) if bn else i + 1)}</div><span>{_h.escape(b)}</span></div>' for i, b in enumerate(p['bullets'][:5]))
    shot = frame(p['shot'], 936, min(p.get('h', 250), 190)) if p.get('shot') else ''
    inner = (header(p['label']) + title_html(p, bn) + f'<p class="sub">{em(p["sub"])}</p>'
             + f'<div class="body"><div class="cl">{items}</div><div class="fwrap" style="margin-top:8px">{shot}</div></div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_poll(p, pid, bn):
    opts = ''.join(f'<div class="opt"><b>{"ABCD"[i] if not bn else "কখগঘ"[i]}</b><span>{_h.escape(o)}</span></div>' for i, o in enumerate(p['bullets'][:4]))
    shot = frame(p['shot'], 936, p.get('h', 230)) if p.get('shot') else ''
    cta = 'মন্তব্যে আপনার উত্তর জানান' if bn else 'Comment your answer below'
    inner = (header(p['label']) + f'<h2 class="big" style="margin-top:34px;font-size:{fsize(p["title"], bn, (96, 86, 76, 66))}px">{em(p["title"])}</h2>'
             + f'<p class="sub">{em(p["sub"])}</p><div class="body"><div class="cl">{opts}</div><div class="chips" style="margin-top:6px"><div class="chip">{ic("check")}{cta}</div></div>'
             + f'<div class="fwrap" style="margin-top:8px">{shot}</div></div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_offer(p, pid, bn):
    price = p.get('price', '1,000')
    unit = p.get('unit', ('BDT<br>per month<s>5,700 BDT regular</s>' if not bn else 'টাকা<br>প্রতি মাসে<s>নিয়মিত ৫,৭০০ টাকা</s>'))
    pills = ''.join(f'<div class="cli"><div class="num">{ic("check")}</div><span>{_h.escape(b)}</span></div>' for b in p['bullets'][:3])
    shot = frame(p['shot'], 936, p.get('h', 220)) if p.get('shot') else ''
    inner = (header(p['label']) + title_html(p, bn, (82, 74, 66, 58))
             + f'<div style="display:flex;align-items:baseline;gap:22px;margin-top:20px"><div class="price">{price}</div><div class="pr-u">{unit}</div></div>'
             + f'<div class="body"><div class="cl">{pills}</div><div class="fwrap" style="margin-top:6px">{shot}</div></div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_step(p, pid, bn, n, total, slide):
    """One carousel inner slide."""
    num = bn_digits(f'{n:02d}') if bn else f'{n:02d}'
    cnt = bn_digits(f'{n}/{total}') if bn else f'{n}/{total}'
    shot = frame(slide['shot'], 936, slide.get('sh', 470)) if slide.get('shot') else ''
    inner = (f'<div class="hd"><div class="brand">{mark(44)}<div>DT <span>Restora</span></div></div><div class="cnt">{cnt}</div></div>'
             + f'<div class="stepn" style="margin-top:34px">{num}</div><h2 class="t" style="margin-top:14px;font-size:{fsize(slide["head"], bn, (76, 70, 62, 54))}px">{em(slide["head"])}</h2>'
             + f'<p class="sub">{em(slide["text"])}</p><div class="body"><div class="fwrap">{shot}</div></div>' + footer(bn=bn))
    return wrap(pid, slide.get('theme', p['theme']), bn, inner)


def t_cover(p, pid, bn, total):
    shot = frame(p['shot'], 936, p.get('h', 470)) if p.get('shot') else ''
    sw = ('সোয়াইপ করুন' if bn else 'Swipe') + ' &rarr;'
    inner = (header(p['label']) + title_html(p, bn) + f'<p class="sub">{em(p["sub"])}</p><div class="body"><div class="fwrap">{shot}</div>'
             + f'<div class="chips"><div class="swipe">{sw}</div></div></div>' + footer(bn=bn))
    return wrap(pid, p['theme'], bn, inner)


def t_cta(p, pid, bn, total):
    t = 'নিজের আসল হিসাব দেখতে প্রস্তুত?' if bn else 'Ready to see your *real* numbers?'
    t = t.replace('আসল হিসাব', '*আসল হিসাব*') if bn else t
    sub = 'ফ্রি লাইভ ডেমো দেখুন, প্রথম মাস ফ্রি।' if bn else 'Try the free live demo. First month free after installation.'
    name = 'ডাটাটেক বিডি লিমিটেড' if bn else 'Datatech BD Ltd.'
    role = 'DT Restora-এর নির্মাতা' if bn else 'The company behind DT Restora'
    sales = 'বিক্রয় ও গ্রাহক জিজ্ঞাসা' if bn else 'Sales and customer inquiries'
    addr = 'ফ্ল্যাট ৪-এ, ধূমকেতু শিকদার টাওয়ার, ৬৬ পশ্চিম মনিপুর, মিরপুর, ঢাকা-১২১৬' if bn else 'Flat 4-A, Dhumketu Shikder Tower, 66 Paschim Monipur, Mirpur, Dhaka-1216'
    inner = (header(p['label']) + f'<h2 class="t" style="font-size:{fsize(plain(t), bn, (92, 84, 74, 64))}px">{em(t)}</h2><p class="sub">{sub}</p>'
             + f'<div class="body"><div class="ctab"><div style="font-size:42px;font-weight:800">{name}</div><div style="font-size:23px;color:#AFC0D6;margin-top:-10px">{role}</div>'
             + f'<div class="cline">{ic("phone")}<span>+8801575888775</span></div>'
             + f'<div class="cline">{ic("mail")}<span>sales@datatechbd.com <small style="font-size:22px;color:#AFC0D6;font-weight:500">{sales}</small></span></div>'
             + f'<div class="cline">{ic("globe")}dt-restora.datatechbd.com</div>'
             + f'<div class="cline" style="font-size:23px;font-weight:500;align-items:flex-start;line-height:1.35">{ic("pin")}<span>{addr}</span></div></div>'
             + f'<div class="bar-cta"><span>{"ফ্রি ডেমো দেখুন" if bn else "Try the live demo"}</span>{ic("arrow")}</div></div>' + footer(bn=bn))
    return wrap(pid, 'dark', bn, inner)


TPL = {'hero': t_hero, 'statement': t_statement, 'grid': t_grid, 'vs': t_vs, 'check': t_check, 'poll': t_poll, 'offer': t_offer}
