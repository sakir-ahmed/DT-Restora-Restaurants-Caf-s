"""Templates and helpers for the DT Restora social post generator."""
import re, html as _h

ROOT = '/Users/sakir.ahmed/web-artisan/practices/dt-top-ten/DT-Restora-Restaurants-Caf-s/marketing/'
SITE = 'https://dt-restora.datatechbd.com'
WA = 'https://wa.me/8801304734623'
ASPECT = 0.55  # desktop screenshots are 1600x880

# symbols (logo mark + icons) shared with the cards
_cards = open(ROOT + 'cards/cards.html').read()
_a = _cards.index('<svg width="0"')
SYMBOLS = _cards[_a:_cards.index('</defs></svg>', _a) + len('</defs></svg>')]

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
    return (f'<div class="crop" style="width:{w}px;height:{h}px;border-radius:{radius}px"><img src="../../assets/shots/{n}.jpg" '
            f'style="width:{iw:.0f}px;left:{left:.0f}px;top:{top:.0f}px"></div>')


def frame(spec, w=936, h=520, url='dt-restora.datatechbd.com', tilt=False):
    return (f'<div class="browser fr" style="width:{w}px"><div class="bar"><i></i><i></i><i></i><span>{url}</span></div>'
            f'{crop(spec, w, h)}</div>')


def phone(spec, w=300):
    n = parse_shot(spec)[0]
    return f'<div class="phone" style="width:{w}px"><div class="scr"><img src="../../assets/shots/{n}.jpg"></div></div>'


def fsize(text, bn=False, base=(88, 78, 68, 60)):
    n = len(plain(text))
    if bn:
        n = int(n * 1.15)
    s = base[0] if n <= 26 else base[1] if n <= 42 else base[2] if n <= 60 else base[3]
    return int(s * (0.88 if bn else 1))


CSS = """
@font-face{font-family:'Inter';font-weight:400;src:url('../../assets/fonts/inter-latin-400-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:500;src:url('../../assets/fonts/inter-latin-500-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:600;src:url('../../assets/fonts/inter-latin-600-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:700;src:url('../../assets/fonts/inter-latin-700-normal.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:800;src:url('../../assets/fonts/inter-latin-800-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:400;src:url('../../assets/fonts/noto-sans-bengali-bengali-400-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:500;src:url('../../assets/fonts/noto-sans-bengali-bengali-500-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:600;src:url('../../assets/fonts/noto-sans-bengali-bengali-600-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:700;src:url('../../assets/fonts/noto-sans-bengali-bengali-700-normal.woff2') format('woff2')}
@font-face{font-family:'Noto Sans Bengali';font-weight:800;src:url('../../assets/fonts/noto-sans-bengali-bengali-800-normal.woff2') format('woff2')}
:root{--ink:#0F172A;--ink2:#1E293B;--ink3:#334155;--muted:#64748B;--line:#E2E8F0;--paper:#F8FAFC;--amber:#F59E0B;--amberD:#B45309;--amberS:#FEF3C7;--green:#059669;--greenS:#D1FAE5;--rose:#E11D48;--roseS:#FFE4E6}
*{box-sizing:border-box;margin:0;padding:0}
body{background:#94a3b8;font-family:'Inter','Noto Sans Bengali',system-ui,sans-serif;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;gap:30px;align-items:flex-start;padding:20px}
.s{position:relative;overflow:hidden;display:flex;flex-direction:column;flex:none;font-family:'Inter','Noto Sans Bengali',sans-serif}
.s.f{width:1080px;height:1350px;padding:70px 72px 56px}
.s.dark{background:#0F172A;color:#fff}.s.light{background:#F8FAFC;color:#0F172A}.s.amber{background:#F59E0B;color:#0F172A}
.s.f::before{content:"";position:absolute;right:-230px;top:-230px;width:640px;height:640px;border-radius:50%;border:72px solid rgba(245,158,11,.11)}
.s.amber::before{border-color:rgba(255,255,255,.22)}
.s>*{position:relative}
.bn,.bn *{letter-spacing:0!important;text-transform:none!important}
.bn h2{line-height:1.3!important}
.hd{display:flex;justify-content:space-between;align-items:center}
.brand{display:flex;align-items:center;gap:12px;font-weight:800;font-size:28px;letter-spacing:-.02em}
.brand span{font-weight:600}
.pill{font-size:19px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:9px 18px;border-radius:999px;background:rgba(245,158,11,.16);color:#F59E0B}
.light .pill{background:#FEF3C7;color:#B45309}.amber .pill{background:rgba(15,23,42,.12);color:#0F172A}
.mk{display:block}
.dark .mk,.dark.mkd .mk{--t:#F59E0B;--r:#0F172A}
.amber .mk{--t:#0F172A;--r:#F59E0B}
h2.t{font-weight:800;letter-spacing:-.035em;line-height:1.04;margin-top:34px}
h2.t em{font-style:normal;color:#F59E0B}.light h2.t em{color:#B45309}.amber h2.t em{color:#fff}
.sub{font-size:30px;line-height:1.42;margin-top:20px;color:#94A3B8;font-weight:500}
.light .sub{color:#475569}.amber .sub{color:#451A03}
.body{flex:1;display:flex;flex-direction:column;justify-content:center;gap:18px;min-height:0}
.ft{display:flex;justify-content:space-between;align-items:center;font-size:23px;font-weight:700;padding-top:22px;border-top:2px solid rgba(148,163,184,.3);color:#94A3B8}
.light .ft{color:#64748B;border-color:#E2E8F0}.amber .ft{color:#0F172A;border-color:rgba(15,23,42,.25)}
.ft b{color:#F59E0B}.light .ft b{color:#B45309}.amber .ft b{color:#0F172A}
.ic{width:1em;height:1em;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.browser{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 28px 54px -20px rgba(2,6,23,.55),0 0 0 1px rgba(15,23,42,.12)}
.browser .bar{height:32px;background:#E8EDF3;display:flex;align-items:center;gap:7px;padding:0 14px;border-bottom:1px solid #D5DCE6}
.browser .bar i{width:10px;height:10px;border-radius:50%;display:block;background:#F87171}.browser .bar i:nth-child(2){background:#FBBF24}.browser .bar i:nth-child(3){background:#4ADE80}
.browser .bar span{margin-left:12px;height:20px;border-radius:10px;background:#fff;font-size:11px;line-height:20px;padding:0 12px;color:#64748B;font-weight:500}
.crop{position:relative;overflow:hidden;background:#fff}.crop img{position:absolute;display:block;max-width:none}
.phone{background:#0B1220;border-radius:44px;padding:11px;box-shadow:0 30px 56px -18px rgba(2,6,23,.6),0 0 0 2px #1E293B;position:relative}
.phone .scr{border-radius:34px;overflow:hidden;background:#fff;padding-top:20px}.phone img{display:block;width:100%}
.phone::before{content:"";position:absolute;top:15px;left:50%;transform:translateX(-50%);width:22%;height:10px;border-radius:6px;background:#0B1220;z-index:2}
.chips{display:flex;flex-wrap:wrap;gap:12px}
.chip{display:inline-flex;align-items:center;gap:10px;font-size:24px;font-weight:700;border-radius:999px;padding:12px 22px;background:rgba(245,158,11,.16);color:#F59E0B}
.light .chip{background:#fff;border:1.5px solid #E2E8F0;color:#0F172A}.amber .chip{background:rgba(15,23,42,.1);color:#0F172A}
.chip .ic{font-size:26px}
.tag{position:absolute;background:#0F172A;color:#fff;font-weight:700;font-size:22px;padding:10px 20px;border-radius:999px;box-shadow:0 10px 22px rgba(2,6,23,.4);z-index:3}
.dark .tag{background:#F59E0B;color:#0F172A}
.fwrap{position:relative;align-self:center}
.grid6{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.tile{border-radius:22px;padding:22px 24px;display:flex;align-items:center;gap:18px;font-size:27px;font-weight:700;line-height:1.2}
.dark .tile{background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.12)}.light .tile{background:#fff;border:1.5px solid #E2E8F0}.amber .tile{background:rgba(255,255,255,.35)}
.tile .ic{font-size:40px;color:#F59E0B}.light .tile .ic{color:#B45309}.amber .tile .ic{color:#0F172A}
.vsr{display:grid;grid-template-columns:1fr 54px 1fr;align-items:center}
.vsr .l,.vsr .r{border-radius:20px;padding:20px 24px;font-size:27px;font-weight:700;line-height:1.28;min-height:96px;display:flex;align-items:center}
.vsr .l{background:#FFE4E6;color:#9F1239}.vsr .r{background:#D1FAE5;color:#065F46}.vsr .a{text-align:center;font-size:34px;display:grid;place-items:center}
.vh{display:grid;grid-template-columns:1fr 54px 1fr;font-size:20px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.vh span:first-child{color:#E11D48}.vh span:last-child{color:#10B981}
.cl{display:flex;flex-direction:column;gap:14px}
.cli{display:flex;gap:22px;align-items:center;border-radius:22px;padding:15px 24px;font-size:31px;font-weight:700;line-height:1.25}
.dark .cli{background:rgba(255,255,255,.07)}.light .cli{background:#fff;border:1.5px solid #E2E8F0}.amber .cli{background:rgba(255,255,255,.4)}
.num{width:54px;height:54px;border-radius:50%;background:#F59E0B;color:#0F172A;font-weight:800;font-size:30px;display:grid;place-items:center;flex:none}
.amber .num{background:#0F172A;color:#F59E0B}
.opt{display:flex;gap:22px;align-items:center;border-radius:26px;padding:26px 30px;font-size:34px;font-weight:700;border:2px solid rgba(245,158,11,.5)}
.light .opt{border-color:#F59E0B;background:#fff}.amber .opt{border-color:#0F172A;background:rgba(255,255,255,.4)}
.opt b{width:58px;height:58px;border-radius:16px;background:#F59E0B;color:#0F172A;display:grid;place-items:center;font-size:30px;flex:none}.amber .opt b{background:#0F172A;color:#F59E0B}
.price{font-size:210px;font-weight:800;letter-spacing:-.06em;line-height:.88;color:#F59E0B}.light .price{color:#B45309}.amber .price{color:#0F172A}
.pr-u{font-size:36px;font-weight:700;line-height:1.25}
.pr-u s{display:block;font-size:34px;opacity:.65;font-weight:600}
.bar-cta{background:#F59E0B;color:#0F172A;border-radius:24px;padding:26px 34px;font-size:36px;font-weight:800;display:flex;align-items:center;justify-content:space-between}
.amber .bar-cta{background:#0F172A;color:#F59E0B}
.big{font-weight:800;letter-spacing:-.04em;line-height:1.02}
.big em{font-style:normal;color:#F59E0B}.light .big em{color:#B45309}.amber .big em{color:#fff}
.cnt{font-size:22px;font-weight:700;color:#94A3B8}.swipe{font-size:22px;font-weight:700;background:#F59E0B;color:#0F172A;border-radius:999px;padding:10px 20px;display:inline-flex;gap:8px;align-items:center}
.stepn{font-size:150px;font-weight:800;line-height:.9;color:#F59E0B;letter-spacing:-.05em}.light .stepn{color:#B45309}.amber .stepn{color:#0F172A}
.ctab{border-radius:30px;padding:36px 40px;display:flex;flex-direction:column;gap:20px}
.dark .ctab{background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.14)}
.cline{display:flex;align-items:center;gap:20px;font-size:32px;font-weight:700}.cline .ic{font-size:42px;color:#F59E0B}
/* assets */
.a{position:relative;overflow:hidden;flex:none;font-family:'Inter','Noto Sans Bengali',sans-serif;display:flex}
.a.dark{background:#0F172A;color:#fff}.a.light{background:#F8FAFC;color:#0F172A}.a.amber{background:#F59E0B;color:#0F172A}
.a>*{position:relative}
"""


def page(sections, bn_default=False, extra_css=''):
    return ('<!doctype html><html><head><meta charset="utf-8"><title>social</title><style>' + CSS + extra_css + '</style></head><body>'
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
    name = 'মোঃ সাকির আহমেদ' if bn else 'Md. Sakir Ahmed'
    role = 'ব্যবস্থাপনা পরিচালক, ডাটাটেক বিডি লিমিটেড' if bn else 'Managing Director, Datatech BD Ltd'
    wa = 'মোবাইল ও হোয়াটসঅ্যাপ' if bn else 'Mobile and WhatsApp'
    inner = (header(p['label']) + f'<h2 class="t" style="font-size:{fsize(plain(t), bn, (92, 84, 74, 64))}px">{em(t)}</h2><p class="sub">{sub}</p>'
             + f'<div class="body"><div class="ctab"><div style="font-size:42px;font-weight:800">{name}</div><div style="font-size:23px;color:#94A3B8;margin-top:-10px">{role}</div>'
             + f'<div class="cline">{ic("phone")}<span>8801304734623 <small style="font-size:22px;color:#94A3B8;font-weight:500">{wa}</small></span></div>'
             + f'<div class="cline">{ic("mail")}sakir.ahmed@datatechbd.com</div><div class="cline">{ic("globe")}dt-restora.datatechbd.com</div></div>'
             + f'<div class="bar-cta"><span>{"ফ্রি ডেমো দেখুন" if bn else "Try the live demo"}</span>{ic("arrow")}</div></div>' + footer(bn=bn))
    return wrap(pid, 'dark', bn, inner)


TPL = {'hero': t_hero, 'statement': t_statement, 'grid': t_grid, 'vs': t_vs, 'check': t_check, 'poll': t_poll, 'offer': t_offer}
