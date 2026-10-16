"""Second design family: pastel sticker cards with thick outlines, built around questions,
short highlighted solution points and small infographics."""
import math, html as _h
import common
from common import em, ic, mark, crop, bn_digits, plain, fsize

CSS2 = """
.q{width:1080px;height:1350px;padding:60px 64px 50px;color:#0F172A;font-family:'Inter','Noto Sans Bengali',sans-serif}
.q.sq{height:1080px}
.q.cream{background:#FFF1C9}.q.mint{background:#CFF5E3}.q.sky{background:#D6E8FF}.q.rose{background:#FFDDE2}.q.lilac{background:#E6E0FF}.q.white{background:#FFFFFF}.q.sun{background:#FBBF24}
.q::before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(15,23,42,.10) 2.5px,transparent 2.5px);background-size:34px 34px;border:0;width:auto;height:auto;border-radius:0;right:auto;top:0}
.q>*{position:relative;z-index:1}
.q .top{display:flex;justify-content:space-between;align-items:center}
.q .logo{display:flex;align-items:center;gap:12px;background:#fff;border:4px solid #0F172A;border-radius:20px;padding:8px 18px 8px 10px;font-weight:800;font-size:27px;box-shadow:5px 5px 0 #0F172A;letter-spacing:-.02em}
.q .logo span{font-weight:600}.q .logo svg{--t:#0F172A;--r:#F59E0B}
.q .lab{background:#0F172A;color:#fff;border-radius:999px;padding:11px 24px;font-size:22px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.q .bubble{background:#fff;border:5px solid #0F172A;border-radius:40px;padding:30px 40px;box-shadow:10px 10px 0 #0F172A;margin-top:36px;position:relative}
.q .bubble::after{content:"";position:absolute;left:90px;bottom:-27px;width:44px;height:44px;background:#fff;border-right:5px solid #0F172A;border-bottom:5px solid #0F172A;transform:rotate(45deg)}
.q .qt{font-weight:800;line-height:1.1;letter-spacing:-.03em}
.q .qt em{font-style:normal;background:linear-gradient(transparent 58%,#FBBF24 58%);padding:0 6px}
.q.sun .qt em{background:linear-gradient(transparent 58%,#fff 58%)}
.q .ans{margin-top:30px;font-size:25px;font-weight:700;line-height:1.35;color:#334155;padding-left:8px}
.q .fix{margin-top:30px;display:flex;align-items:center;gap:14px;font-size:24px;font-weight:800;text-transform:uppercase;letter-spacing:.12em}
.q .fix::after{content:"";flex:1;border-top:4px dashed #0F172A;opacity:.5}
.q .fix .ic{font-size:30px}
.q .pts{display:flex;flex-direction:column;gap:16px;margin-top:18px}
.q .pt{display:flex;align-items:center;gap:22px;background:#fff;border:4px solid #0F172A;border-radius:26px;padding:20px 26px;font-size:32px;font-weight:700;line-height:1.22;box-shadow:7px 7px 0 #0F172A}
.q .pt:nth-child(2){transform:rotate(-.6deg)}.q .pt:nth-child(3){transform:rotate(.5deg)}
.q .pt b{width:58px;height:58px;border-radius:50%;border:4px solid #0F172A;display:grid;place-items:center;font-size:28px;font-weight:800;flex:none}
.q .pt:nth-child(1) b{background:#F59E0B}.q .pt:nth-child(2) b{background:#4ADE80}.q .pt:nth-child(3) b{background:#60A5FA}.q .pt:nth-child(4) b{background:#F472B6}
.q .bot{display:flex;gap:22px;align-items:flex-end;margin-top:auto;padding-top:22px}
.q .mini{flex:1;border:4px dashed #0F172A;border-radius:28px;padding:18px 22px;background:rgba(255,255,255,.65);display:flex;align-items:center;justify-content:space-around;gap:6px}
.q .mn{display:flex;flex-direction:column;align-items:center;gap:8px;font-size:19px;font-weight:800;text-align:center;line-height:1.15;max-width:130px}
.q .mn i{width:68px;height:68px;border-radius:50%;border:4px solid #0F172A;display:grid;place-items:center;font-style:normal;font-size:34px}
.q .mn:nth-of-type(1) i{background:#FBBF24}.q .mn:nth-of-type(3) i{background:#4ADE80}.q .mn:nth-of-type(5) i{background:#60A5FA}.q .mn:nth-of-type(7) i{background:#F472B6}
.q .ar{font-size:40px;font-weight:800;line-height:1;margin-top:-26px}
.q .stk{flex:none;border:4px solid #0F172A;border-radius:18px;overflow:hidden;box-shadow:7px 7px 0 #0F172A;background:#fff;transform:rotate(-3deg)}
.q .stk .cap{background:#0F172A;color:#fff;font-size:15px;font-weight:700;padding:5px 12px;letter-spacing:.06em;text-transform:uppercase}
.q .foot{display:flex;justify-content:space-between;align-items:center;margin-top:20px}
.q .foot .t{font-size:22px;font-weight:700;color:#334155}
.q .foot .u{background:#F59E0B;border:4px solid #0F172A;border-radius:999px;padding:10px 24px;font-size:24px;font-weight:800;box-shadow:5px 5px 0 #0F172A}
.q.sun .foot .u{background:#fff}
.q .stamp{display:inline-block;border:5px solid #0F172A;border-radius:14px;padding:6px 20px;font-size:34px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;transform:rotate(-4deg);background:#fff}
.q .opt{display:flex;align-items:center;gap:22px;background:#fff;border:4px solid #0F172A;border-radius:24px;padding:18px 24px;font-size:31px;font-weight:700;line-height:1.2;box-shadow:6px 6px 0 #0F172A}
.q .opt b{width:56px;height:56px;border-radius:16px;border:4px solid #0F172A;background:#FBBF24;display:grid;place-items:center;font-size:28px;font-weight:800;flex:none}
.q .opt.ok{background:#86EFAC}.q .opt.ok b{background:#22C55E}
.q .opt .tick{margin-left:auto;font-size:26px;font-weight:800;background:#0F172A;color:#fff;border-radius:999px;padding:6px 16px;white-space:nowrap}
.q .why{background:#FFF;border:4px solid #0F172A;border-radius:22px;padding:20px 26px;font-size:27px;font-weight:600;line-height:1.35;transform:rotate(-.8deg);box-shadow:6px 6px 0 #0F172A;margin-top:6px}
.q .why strong{background:#FBBF24;padding:2px 10px;border-radius:8px;margin-right:6px}
.q .chips2{display:flex;flex-wrap:wrap;gap:12px;margin-top:6px}
.q .c2{display:inline-flex;align-items:center;gap:10px;background:#fff;border:3.5px solid #0F172A;border-radius:999px;padding:10px 22px;font-size:25px;font-weight:700;box-shadow:4px 4px 0 #0F172A}
.q .c2 .ic{font-size:26px}
.q .mf{border:5px solid #0F172A;border-radius:36px;padding:34px 40px;box-shadow:9px 9px 0 #0F172A;position:relative}
.q .mf.m{background:#FFB4BE}.q .mf.f{background:#86EFAC}
.q .mf .tx{font-size:54px;font-weight:800;line-height:1.14;letter-spacing:-.03em;margin-top:14px}
.q .mf.m .tx{text-decoration:line-through;text-decoration-thickness:6px;text-decoration-color:rgba(15,23,42,.55)}
.q .chat{background:#fff;border:5px solid #0F172A;border-radius:40px;box-shadow:10px 10px 0 #0F172A;overflow:hidden;margin-top:30px}
.q .chat .hdr{background:#0F172A;color:#fff;display:flex;gap:16px;align-items:center;padding:16px 26px}
.q .chat .av{width:54px;height:54px;border-radius:50%;background:#F59E0B;display:grid;place-items:center}.q .chat .av svg{--t:#F59E0B;--r:#0F172A}
.q .chat .hdr b{font-size:26px;display:block}.q .chat .hdr small{font-size:18px;color:#86EFAC;font-weight:600}
.q .chat .msgs{padding:24px 26px;display:flex;flex-direction:column;gap:16px;background:#F1F5F9}
.q .bb{max-width:80%;border:4px solid #0F172A;border-radius:26px;padding:16px 22px;font-size:29px;font-weight:600;line-height:1.3}
.q .bb.q1{align-self:flex-start;background:#fff;border-bottom-left-radius:6px}.q .bb.a1{align-self:flex-end;background:#BBF7D0;border-bottom-right-radius:6px}
.q .bb small{display:block;font-size:17px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;opacity:.6;margin-bottom:4px}
.q .rcpt{background:#fff;border:4px solid #0F172A;box-shadow:9px 9px 0 #0F172A;padding:30px 34px 44px;margin-top:30px;position:relative;clip-path:polygon(0 0,100% 0,100% calc(100% - 18px),95% 100%,90% calc(100% - 18px),85% 100%,80% calc(100% - 18px),75% 100%,70% calc(100% - 18px),65% 100%,60% calc(100% - 18px),55% 100%,50% calc(100% - 18px),45% 100%,40% calc(100% - 18px),35% 100%,30% calc(100% - 18px),25% 100%,20% calc(100% - 18px),15% 100%,10% calc(100% - 18px),5% 100%,0 calc(100% - 18px))}
.q .rr{display:flex;align-items:center;gap:16px;padding:14px 0;border-bottom:3px dotted #94A3B8;font-size:31px;font-weight:800}
.q .rr .op{width:46px;height:46px;border-radius:12px;border:3.5px solid #0F172A;background:#FBBF24;display:grid;place-items:center;font-size:30px;flex:none}
.q .rr.m .op{background:#FDA4AF}.q .rr.p .op{background:#86EFAC}
.q .rr small{margin-left:auto;font-size:21px;font-weight:600;color:#475569;text-align:right}
.q .rr.res{border:0;background:#0F172A;color:#FBBF24;border-radius:16px;padding:18px 20px;margin-top:14px;font-size:38px}
.q .rr.res small{color:#CBD5E1}.q .rr.res .op{background:#FBBF24}
.q .cyc{position:relative;width:952px;height:560px;margin:18px auto 0}
.q .cyc .ring{position:absolute;left:150px;top:55px;width:650px;height:400px;border:5px dashed #0F172A;border-radius:50%;opacity:.45}
.q .cyc .ctr{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:230px;height:230px;border-radius:50%;background:#0F172A;color:#FBBF24;border:5px solid #0F172A;display:grid;place-items:center;text-align:center;font-size:30px;font-weight:800;padding:26px;line-height:1.2}
.q .cyc .nd{position:absolute;transform:translate(-50%,-50%);background:#fff;border:4px solid #0F172A;border-radius:24px;padding:14px 20px;font-size:26px;font-weight:800;box-shadow:6px 6px 0 #0F172A;display:flex;align-items:center;gap:12px;white-space:nowrap}
.q .cyc .nd .ic{font-size:36px}
.q .cyc .nd .n{width:36px;height:36px;border-radius:50%;background:#FBBF24;border:3px solid #0F172A;display:grid;place-items:center;font-size:20px}
.q .tl{position:relative;margin-top:24px;display:flex;flex-direction:column;gap:20px;padding-left:40px}
.q .tl::before{content:"";position:absolute;left:19px;top:20px;bottom:20px;border-left:6px dashed #0F172A;opacity:.5}
.q .ti{position:relative;background:#fff;border:4px solid #0F172A;border-radius:26px;padding:20px 26px;box-shadow:7px 7px 0 #0F172A;display:flex;gap:20px;align-items:center}
.q .ti::before{content:attr(data-n);position:absolute;left:-58px;top:50%;margin-top:-23px;width:46px;height:46px;border-radius:50%;background:#FBBF24;border:4px solid #0F172A;display:grid;place-items:center;font-weight:800;font-size:23px}
.q .ti .ic{font-size:48px;flex:none}
.q .ti b{font-size:32px;display:block}.q .ti span{font-size:24px;font-weight:600;color:#475569;line-height:1.3;display:block}
.q .cmp{background:#fff;border:5px solid #0F172A;border-radius:34px;box-shadow:9px 9px 0 #0F172A;margin-top:28px;overflow:hidden}
.q .cr{display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;align-items:center;border-top:3px solid #0F172A;font-size:27px;font-weight:700}
.q .cr:first-child{border-top:0;background:#F1F5F9;font-size:23px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
.q .cr>div{padding:18px 16px;text-align:center}.q .cr>div:first-child{text-align:left;padding-left:26px}
.q .cr>div:last-child{background:#FEF3C7;border-left:3px solid #0F172A}
.q .cr:first-child>div:last-child{background:#FBBF24}
.q .yes,.q .no{width:44px;height:44px;border-radius:50%;border:3.5px solid #0F172A;display:inline-grid;place-items:center;font-size:26px}
.q .yes{background:#4ADE80}.q .no{background:#FDA4AF}
.bn.q .qt{letter-spacing:0}
.q.tight .rr{padding:8px 0;font-size:28px}.q.tight .rr.res{font-size:34px;padding:14px 18px}.q.tight .ti{padding:13px 22px}.q.tight .tl{gap:13px;margin-top:20px}.q.tight .rcpt{padding:20px 30px 38px;margin-top:22px}
.q.tight .bubble{padding:24px 36px;margin-top:28px}.q.tight .qt{font-size:58px!important}.q.tight .ti .ic{font-size:40px}.q.tight .ti b{font-size:29px}.q.tight .ti span{font-size:22px}.q.tight .rr small{font-size:19px}
.q.tightq .opt{padding:11px 22px;font-size:29px}.q.tightq .opt b{width:50px;height:50px}.q.tightq .why{font-size:25px;padding:14px 22px}.q.tightq .pts{gap:12px!important}


.q .ans{font-size:28px;margin-top:28px}
.q .pt{font-size:34px;padding:20px 26px}.q .pt b{width:60px;height:60px;font-size:29px}
.q .pts{gap:16px}
.q .mn{font-size:20px;max-width:140px}.q .mn i{width:70px;height:70px;font-size:35px}
.q .c2{font-size:26px;padding:10px 22px}
.q .opt{font-size:33px;padding:16px 26px}.q .opt b{width:56px;height:56px;font-size:29px}
.q .why{font-size:28px;padding:18px 28px}
.q .bb{font-size:31px;padding:15px 24px;max-width:88%}.q .chat .msgs{gap:18px;padding:24px 26px}.q .bb small{font-size:19px}
.q .chat .hdr b{font-size:30px}.q .chat .hdr{padding:20px 28px}
.q .rr{font-size:34px;padding:15px 0}.q .rr small{font-size:22px}.q .rr .op{width:50px;height:50px;font-size:32px}.q .rr.res{font-size:40px;padding:18px 22px}
.q .cr{font-size:29px}.q .cr>div{padding:15px 14px}.q .cr:first-child{font-size:25px}.q .yes,.q .no{width:52px;height:52px;font-size:30px}
.q .mf .tx{font-size:58px}
.q.tight .rr{padding:8px 0;font-size:28px}.q.tight .rr.res{font-size:34px;padding:14px 18px}.q.tight .rr small{font-size:19px}.q.tight .rr .op{width:46px;height:46px;font-size:30px}.q.tight .ti{padding:13px 22px}
.q>*{flex:none}
.q .cyc{height:520px}
.q .cyc .nd{font-size:28px;padding:16px 22px}
.q .stk .cap{font-size:17px}
.bn.q .qt{letter-spacing:0}
"""
common.CSS += CSS2


def top(p, label):
    return f'<div class="top"><div class="logo">{mark(40)}<div>DT <span>Restora</span></div></div><div class="lab">{_h.escape(label)}</div></div>'


def foot(bn):
    t = 'বাংলাদেশের রেস্টুরেন্ট ও ক্যাফের জন্য' if bn else 'Restaurant software for Bangladesh'
    return f'<div class="foot"><div class="t">{t}</div><div class="u">dt-restora.datatechbd.com</div></div>'


def sticker(shot, w=330, h=200, cap='From the software'):
    if not shot:
        return ''
    return f'<div class="stk" style="width:{w}px"><div class="cap">{_h.escape(cap)}</div>{crop(shot, w - 8, h)}</div>'


def mini(spec):
    if not spec:
        return ''
    kind, nodes = spec
    parts = []
    for i, (icn, lab) in enumerate(nodes):
        parts.append(f'<div class="mn"><i>{ic(icn)}</i><span>{_h.escape(lab)}</span></div>')
        if i < len(nodes) - 1:
            parts.append('<div class="ar">&rarr;</div>')
    return f'<div class="mini">{"".join(parts)}</div>'


def wrapq(pid, p, bn, inner, sq=False, extra=''):
    bg = p.get('bg', 'cream')
    return f'<section class="s q {bg}{" sq" if sq else ""}{" bn" if bn else ""}{extra}" id="{pid}">{inner}</section>'


def qfs(text, bn, base=(84, 74, 66, 58)):
    n = len(plain(text)) * (1.2 if bn else 1)
    s = base[0] if n <= 30 else base[1] if n <= 48 else base[2] if n <= 70 else base[3]
    return int(s * (0.85 if bn else 1))


def t_qa(p, pid, bn):
    pts = ''.join(f'<div class="pt"><b>{bn_digits(str(i + 1)) if bn else i + 1}</b><span>{_h.escape(b)}</span></div>' for i, b in enumerate(p['bullets'][:4]))
    fix = 'সমাধান' if bn else 'The fix'
    inner = (top(p, p['label']) + f'<div class="bubble"><div class="qt" style="font-size:{qfs(p["title"], bn)}px">{em(p["title"])}</div></div>'
             + f'<div class="ans">{em(p["sub"])}</div><div class="fix">{ic("arrow")}{fix}</div><div class="pts">{pts}</div>'
             + f'<div class="bot">{mini(p.get("mini"))}{sticker(p.get("shot"), cap=p.get("cap", "In DT Restora"))}</div>' + foot(bn))
    return wrapq(pid, p, bn, inner)


def t_quiz(p, pid, bn):
    letters = 'কখগঘ' if bn else 'ABCD'
    opts = ''
    for i, o in enumerate(p['options'][:4]):
        ok = i == p['answer']
        opts += f'<div class="opt{" ok" if ok else ""}"><b>{letters[i]}</b><span>{_h.escape(o)}</span>{"<span class=tick>" + ("সঠিক" if bn else "Correct") + "</span>" if ok else ""}</div>'
    chips = ''.join(f'<div class="c2">{ic("check")}{_h.escape(b)}</div>' for b in p['bullets'][:3])
    why = 'কেন?' if bn else 'Why?'
    stamp = 'কুইজ' if bn else 'Quiz'
    inner = (top(p, p['label']) + f'<div style="margin-top:30px"><span class="stamp">{stamp}</span></div>'
             + f'<div class="qt" style="font-size:{qfs(p["title"], bn, (68, 60, 54, 48))}px;margin-top:22px">{em(p["title"])}</div>'
             + f'<div class="pts" style="margin-top:26px">{opts.replace("pt", "pt")}</div>'
             + f'<div class="why"><strong>{why}</strong>{_h.escape(p["why"])}</div><div class="chips2">{chips}</div>'
             + f'<div class="bot" style="margin-top:auto">{sticker(p.get("shot"), 300, 90, cap=p.get("cap", "In DT Restora"))}</div>' + foot(bn))
    return wrapq(pid, p, bn, inner)


def t_myth(p, pid, bn):
    chips = ''.join(f'<div class="c2">{ic("check")}{_h.escape(b)}</div>' for b in p['bullets'][:3])
    ml, fl = ('ভুল ধারণা', 'সত্য') if bn else ('Myth', 'Fact')
    fs = lambda t: int(58 * (0.86 if bn else 1)) if len(plain(t)) < 55 else int(50 * (0.86 if bn else 1))
    inner = (top(p, p['label'])
             + f'<div class="mf m" style="margin-top:34px"><span class="stamp" style="transform:rotate(-3deg);background:#fff">{ml}</span><div class="tx" style="font-size:{fs(p["myth"])}px">{em(p["myth"])}</div></div>'
             + f'<div class="mf f" style="margin-top:34px"><span class="stamp" style="transform:rotate(2deg);background:#fff">{fl}</span><div class="tx" style="font-size:{fs(p["fact"])}px">{em(p["fact"])}</div></div>'
             + f'<div class="chips2" style="margin-top:28px">{chips}</div><div class="bot">{sticker(p.get("shot"), 440, 170, cap=p.get("cap", "In DT Restora"))}</div>' + foot(bn))
    return wrapq(pid, p, bn, inner)


def t_chat(p, pid, bn):
    who = 'রেস্টুরেন্ট মালিক' if bn else 'Restaurant owner'
    us = 'DT Restora টিম' if bn else 'DT Restora team'
    msgs = ''
    for kind, text in p['chat']:
        if kind == 'q':
            msgs += f'<div class="bb q1"><small>{who}</small>{_h.escape(text)}</div>'
        else:
            msgs += f'<div class="bb a1"><small>{us}</small>{_h.escape(text)}</div>'
    chips = ''.join(f'<div class="c2">{ic("check")}{_h.escape(b)}</div>' for b in p['bullets'][:3])
    online = 'অনলাইন' if bn else 'online'
    inner = (top(p, p['label']) + f'<div class="chat"><div class="hdr"><div class="av">{mark(34)}</div><div><b>DT Restora</b><small>{online}</small></div></div><div class="msgs">{msgs}</div></div>'
             + f'<div class="chips2" style="margin-top:26px">{chips}</div><div class="bot">{sticker(p.get("shot"), 440, 110, cap=p.get("cap", "In DT Restora"))}</div>' + foot(bn))
    return wrapq(pid, p, bn, inner)


def t_formula(p, pid, bn):
    rows = ''
    for lab, note, op in p['parts']:
        cls = 'p' if op == '+' else 'm' if op in '-−' else ''
        rows += f'<div class="rr {cls}"><div class="op">{op}</div><span>{_h.escape(lab)}</span><small>{_h.escape(note)}</small></div>'
    rl, rn = p['result']
    rows += f'<div class="rr res"><div class="op">=</div><span>{_h.escape(rl)}</span><small>{_h.escape(rn)}</small></div>'
    chips = ''.join(f'<div class="c2">{ic("check")}{_h.escape(b)}</div>' for b in p['bullets'][:3])
    inner = (top(p, p['label']) + f'<div class="bubble"><div class="qt" style="font-size:{qfs(p["title"], bn, (68, 60, 54, 48))}px">{em(p["title"])}</div></div>'
             + f'<div class="rcpt">{rows}</div><div class="chips2" style="margin-top:22px">{chips}</div>' + ('' if len(p['parts']) >= 5 else f'<div class="bot">{sticker(p.get("shot"), 330, 170)}</div>') + foot(bn))
    return wrapq(pid, p, bn, inner, extra=' tight' if len(p['parts']) >= 5 else '')


def t_cycle(p, pid, bn):
    nodes = p['nodes']
    n = len(nodes)
    cx, cy, rx, ry = 476, 255, 360, 185
    html = '<div class="cyc"><div class="ring"></div>'
    for i, (icn, lab) in enumerate(nodes):
        a = -math.pi / 2 + 2 * math.pi * i / n
        x, y = cx + rx * math.cos(a), cy + ry * math.sin(a)
        html += f'<div class="nd" style="left:{x:.0f}px;top:{y:.0f}px"><span class="n">{bn_digits(str(i + 1)) if bn else i + 1}</span>{ic(icn)}<span>{_h.escape(lab)}</span></div>'
    html += f'<div class="ctr">{_h.escape(p["center"])}</div></div>'
    chips = ''.join(f'<div class="c2">{ic("check")}{_h.escape(b)}</div>' for b in p['bullets'][:3])
    inner = (top(p, p['label']) + f'<div class="bubble"><div class="qt" style="font-size:{qfs(p["title"], bn)}px">{em(p["title"])}</div></div>' + html
             + f'<div class="chips2" style="margin-top:14px">{chips}</div>' + foot(bn))
    return wrapq(pid, p, bn, inner)


def t_timeline(p, pid, bn):
    items = ''.join(f'<div class="ti" data-n="{bn_digits(str(i + 1)) if bn else i + 1}">{ic(icn)}<div><b>{_h.escape(h)}</b><span>{_h.escape(t)}</span></div></div>' for i, (icn, h, t) in enumerate(p['steps'][:5]))
    chips = ''.join(f'<div class="c2">{ic("check")}{_h.escape(b)}</div>' for b in p['bullets'][:3])
    inner = (top(p, p['label']) + f'<div class="bubble"><div class="qt" style="font-size:{qfs(p["title"], bn)}px">{em(p["title"])}</div></div><div class="tl">{items}</div>'
             + f'<div class="chips2" style="margin-top:22px">{chips}</div>' + foot(bn))
    return wrapq(pid, p, bn, inner, extra=' tight')


def t_compare(p, pid, bn):
    cols = p['cols']
    head = '<div class="cr"><div></div>' + ''.join(f'<div>{_h.escape(c)}</div>' for c in cols) + '</div>'
    body = ''
    for lab, vals in p['rows'][:6]:
        cells = ''.join(f'<div><span class="{"yes" if v else "no"}">{"&#10003;" if v else "&#10005;"}</span></div>' for v in vals)
        body += f'<div class="cr"><div>{_h.escape(lab)}</div>{cells}</div>'
    chips = ''.join(f'<div class="c2">{ic("check")}{_h.escape(b)}</div>' for b in p['bullets'][:3])
    inner = (top(p, p['label']) + f'<div class="bubble"><div class="qt" style="font-size:{qfs(p["title"], bn)}px">{em(p["title"])}</div></div><div class="cmp">{head}{body}</div>'
             + f'<div class="chips2" style="margin-top:24px">{chips}</div>' + foot(bn))
    return wrapq(pid, p, bn, inner)


TPL2 = {'qa': t_qa, 'quiz': t_quiz, 'myth': t_myth, 'chat': t_chat, 'formula': t_formula, 'cycle': t_cycle, 'timeline': t_timeline, 'compare': t_compare}
