"""Builds docs/marketing/book/v2/dt-restora-product-book-bn.html (A4, Bangla, Datatech brand).

Run: python3 docs/marketing/book/v2/build.py
"""
import os
from lib import *

PAGES = []  # (title, html)
URL = 'dt-restora.datatechbd.com'
PHONE = '+৮৮০ ১৫৭৫-৮৮৮৭৭৫'
EMAIL = 'sales@datatechbd.com'
ADDR = 'ফ্ল্যাট ৪-এ, ধূমকেতু শিকদার টাওয়ার, হোল্ডিং নং ৬৬, পশ্চিম মনিপুর, মিরপুর, ঢাকা-১২১৬, বাংলাদেশ'


def top(label, dark=False):
    return (f'<div class="top"><div class="lg">{mark(26)}<span>DT <b>Restora</b></span></div><span>{label}</span></div>')


def foot():
    return f'<div class="foot"><span>ডাটাটেক বিডি লিমিটেড · {PHONE} · {EMAIL}</span><span>{{N}}</span></div>'


def head(kick, h1, lead=''):
    return f'<div class="kick">{kick}</div><h1>{h1}</h1>' + (f'<p class="lead">{lead}</p>' if lead else '')


def add(title, label, body, dark=False, nohead=False):
    cls = 'pg dark' if dark else 'pg'
    html = f'<section class="{cls}">' + ('' if nohead else top(label)) + body + ('' if nohead else foot()) + '</section>'
    PAGES.append((title, html))


def feature(title, label, kick, h1, lead, stage_html, stage_h, items, co, cols=2):
    body = (head(kick, h1, lead)
            + stage(stage_html, 698, stage_h, 'margin-top:14px')
            + f'<div style="margin-top:12px;position:relative">{bullets(items, cols)}</div>'
            + f'<div style="margin-top:12px;position:relative">{co}</div>')
    add(title, label, body)


def trio(n1, n2, n3, chip1, chip2, hh=700):
    return (card(n1, 275, 60, 1525, hh, 540, style='left:0;top:8px')
            + card(n2, 275, 60, 1525, hh, 480, style='left:218px;top:150px;transform:rotate(2deg)')
            + card(n3, 275, 60, 1525, hh, 400, style='left:22px;top:292px;transform:rotate(-2deg)')
            + chip(chip1[0], chip1[1], 'left:200px;top:0px')
            + chip(chip2[0], chip2[1], 'left:450px;top:440px', chip2[2] if len(chip2) > 2 else 's'))


# ---------------------------------------------------------------- 1 cover
cover = f'''<section class="pg dark" style="padding:0">
<div class="abs" style="left:-140px;top:380px;width:560px;height:560px;border-radius:50%;border:2px solid rgba(56,168,255,.22)"></div>
<div class="abs" style="right:-200px;top:520px;width:640px;height:640px;border-radius:50%;border:2px solid rgba(56,168,255,.16)"></div>
<div class="corner tl"></div><div class="corner br"></div>
<div class="abs" style="left:56px;top:54px;display:flex;align-items:center;gap:12px">{mark(46)}<div style="font-family:Poppins;font-weight:600;font-size:26px;line-height:1">DT <span style="color:#38A8FF">Restora</span><div style="font-family:'Hind Siliguri';font-weight:500;font-size:12px;color:#9FB6D6;margin-top:3px;letter-spacing:.02em">রেস্টুরেন্ট মালিকের নিয়ন্ত্রণ ব্যবস্থা</div></div></div>
<div class="abs" style="right:56px;top:62px;font-size:13px;font-weight:600;color:#9FB6D6;border:1.5px solid rgba(255,255,255,.2);border-radius:999px;padding:5px 16px">প্রোডাক্ট বুক</div>
<div class="abs" style="left:56px;top:150px;width:690px">
<div style="font-size:15px;font-weight:700;color:#38A8FF;letter-spacing:.03em">বাংলাদেশের রেস্টুরেন্ট ও ক্যাফের জন্য</div>
<div style="font-size:54px;line-height:1.18;font-weight:700;margin-top:10px;letter-spacing:-.01em">আপনার রেস্টুরেন্টের <span style="color:#38A8FF">আসল লাভ,</span><br>প্রতিদিন আপনার হাতে</div>
<p class="lead" style="margin-top:16px;font-size:17px;max-width:600px;color:#C4D3EA">পিওএস, কিচেন, স্টক, কর্মী ও বেতন, লয়্যালটি, ডেলিভারি, ওয়েবসাইট ও হিসাব, সব একটি সিস্টেমে, একটি লগইনে।</p></div>
<div class="abs" style="left:0;top:430px;width:794px;height:520px">
{card('admin-02-dashboard', 0, 0, 1800, 1100, 560, style='left:48px;top:40px;transform:rotate(-3deg)')}
{card('admin-61-pos-items-added', 0, 0, 1800, 1037, 470, style='left:250px;top:230px;transform:rotate(2.5deg)')}
{phone('mobile-03-menu', 168, 0, 1150, style='left:582px;top:20px;transform:rotate(4deg)')}
{chip('arrow-trend-up', 'প্রতিদিনের নিট লাভ', 'left:36px;top:6px')}
{chip('cash-register', 'টাচ পিওএস', 'left:300px;top:468px', 's')}
{chip('qrcode', 'কিউআর অর্ডার', 'left:600px;top:380px', 'g')}
</div>
<div class="abs" style="left:56px;right:56px;bottom:56px;border:1.5px solid rgba(56,168,255,.45);border-radius:20px;padding:18px 26px;display:flex;justify-content:space-between;align-items:center;background:rgba(10,20,40,.35)">
<div><div style="font-size:12.5px;color:#9FB6D6">ছাড়কৃত মাসিক মূল্য</div><div style="font-size:34px;font-weight:700;line-height:1.15;color:#38A8FF">১,০০০ টাকা <span style="font-size:15px;color:#C4D3EA;font-weight:500">প্রতি মাসে</span></div></div>
<div style="text-align:right"><span class="pill" style="font-size:13px">প্রথম মাস ফ্রি</span><div style="font-size:13px;color:#C4D3EA;margin-top:6px">লাইভ ডেমো: {URL}</div></div></div>
</section>'''
PAGES.append(('কভার', cover))

# ---------------------------------------------------------------- 2 at a glance
tiles = [('layer-group', '২৪টি', 'টুল এক সিস্টেমে'), ('rocket', '৫ ধাপে', 'নিজের রেস্টুরেন্টে চালু'), ('hand-holding-dollar', '০ টাকা', 'প্রথম মাসের মাসিক ফি'),
         ('language', '২ ভাষা', 'বাংলা ও ইংরেজি'), ('wifi', 'অফলাইনেও', 'কাউন্টারে বিক্রি চলে'), ('database', 'প্রতিদিন', 'ব্যাকআপ, তথ্য আপনার')]
t_html = ''.join(f'<div class="bl" style="flex-direction:column;align-items:flex-start;gap:8px;padding:18px"><div class="bi" style="width:44px;height:44px;font-size:21px;border-radius:13px">{ic(i)}</div><div style="font-size:30px;font-weight:700;line-height:1.1;color:var(--blue)">{a}</div><p style="font-size:13.5px">{b}</p></div>' for i, a, b in tiles)
q = [('১', 'আমার ব্যবসার জন্য এটা কেন দরকার?', 'কারণ খাতা আর আন্দাজ আপনার টাকা কোথায় যাচ্ছে তা আড়াল করে রাখে।', 'পাতা ৩–৪'),
     ('২', 'এটা আমাকে কীভাবে লাভ বাড়াতে সাহায্য করবে?', 'বিক্রি বাড়ায়, অপচয় কমায়, সময় বাঁচায়, আর প্রতিদিন আসল লাভ দেখায়।', 'পাতা ৫–২০'),
     ('৩', 'কীভাবে শুরু করব, খরচ কত?', 'পাঁচটি সহজ ধাপ। প্রথম মাস ফ্রি, তারপর মাসে ১,০০০ টাকা।', 'পাতা ২৩–২৪')]
q_html = ''.join(f'<div class="bl" style="align-items:center;padding:14px 16px"><div style="width:40px;height:40px;border-radius:50%;background:var(--blue);color:#fff;display:grid;place-items:center;font-weight:700;font-size:20px;flex:none">{n}</div><div style="flex:1"><h4>{a}</h4><p>{b}</p></div><span class="pill">{c}</span></div>' for n, a, b, c in q)
add('এক নজরে', 'এক নজরে',
    head('আগে এই পাতাটি পড়ুন', 'একটি সিস্টেম, <em>পুরো রেস্টুরেন্ট</em>', 'হাতে দুই মিনিট থাকলে এই পাতাই যথেষ্ট। এই বই মালিকের তিনটি প্রশ্নের সহজ উত্তর দেয়।')
    + f'<div class="bls c3" style="margin-top:22px">{t_html}</div>'
    + f'<div class="bls c1" style="margin-top:20px">{q_html}</div>'
    + f'<div style="margin-top:20px">{callout("handshake", "আমাদের সহজ প্রতিশ্রুতি", "কী বিক্রি হলো, কত খরচ হলো, কী নষ্ট হলো আর হাতে কত থাকল, সবই সবসময় আপনি জানবেন। মাঝরাতে স্প্রেডশিট নয়, মাস শেষে চমকও নয়।")}</div>'
    + stage(card('admin-06-kitchen-display', 275, 60, 1525, 520, 300, style='left:0;top:8px;transform:rotate(-2deg)') + card('admin-02-dashboard', 275, 60, 1525, 520, 330, style='left:184px;top:0;transform:rotate(1deg)') + card('admin-28-reports', 275, 60, 1525, 520, 300, style='left:398px;top:10px;transform:rotate(3deg)'), 698, 150, 'margin-top:16px'))

# ---------------------------------------------------------------- 3 problem
leaks = [('trash-can', 'যে অপচয়ের হিসাব নেই', 'নষ্ট, পড়ে যাওয়া বা এমনি দিয়ে দেওয়া খাবার চিহ্ন ছাড়াই হারায়।'),
         ('boxes-stacked', 'আন্দাজের স্টক', 'উপকরণ ফুরোলে তবেই জানা যায়, নয়তো বেশি কিনে ফেলা হয়।'),
         ('receipt', 'বিল আর ক্যাশ মেলে না', 'হাতে লেখা বিল ও অনুমোদন ছাড়া ছাড় পরে খুঁজে পাওয়া কঠিন।'),
         ('hourglass-half', 'লাভ জানা যায় দেরিতে', 'মাস শেষে হিসাব বসলে তখন আর কিছু করার থাকে না।'),
         ('truck-fast', 'ডেলিভারি অ্যাপের কমিশন', 'কমিশন কেটে আসল লাভ কত, বেশিরভাগ মালিক জানেন না।'),
         ('user-clock', 'কর্মীর হাজিরা ও বেতন', 'কাগজের খাতায় ভুল হয়, আর বিরোধ লেগেই থাকে।')]
l_html = ''.join(f'<div class="bl" style="padding:17px 18px;align-items:center"><div class="bi" style="background:rgba(229,72,77,.22);color:#FF8A8E;width:40px;height:40px;font-size:18px">{ic(i)}</div><div><h4>{a}</h4><p>{b}</p></div></div>' for i, a, b in leaks)
add('সমস্যা', 'কেন দরকার',
    head('কেন দরকার', 'আপনার রেস্টুরেন্ট টাকা হারাচ্ছে এমন জায়গায়, <em>যা আপনি দেখতে পান না</em>', 'ব্যস্ত রান্নাঘর সাধারণত খাবার খারাপ বলে ডোবে না। ডোবে, কারণ ছোট ছোট ফুটো মাসের পর মাস কারও চোখে পড়ে না।')
    + f'<div class="bls c1" style="margin-top:24px">{l_html}</div>'
    + f'<div class="abs" style="left:48px;right:48px;bottom:84px;border-radius:22px;padding:26px 30px;background:linear-gradient(135deg,#0B63CE,#1A78E6);display:flex;gap:22px;align-items:center"><div style="font-size:58px;color:#fff;opacity:.9">{ic("magnifying-glass-chart")}</div><div><div style="font-size:22px;font-weight:700;line-height:1.3">যে খরচ চোখে পড়ে না, তা ঠিক করাও যায় না।</div><p style="color:#E4F2FF;margin-top:6px;font-size:14px">DT Restora প্রতিটি ফুটো আপনার চোখের সামনে এনে দেয়, যাতে আপনি সময়মতো বন্ধ করতে পারেন।</p></div></div>',
    dark=True)

# ---------------------------------------------------------------- 4 before / after
rows = [('হাতে লেখা বিল ও যোগ-বিয়োগ', 'প্রতিটি অর্ডার কাউন্টারেই রেকর্ড হয়'), ('আন্দাজে স্টক গোনা', 'প্রতিটি বিক্রিতে স্টক নিজে কমে'),
        ('অপচয় ধরা পড়ে অনেক দেরিতে', 'প্রতিটি ক্ষতি কারণ ও খরচসহ লেখা থাকে'), ('লাভ জানা যায় মাস শেষে', 'আজই দেখুন শাখাভিত্তিক লাভ'),
        ('বিক্রি শুধু দোকানের দরজায়', 'ওয়েবসাইট, কিউআর ও ডেলিভারি অর্ডার এক জায়গায়'), ('বিশ্বাসের ওপর ক্যাশ সামলানো', 'শিফটের গণনা আর প্রতিটি পরিবর্তনের লগ'),
        ('কর্মীর বেতন খাতায় হিসাব', 'হাজিরা থেকে স্বয়ংক্রিয় বেতন ও স্লিপ'), ('মাস শেষে অ্যাকাউন্ট্যান্টের হুড়োহুড়ি', 'প্রতিটি বিক্রি নিজে খাতায় ওঠে')]
r_html = ''.join(f'<div style="display:grid;grid-template-columns:1fr 38px 1fr;gap:10px;align-items:stretch;margin-top:15px"><div class="bl" style="background:var(--red-soft);border-color:#F6CFD0;align-items:center;padding:15px 14px"><div class="bi" style="background:#fff;color:var(--red);width:28px;height:28px;font-size:12px">{ic("xmark")}</div><h4 style="font-size:13.5px;font-weight:600;color:#7A2A2D">{a}</h4></div><div style="display:grid;place-items:center;color:var(--blue);font-size:18px">{ic("arrow-right")}</div><div class="bl" style="background:var(--green-soft);border-color:#BFE6D0;align-items:center;padding:15px 14px"><div class="bi" style="background:#fff;color:var(--green);width:28px;height:28px;font-size:12px">{ic("check")}</div><h4 style="font-size:13.5px;color:#0B5A36">{b}</h4></div></div>' for a, b in rows)
add('আগে ও এখন', 'ডিজিটাল হওয়ার যুক্তি',
    head('ডিজিটাল হওয়ার যুক্তি', 'খাতা-কলমে নয়, <em>DT Restora</em> দিয়ে', 'ডিজিটাল হওয়া খরচ নয়। এটাই আয়ের বেশি অংশ নিজের কাছে রাখার উপায়।')
    + '<div style="display:grid;grid-template-columns:1fr 38px 1fr;gap:10px;margin-top:22px;font-weight:700;font-size:14px"><div style="color:var(--red)">আজ, খাতা-কলমে</div><div></div><div style="color:var(--green)">DT Restora দিয়ে</div></div>'
    + r_html
    + f'<div style="margin-top:18px">{callout("thumbs-up", "গ্রাহকেরা এখন এটাই আশা করেন", "ফোনে অর্ডার, কিউআর কোড স্ক্যান, ডেলিভারির অবস্থা দেখা। যে রেস্টুরেন্ট এটা দিতে পারে, গ্রাহক সেখানেই ফিরে যান।")}</div>')

# ---------------------------------------------------------------- 5 profit
lev = [('arrow-trend-up', 'বেশি অর্ডার', 'ওয়েবসাইট, কিউআর টেবিল ও ডেলিভারি অ্যাপ, সব চ্যানেলের অর্ডার এক জায়গায়। রাতেও দোকান খোলা।'),
       ('trash-can', 'কম অপচয়', 'প্রতিটি নষ্ট খাবার কারণসহ লেখা থাকে, তাই কোথায় লোকসান হচ্ছে চোখে পড়ে।'),
       ('percent', 'ছাড়ে নিয়ন্ত্রণ', 'সীমার বেশি ছাড়ে ম্যানেজারের অনুমোদন লাগে। বাতিলে কারণ দিতে হয়।'),
       ('scale-balanced', 'সঠিক খাবারের খরচ', 'রেসিপি থেকে প্রতিটি পদের আসল খরচ জানা যায়, দাম ঠিক করা সহজ।')]
lv = ''.join(f'<div class="bl" style="flex-direction:column;gap:8px;padding:16px"><div class="bi" style="width:42px;height:42px;font-size:19px">{ic(i)}</div><h4 style="font-size:16px">{a}</h4><p>{b}</p></div>' for i, a, b in lev)
eq = [('নিট বিক্রি', 'sack-dollar', ''), ('খাবারের খরচ', 'bowl-food', '−'), ('অপচয়', 'trash-can', '−'), ('কর্মীর বেতন', 'users', '−'), ('খরচ ও কমিশন', 'coins', '−'), ('নিট লাভ', 'chart-line', '=')]
eq_html = ''.join(f'<div style="display:flex;align-items:center;gap:6px">' + (f'<span style="font-size:20px;font-weight:700;color:var(--sky)">{s}</span>' if s else '') + f'<div style="text-align:center;background:{"var(--blue)" if s=="=" else "#fff"};color:{"#fff" if s=="=" else "var(--ink)"};border:1px solid var(--line);border-radius:12px;padding:8px 10px;font-size:12px;font-weight:700;min-width:80px"><div style="font-size:16px;color:{"#fff" if s=="=" else "var(--blue)"}">{ic(i)}</div>{a}</div></div>' for a, i, s in eq)
add('লাভ', 'লাভ বাড়ে যেভাবে',
    head('লাভ বাড়ে যেভাবে', 'চারটি উপায়ে <em>লাভ আপনার কাছে থাকে</em>', 'DT Restora লাভ বাড়ায় দুই দিক থেকে: বিক্রি বাড়িয়ে, আর ফুটো বন্ধ করে।')
    + f'<div class="bls c2" style="margin-top:20px">{lv}</div>'
    + f'<div style="margin-top:18px;font-size:15px;font-weight:700">আপনার লাভের হিসাব, প্রতিদিন নিজে থেকে তৈরি</div><div style="display:flex;flex-wrap:wrap;gap:8px 4px;margin-top:10px;align-items:center">{eq_html}</div>'
    + stage(card('admin-02-dashboard', 1040, 935, 745, 330, 698, style='left:0;top:6px') + chip('circle-check', 'ড্যাশবোর্ড, রিপোর্ট ও হিসাবের লাভ একই সংখ্যা', 'right:14px;top:-4px', 'g'), 698, 270, 'margin-top:14px'))

# ---------------------------------------------------------------- 6 ROI example
roi = [('trash-can', 'অপচয় ও অতিরিক্ত কেনা কমে', 'খাবারের খরচ ১,৬০,০০০ টাকার ৫%', '৮,০০০'),
       ('receipt', 'ক্যাশের গরমিল ও ফাঁকা বিল কমে', 'বিক্রির ১%', '৫,০০০'),
       ('arrow-trend-up', 'অনলাইন, কিউআর ও ডেলিভারি থেকে বাড়তি অর্ডার', '৩% বেশি বিক্রি (১৫,০০০ টাকা), লাভ ২৫%', '৩,৭৫০')]
roi_rows = ''.join(f'<div style="display:flex;gap:14px;align-items:center;padding:13px 0;border-bottom:1px dashed rgba(255,255,255,.22)"><div class="bi" style="width:40px;height:40px;font-size:18px">{ic(i)}</div><div style="flex:1"><div style="font-size:15px;font-weight:700">{a}</div><div style="font-size:12.5px;color:#9FB6D6">{b}</div></div><div style="font-size:22px;font-weight:700;color:#38A8FF">{c} <span style="font-size:12px;color:#9FB6D6;font-weight:500">টাকা</span></div></div>' for i, a, b, c in roi)
add('একটি হিসাব', 'এটি নিজের খরচ নিজেই তোলে',
    head('একটি সহজ হিসাব', 'মাসে ১,০০০ টাকা খরচ, <em>লাভ কত ফিরে আসতে পারে?</em>', 'নিচে একটি কাল্পনিক উদাহরণ। মাসিক বিক্রি ৫,০০,০০০ টাকার একটি রেস্টুরেন্ট ধরা হয়েছে।')
    + f'<div class="abs" style="left:48px;right:48px;top:290px;border-radius:24px;padding:10px 28px 28px;background:rgba(10,20,40,.55);border:1.5px solid rgba(56,168,255,.4)"><div style="font-size:13px;font-weight:700;color:#38A8FF;letter-spacing:.04em;margin-top:14px">কাল্পনিক উদাহরণ · মাসিক হিসাব</div>{roi_rows}'
    f'<div style="display:flex;justify-content:space-between;align-items:center;padding:16px 0 4px"><div style="font-size:15px;color:#C4D3EA">মোট সম্ভাব্য বাড়তি লাভ</div><div style="font-size:30px;font-weight:700">১৬,৭৫০ <span style="font-size:13px;font-weight:500;color:#9FB6D6">টাকা</span></div></div>'
    f'<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0"><div style="font-size:15px;color:#C4D3EA">DT Restora মাসিক মূল্য</div><div style="font-size:22px;font-weight:700;color:#FF8A8E">− ১,০০০ <span style="font-size:13px;font-weight:500">টাকা</span></div></div>'
    f'<div style="margin-top:14px;border-radius:16px;padding:16px 22px;background:linear-gradient(135deg,#0B63CE,#1A78E6);display:flex;justify-content:space-between;align-items:center"><div style="font-size:16px;font-weight:700">নিট বাড়তি লাভ প্রতি মাসে</div><div style="font-size:36px;font-weight:700">১৫,৭৫০ <span style="font-size:14px;font-weight:500">টাকা</span></div></div></div>'
    + '<div class="abs" style="left:48px;right:48px;top:818px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px">' + ''.join(f'<div class="bl" style="flex-direction:column;align-items:flex-start;gap:4px;padding:16px"><div style="font-size:30px;font-weight:700;color:#38A8FF;line-height:1.1">{a}</div><p style="font-size:13px">{b}</p></div>' for a, b in [('১৬.৭৫ গুণ', 'খরচের তুলনায় সম্ভাব্য ফেরত'), ('৩টি উপায়', 'সঞ্চয় ও বাড়তি আয়ের'), ('০ টাকা', 'প্রথম মাসের মাসিক ফি')]) + '</div>'
    + f'<div class="abs" style="left:48px;right:48px;bottom:84px"><div class="bl" style="align-items:center"><div class="bi">{ic("triangle-exclamation")}</div><p style="font-size:12.5px"><b style="color:#fff">খেয়াল রাখুন:</b> এটি বোঝানোর জন্য কাল্পনিক উদাহরণ। প্রকৃত ফল রেস্টুরেন্টভেদে আলাদা হয়। আপনার সংখ্যা বসিয়ে হিসাব করে দিতে আমাদের বলুন।</p></div></div>',
    dark=True)

# ---------------------------------------------------------------- 7 time
tm = [('cash-register', 'বিল তৈরি ও যোগ-বিয়োগ', 'এক ট্যাপে আইটেম, হিসাব নিজে হয়'), ('kitchen-set', 'রান্নাঘরে অর্ডার পাঠানো', 'কাউন্টার থেকে সরাসরি স্ক্রিনে, চেঁচামেচি ছাড়া'),
      ('boxes-stacked', 'স্টক গোনা ও খাতা মেলানো', 'প্রতিটি বিক্রিতে স্টক নিজে কমে'), ('fingerprint', 'হাজিরা ও বেতনের হিসাব', 'মেশিনের হাজিরা থেকে বেতন-স্লিপ'),
      ('file-invoice-dollar', 'মাস শেষের রিপোর্ট ও খাতা', 'রিপোর্ট ও খতিয়ান প্রতিদিন হালনাগাদ'), ('phone', 'ফোনে অর্ডার নেওয়া', 'ওয়েবসাইট ও কিউআর অর্ডার নিজেই আসে')]
tm_html = ''.join(f'<div class="bl" style="align-items:center;padding:18px 16px"><div class="bi" style="width:46px;height:46px;font-size:21px;background:var(--blue);color:#fff">{ic(i)}</div><div style="flex:1"><h4 style="font-size:16px">{a}</h4><p>{b}</p></div><div style="font-size:11.5px;font-weight:700;color:var(--green);background:var(--green-soft);border-radius:999px;padding:4px 10px;white-space:nowrap">নিজে নিজে</div></div>' for i, a, b in tm)
add('সময়', 'সময় বাঁচে যেভাবে',
    head('সময় বাঁচে যেভাবে', 'যে কাজ হাতে করতেন, <em>এখন নিজে নিজে হয়</em>', 'একবার লিখলেই বিক্রি, স্টক, খাতা ও মালিকের ড্যাশবোর্ড হালনাগাদ হয়। কাউকে দুবার টাইপ করতে হয় না।')
    + f'<div class="bls c1" style="margin-top:22px">{tm_html}</div>'
    + f'<div style="margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:14px">{callout("clock", "মালিকের সময় ফেরে", "রান্নাঘরে না দাঁড়িয়েও সব শাখার খবর ফোন বা কম্পিউটারে।")}{callout("bolt", "সার্ভিস হয় দ্রুত", "কাউন্টার ও রান্নাঘর একই বোর্ড দেখে, খাবার তাড়াতাড়ি বের হয়।", True)}</div>')

# ---------------------------------------------------------------- 8 easy
steps = [('language', 'সবকিছু বাংলায়', 'বাংলা ও ইংরেজি, এক ক্লিকে ভাষা বদল। কর্মীরা নিজের ভাষায় কাজ করেন।'),
         ('user-shield', 'প্রত্যেকের নিজের স্ক্রিন', 'ক্যাশিয়ার দেখেন শুধু পিওএস, রান্নাঘর দেখে শুধু টিকেট। বাড়তি কিছু নেই, তাই ভুলও কম।'),
         ('mobile-screen-button', 'টাচ ও ফোনে চলে', 'ট্যাবলেট, টাচ মনিটর, কম্পিউটার বা ফোন, যেখানে সুবিধা সেখানেই।')]
sh = ''.join(f'<div class="bl" style="align-items:center"><div class="bi" style="width:44px;height:44px;font-size:20px;background:var(--blue);color:#fff">{ic(i)}</div><div><h4 style="font-size:16px">{a}</h4><p>{b}</p></div></div>' for i, a, b in steps)
add('সহজ ব্যবহার', 'ব্যবহার করা সহজ',
    head('ব্যবহার করা সহজ', 'ক্যাশিয়ার <em>কয়েক মিনিটেই</em> শিখে ফেলেন', 'প্রযুক্তি জানতে হবে না। প্রতিটি ভূমিকার জন্য আছে পরিষ্কার একটি স্ক্রিন, শুধু প্রয়োজনীয় টুল নিয়ে।')
    + stage(card('admin-01-login', 0, 0, 1800, 1037, 560, style='left:0;top:6px')
            + phone('mobile-01-home', 190, 0, 1500, style='left:500px;top:0px;transform:rotate(3deg)')
            + chip('user-shield', 'রোল অনুযায়ী লগইন', 'left:300px;top:330px', 'n')
            + chip('language', 'বাংলা · English', 'left:20px;top:380px', 's'), 698, 470, 'margin-top:14px')
    + f'<div class="bls c1" style="margin-top:10px">{sh}</div>')

# ---------------------------------------------------------------- 9 feature map
groups = [('বিক্রি', [('cash-register', 'পিওএস ও টেবিল'), ('bell-concierge', 'কিচেন ডিসপ্লে'), ('credit-card', 'অর্ডার ও পেমেন্ট'), ('globe', 'অর্ডারের ওয়েবসাইট')]),
          ('গ্রাহক', [('star', 'লয়্যালটি পয়েন্ট'), ('ticket', 'কুপন ও অফার'), ('qrcode', 'কিউআর টেবিল অর্ডার'), ('users', 'গ্রাহকের অ্যাকাউন্ট')]),
          ('স্টক', [('clipboard-list', 'রেসিপি ও খরচ'), ('truck-ramp-box', 'ক্রয় ও সরবরাহকারী'), ('trash-can', 'অপচয়ের তালিকা'), ('boxes-stacked', 'স্টক গণনা ও স্থানান্তর')]),
          ('কর্মী', [('user-clock', 'কর্মী ও শিফট'), ('fingerprint', 'হাজিরা মেশিন'), ('money-bill-wave', 'বেতন ও স্লিপ'), ('file-lines', 'চিঠিপত্র')]),
          ('প্রসার', [('motorcycle', 'ডেলিভারি ও রাইডার'), ('truck-fast', 'ডেলিভারি অ্যাপ'), ('bullhorn', 'ক্যারিয়ার ও ব্লগ'), ('images', 'ছবি ও ভিডিও')]),
          ('নিয়ন্ত্রণ', [('scale-balanced', 'হিসাবরক্ষণ'), ('file-export', 'রিপোর্ট ও এক্সপোর্ট'), ('code-branch', 'একাধিক শাখা'), ('clock-rotate-left', 'অ্যাক্টিভিটি লগ ও সিঙ্ক')])]
g_html = ''.join(f'<div style="background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px"><div style="font-size:13px;font-weight:700;color:var(--blue);margin-bottom:8px;letter-spacing:.03em">{g}</div>' + ''.join(f'<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font-size:13.5px;font-weight:600"><span class="bi" style="width:28px;height:28px;font-size:13px;border-radius:8px">{ic(i)}</span>{t}</div>' for i, t in items) + '</div>' for g, items in groups)
add('ফিচার মানচিত্র', 'সব ফিচার',
    head('সব ফিচার একনজরে', '<em>২৪টি টুল,</em> একটি লগইন', 'পিওএস আর রান্নাঘর দিয়ে শুরু করুন। প্রস্তুত হলে স্টক, কর্মী, লয়্যালটি, ডেলিভারি আর ওয়েবসাইট যোগ করুন।')
    + f'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:22px">{g_html}</div>'
    + f'<div style="margin-top:16px">{callout("link", "সব একই মেনু, গ্রাহক, কর্মী ও হিসাব ব্যবহার করে", "কাউন্টারে একটি বিক্রি হলে রান্নাঘর, স্টক, খাতা ও মালিকের ড্যাশবোর্ড নিজে থেকেই হালনাগাদ হয়।")}</div>')

# ---------------------------------------------------------------- 10 POS
feature('পিওএস', 'বিক্রি · পিওএস', 'পিওএস টার্মিনাল', 'কাউন্টারে অর্ডার নেওয়া <em>হবে দ্রুত ও নির্ভুল</em>', 'ট্যাবলেট বা টাচ স্ক্রিনে আইটেম ছুঁয়ে অর্ডার, সব পেমেন্ট পদ্ধতি আর রসিদ প্রিন্টার একসাথে।',
        card('admin-61-pos-items-added', 0, 0, 1800, 1037, 698, style='left:0;top:8px')
        + card('admin-60-pos-size-addon', 600, 290, 610, 540, 270, chrome=False, style='left:420px;top:160px;transform:rotate(2deg)')
        + chip('layer-group', 'সাইজ ও অ্যাড-অন', 'left:440px;top:128px')
        + chip('credit-card', 'বিকাশ · নগদ · কার্ড · ভাগ করে পেমেন্ট', 'left:300px;top:-6px', 'g')
        + chip('kitchen-set', 'এক ক্লিকে কিচেনে', 'left:24px;top:404px', 's'), 470,
        [('cash-register', 'দ্রুত আইটেম বাছাই', 'ক্যাটাগরি, সার্চ বা বারকোড দিয়ে খুঁজুন; নির্বাচিত আইটেম রঙে চেনা যায়।'),
         ('percent', 'ছাড়ে নিয়ন্ত্রণ', 'সীমার বেশি ছাড়ে ম্যানেজারের পিন লাগে।'),
         ('hourglass-half', 'হোল্ড ও আবার চালু', 'গ্রাহক সময় নিলে টিকেট রেখে অন্য অর্ডার নিন।'),
         ('print', '৮০ মিমি থার্মাল রসিদ', 'পুনরায় প্রিন্টের হিসাব থাকে। শিফটের নগদ মেলে।')],
        callout('bolt', 'নতুন ক্যাশিয়ারের জন্য একটাই স্ক্রিন', 'শিখতে সময় কম লাগে, তাই ব্যস্ত সপ্তাহেও কাজ আটকায় না।'))

# ---------------------------------------------------------------- 11 online ordering
feature('অনলাইন অর্ডার', 'বিক্রি · ওয়েবসাইট ও কিউআর', 'নিজস্ব ওয়েবসাইট ও কিউআর', 'আপনি রান্নায় ব্যস্ত, <em>অর্ডার আসতেই থাকে</em>', 'মেনুর ছবি, চেকআউট ও সরাসরি ট্র্যাকিংসহ বাংলা-ইংরেজি, মোবাইল-প্রথম ওয়েবসাইট। অর্ডার সরাসরি কিচেন স্ক্রিনে।',
        card('desktop-01-home', 0, 0, 1800, 1037, 520, style='left:0;top:10px')
        + card('desktop-07-checkout', 0, 0, 1800, 1037, 300, style='left:250px;top:292px')
        + phone('mobile-03-menu', 170, 0, 1150, style='left:508px;top:0px;transform:rotate(3deg)')
        + phone('mobile-05-dish', 150, 0, 1150, style='left:350px;top:170px;transform:rotate(-4deg)')
        + chip('qrcode', 'কিউআর স্ক্যান করে টেবিল অর্ডার', 'left:0px;top:345px', 'g')
        + chip('route', 'লাইভ অর্ডার ট্র্যাকিং', 'left:0px;top:400px')
        + chip('cloud', 'গুগলে খুঁজে পাওয়া যায়', 'left:540px;top:420px', 's'), 480,
        [('globe', 'আপনার ডোমেইনে ওয়েবসাইট', 'গ্যালারি, ব্লগ, ক্যারিয়ার ও শাখার পেজসহ।'), ('comment-sms', 'এসএমএস কোডে অর্ডার নিশ্চিত', 'ভুয়া অর্ডার কমে, গ্রাহকের নম্বর নিশ্চিত হয়।'),
         ('motorcycle', 'ডেলিভারি এলাকা ও ফি', 'প্রতি এলাকার ফি ও ন্যূনতম অর্ডার ঠিক করুন।'), ('credit-card', 'অনলাইন পেমেন্ট', 'বিকাশ ও কার্ড, অথবা ক্যাশ অন ডেলিভারি।')],
        callout('bag-shopping', 'আপনার মেনু সবসময় খোলা', 'মাঝরাতে অর্ডার করলেও সকালে দোকান খুলে আপনি তা পেয়ে যান। ফোন মিস হয় না, অর্ডারও ভুল হয় না।'))

# ---------------------------------------------------------------- 12 KDS
feature('কিচেন ডিসপ্লে', 'রান্নাঘর · কিচেন ডিসপ্লে', 'কিচেন ডিসপ্লে', 'রান্নাঘর প্রতিটি অর্ডার দেখে <em>তৎক্ষণাৎ</em>', 'কাগজের স্লিপ হারানোর ভয় নেই। কাউন্টার, ওয়েবসাইট, কিউআর ও ডেলিভারি অ্যাপের টিকেট এক সরাসরি বোর্ডে।',
        card('admin-06-kitchen-display', 275, 60, 1525, 977, 690, style='left:4px;top:6px')
        + chip('bell-concierge', 'নতুন → তৈরি হচ্ছে → প্রস্তুত', 'left:30px;top:0px')
        + chip('truck-fast', 'ফুডপান্ডা · পাঠাও · হাংরিনাকি', 'right:20px;top:200px', 'n')
        + chip('bolt', 'এক ট্যাপে পরের ধাপ', 'right:60px;top:396px', 'g'), 480,
        [('clock', 'প্রতিটি টিকেটে সময়', 'কত সময় অপেক্ষা করছে দেখা যায়, দেরি আগেই ধরা পড়ে।'), ('bell', 'শব্দের সংকেত', 'নতুন টিকেট এলে ঘণ্টা বাজে।'),
         ('table-cells', 'ধরন ও টেবিল', 'ডাইন-ইন, টেকআওয়ে বা ডেলিভারি, টেবিলসহ।'), ('wifi', 'অফলাইনেও চলে', 'ইন্টারনেট না থাকলেও স্থানীয় টিকেট চলতেই থাকে।')],
        callout('bolt', 'দ্রুত সার্ভিস, শান্ত রান্নাঘর', 'সবাই একই বোর্ড দেখলে খাবার দ্রুত বের হয়, আর অর্ডার হারানোও কমে।'))

# ---------------------------------------------------------------- 13 menu & media
feature('মেনু ও ছবি', 'বিক্রি · মেনু ও ছবি', 'মেনু ও ছবি-ভিডিও', 'আপনার মেনু, <em>আপনার নিয়ন্ত্রণে</em>', 'ক্যাটাগরি, আইটেম, সাইজ ও অ্যাড-অন একবার সাজান। পিওএস, ওয়েবসাইট ও কিউআর, সবখানে নিজে থেকে হালনাগাদ।',
        card('admin-07-menu', 275, 60, 1525, 977, 600, style='left:0;top:8px')
        + card('admin-35-add-item', 880, 0, 920, 600, 290, chrome=False, style='left:408px;top:44px;transform:rotate(2deg)')
        + card('admin-08-media', 275, 60, 1525, 977, 340, style='left:20px;top:245px;transform:rotate(-2deg)')
        + chip('pen-to-square', 'দাম, সাইজ, অ্যাড-অন', 'left:458px;top:14px')
        + chip('images', 'প্রতিটি খাবারের ছবি ও ভিডিও', 'left:400px;top:420px', 's'), 480,
        [('layer-group', 'সাইজ ও অ্যাড-অন', 'রেগুলার, লার্জ, বোরহানি, ডিম, যা খুশি যোগ করুন।'), ('eye', 'পাওয়া যাচ্ছে বা নেই', 'শেষ হওয়া আইটেম এক ক্লিকে বন্ধ করুন।'),
         ('images', 'ছবি ও ভিডিও গ্যালারি', 'কোন ছবি ওয়েবসাইটে দেখাবে, আপনি ঠিক করুন।'), ('tags', 'শাখাভিত্তিক দাম ও ভ্যাট', 'প্রতি শাখার ভ্যাট ও সার্ভিস চার্জ আলাদা।')],
        callout('wand-magic-sparkles', 'ছবি দেখে অর্ডার বাড়ে', 'ভালো ছবি আর পরিষ্কার দাম গ্রাহককে দ্রুত সিদ্ধান্ত নিতে সাহায্য করে।'))

# ---------------------------------------------------------------- 14 inventory
feature('স্টক ও রেসিপি', 'স্টক · রেসিপি, ক্রয় ও অপচয়', 'স্টক ও খরচ', 'জানুন প্রতিটি পদের <em>আসল খরচ</em>', 'রেসিপি একবার বানান। এরপর প্রতিটি বিক্রিতে সঠিক পরিমাণ স্টক কমে, আর খাবারের খরচ থাকে হালনাগাদ।',
        card('admin-09-ingredients', 275, 60, 1525, 977, 540, style='left:0;top:6px')
        + card('admin-13-wastage', 275, 60, 1525, 700, 460, style='left:238px;top:170px;transform:rotate(2deg)')
        + card('admin-12-purchases', 275, 60, 1525, 600, 390, style='left:22px;top:300px;transform:rotate(-2deg)')
        + chip('triangle-exclamation', 'স্টক কমলে সতর্কতা', 'left:320px;top:0px', 'n')
        + chip('trash-can', 'কারণসহ অপচয়ের তালিকা', 'left:440px;top:440px', 's'), 470,
        [('clipboard-list', 'রেসিপি ও একক রূপান্তর', 'গ্রাম, মিলি বা পিস হিসাবে উপকরণ।'), ('arrow-trend-up', 'স্বয়ংক্রিয় বাদ', 'প্রতিটি বিক্রিতে স্টক কমে, বাতিলে ফিরে আসে।'),
         ('truck-ramp-box', 'ক্রয় ও সরবরাহকারী', 'পণ্য গ্রহণ ও দামের ইতিহাস। পাওনা ঠিকঠাক।'), ('boxes-stacked', 'স্টক গণনা ও স্থানান্তর', 'গরমিলের রিপোর্ট, শাখা থেকে শাখায় পাঠানো।')],
        callout('triangle-exclamation', 'সমস্যা আগেই ধরুন', 'স্টক পুনরায় কেনার সীমার নিচে নামলে বা অপচয় সাম্প্রতিক গড়ের চেয়ে বাড়লে সতর্কতা আসে।'))

# ---------------------------------------------------------------- 15 customers
feature('গ্রাহক', 'গ্রাহক · লয়্যালটি ও কুপন', 'লয়্যালটি, কুপন ও গ্রাহক', 'প্রথমবারের গ্রাহককে <em>বানান নিয়মিত</em>', 'পয়েন্ট ও কুপন কাউন্টার আর ওয়েবসাইট, দুই জায়গাতেই কাজ করে। প্রতিটি গ্রাহকের সব চ্যানেলে একটাই রেকর্ড।',
        trio('admin-17-loyalty','admin-18-coupons','admin-16-customers',('star','পয়েন্ট জমা ও খরচ, সব জায়গায়'),('ticket','চ্যানেল ও সীমা অনুযায়ী কুপন','s')), 500,
        [('star', 'প্রতিটি পরিশোধিত অর্ডারে পয়েন্ট', 'হার ও মেয়াদ আপনি ঠিক করুন।'), ('crown', 'সিলভার ও গোল্ড স্তর', 'বিশ্বস্ত গ্রাহকের জন্য বাড়তি পয়েন্ট।'),
         ('ticket', 'শতাংশ বা নির্দিষ্ট অঙ্কের কুপন', 'ন্যূনতম অর্ডার ও ব্যবহারের সীমাসহ।'), ('comment-sms', 'এসএমএস ক্যাম্পেইন', 'জন্মদিনের অফার ও ফিরিয়ে আনার বার্তা।')],
        callout('hand-holding-dollar', 'গ্রাহককে ফিরিয়ে আনুন', 'কাউন্টারে পয়েন্ট জমান, অনলাইনে খরচ করুন। সব জায়গায় একই ব্যালেন্স, তাই গ্রাহক আবার আসেন।'))

# ---------------------------------------------------------------- 16 delivery
feature('ডেলিভারি', 'প্রসার · ডেলিভারি ও অ্যাগ্রিগেটর', 'ডেলিভারি, রাইডার ও ডেলিভারি অ্যাপ', 'প্রতিটি ডেলিভারির খোঁজ, <em>প্রতিটি টাকার হিসাব</em>', 'রাইডার ঠিক করুন, গ্রাহককে যাত্রা অনুসরণ করতে দিন, আর ফুডপান্ডা-পাঠাও-হাংরিনাকির অর্ডার ও কমিশন একই খাতায় রাখুন।',
        trio('admin-24-delivery','admin-25-aggregators','admin-04-orders',('motorcycle','ডিসপ্যাচ বোর্ড ও রাইডার'),('truck-fast','অ্যাপের কমিশন লেখা থাকে','n')), 500,
        [('route', 'সরাসরি ট্র্যাকিং', 'গৃহীত থেকে পৌঁছানো, প্রতিটি ধাপ গ্রাহক দেখেন।'), ('motorcycle', 'রাইডারের মোবাইল পেজ', 'ডিউটি চালু-বন্ধ, নির্ধারিত অর্ডার ও পথনির্দেশ।'),
         ('hand-holding-dollar', 'ক্যাশ অন ডেলিভারির হিসাব', 'প্রতিটি রাইডারের হিসাব মেলান।'), ('truck-fast', 'অ্যাপের অর্ডার ও পাওনা', 'প্রত্যাশিত বনাম প্রাপ্ত টাকা মেলান।')],
        callout('coins', 'কমিশন কখনও চমক হয়ে আসে না', 'প্রতিটি অর্ডারে ডেলিভারি অ্যাপের কমিশন লেখা থাকে, তাই অ্যাপ নিজের ভাগ নেওয়ার পরের লাভ দেখা যায়।'))

# ---------------------------------------------------------------- 17 staff
feature('কর্মী', 'কর্মী · হাজিরা ও বেতন', 'কর্মী, হাজিরা ও বেতন', 'কে কাজ করল তা জানুন, <em>বেতন দিন সঠিকভাবে</em>', 'হাজিরা থেকে সরাসরি বেতন, অগ্রিম ও বেতনের স্লিপ তৈরি হয়। বেতনের খরচ দৈনিক লাভের হিসাবে ধরা হয়।',
        trio('admin-19-staff','admin-20-attendance','admin-27-payroll',('fingerprint','ZKTeco ফিঙ্গারপ্রিন্ট হাজিরা'),('money-bill-wave','হাজিরা থেকে স্বয়ংক্রিয় বেতন','g')), 500,
        [('user-clock', 'হাজিরা মেশিন ও হাতে এন্ট্রি', 'মেশিনের হাজিরা নিজে আসে; না থাকলে হাতে লিখুন।'), ('money-bill-wave', 'দৈনিক, মাসিক ও ঘণ্টাভিত্তিক বেতন', 'হাজিরা থেকে হিসাব হয়।'),
         ('hand-holding-dollar', 'অগ্রিম নিজে থেকে কাটা', 'একবার লিখলেই বেতন থেকে কাটা হয়।'), ('file-lines', 'বেতন-স্লিপ ও চিঠিপত্র', 'নিজের লেটারহেডে এক ক্লিকে প্রিন্ট।')],
        callout('calculator', 'কাগজের রেজিস্টারের দিন শেষ', 'বেতন প্রতিদিনের হিসাবে ভাগ হয়, তাই শাখার লাভে কর্মীর বেতন আগেই ধরা থাকে।'))

# ---------------------------------------------------------------- 18 accounts
feature('হিসাব ও রিপোর্ট', 'নিয়ন্ত্রণ · হিসাব ও রিপোর্ট', 'হিসাবরক্ষণ ও রিপোর্ট', 'যে খাতা <em>নিজেই লেখা হয়</em>', 'প্রতিটি বিক্রি, ক্রয়, খরচ ও বেতন সঠিক ডাবল-এন্ট্রি খতিয়ানে উঠে যায়, তাই আপনার হিসাবরক্ষক পান পরিষ্কার স্টেটমেন্ট।',
        trio('admin-26-accounting','admin-28-reports','admin-53-ledger',('scale-balanced','ট্রায়াল ব্যালান্স, লাভ-ক্ষতি, স্থিতিপত্র'),('file-export','PDF · Excel · CSV','s')), 500,
        [('calculator', 'স্বয়ংক্রিয় জার্নাল এন্ট্রি', 'বিক্রি, ভ্যাট, ক্রয়, বেতন ও অপচয় থেকে।'), ('file-invoice-dollar', 'ভাউচার ও খরচ', 'সংযুক্তি ও সরবরাহকারীর পাওনাসহ।'),
         ('chart-pie', 'ক্যাশ ফ্লো ও দেনার বয়স', 'কে কত পাবে, কবে, এক নজরে।'), ('lock', 'অর্থবছর লক', 'পোস্ট করা এন্ট্রি উল্টে দেওয়া হয়, কখনও বদলানো হয় না।')],
        callout('calendar-check', 'মাস শেষের হুড়োহুড়ি নেই', 'আপনার খাতা প্রতিদিনই হালনাগাদ, কারণ বিক্রির হিসাব কাউকে আবার টাইপ করতে হয় না।'))

# ---------------------------------------------------------------- 19 dashboard
db_pts = [('sack-dollar', 'নিট বিক্রি ও অর্ডার', 'আজ, গতকাল, ৭ দিন বা মাস বেছে দেখুন।'), ('bowl-food', 'খাবারের খরচ %', 'রেসিপি থেকে হিসাব, তাই নির্ভরযোগ্য।'),
          ('chart-line', 'নিট লাভ ও মার্জিন', 'সব খরচ, অপচয় ও বেতন বাদ দিয়ে।'), ('triangle-exclamation', 'স্টকের সতর্কতা', 'কোন উপকরণ ফুরিয়ে আসছে সঙ্গে সঙ্গে।')]
add('মালিকের ড্যাশবোর্ড', 'মালিকের নিয়ন্ত্রণ · ড্যাশবোর্ড',
    head('মালিকের ড্যাশবোর্ড', 'আপনার আসল লাভ, <em>প্রতিদিন, প্রতিটি শাখায়</em>', 'মোট ও নিট বিক্রি, খরচ, সতর্কতা আর আনুমানিক লাভ এক স্ক্রিনে। দোকানে না গিয়েও দেখুন।')
    + stage(card('admin-02-dashboard', 0, 0, 1800, 1559, 540, style='left:0;top:6px')
            + card('admin-63-dark-dashboard', 0, 0, 1800, 1037, 300, style='left:392px;top:230px;transform:rotate(2deg)')
            + chip('chart-line', 'নিট লাভ ও মার্জিন', 'left:250px;top:20px')
            + chip('code-branch', 'শাখা তুলনা', 'left:200px;top:440px', 's')
            + chip('triangle-exclamation', 'স্টক সতর্কতা', 'left:380px;top:120px', 'n')
            + chip('moon', 'রাতের জন্য ডার্ক মোড', 'left:470px;top:400px', 'g'), 698, 500, 'margin-top:14px')
    + f'<div style="margin-top:12px;position:relative">{bullets(db_pts)}</div>'
    + f'<div style="margin-top:12px;position:relative">{callout("eye", "সব জায়গায় একই সংখ্যা", "ড্যাশবোর্ড, রিপোর্ট আর হিসাবের লাভ-ক্ষতি, সবই একে অপরের সাথে মেলে।")}</div>')

# ---------------------------------------------------------------- 20 control
feature('শাখা ও নিয়ন্ত্রণ', 'নিয়ন্ত্রণ · শাখা, টেবিল ও সেটিংস', 'শাখা, টেবিল ও সেটিংস', 'অনেক শাখা চালান <em>এক স্ক্রিন থেকে</em>', 'শাখা, টেবিল, মেনু, সেটিংস আর কার্যকলাপের লগ পুরো গ্রুপকে গোছানো ও দায়বদ্ধ রাখে।',
        trio('admin-30-branches','admin-03-tables','admin-33-activity-log',('code-branch','প্রতি শাখার আলাদা ভ্যাট ও চার্জ'),('clock-rotate-left','কে কী করল, সব লেখা থাকে','n')), 500,
        [('building', 'নিজস্ব সময়সূচি ও সেটিংসসহ শাখা', 'প্রতিটি শাখার ম্যানেজার নিজের শাখা দেখেন, আপনি দেখেন সবগুলো।'), ('table-cells', 'টেবিল ও কিউআর কোড', 'অর্ডারের জন্য প্রতিটি টেবিলের নিজস্ব কোড।'),
         ('sliders', 'ভ্যাট, সার্ভিস চার্জ, সীমা', 'ছাড়ের সীমা, ওটিপি ও আরও অনেক সেটিং।'), ('rotate', 'অফলাইন সিঙ্ক', 'ইন্টারনেট ফিরলে জমে থাকা বিক্রি নিজে সিঙ্ক হয়।')],
        callout('shield-halved', 'ছোটখাটো খবরদারি ছাড়াই নিয়ন্ত্রণ', 'সীমার বেশি ছাড়ে অনুমোদন, বাতিলে কারণ আর প্রতিটি পরিবর্তন লগে থাকে।'))

# ---------------------------------------------------------------- 21 security
sec = [('lock', 'রোল অনুযায়ী প্রবেশাধিকার', 'প্রত্যেকে শুধু নিজের কাজের অংশটুকু দেখেন ও বদলান।'), ('user-shield', 'ম্যানেজারের অনুমোদন', 'সীমার বেশি ছাড় ও বাতিলে পিন ও কারণ লাগে।'),
       ('clock-rotate-left', 'প্রতিটি কাজের লগ', 'কে, কখন, কী করল, সব লেখা থাকে এবং মোছা যায় না।'), ('database', 'আপনার নিজের ডাটাবেস', 'প্রতিটি গ্রাহকের আলাদা ইনস্টলেশন ও ডাটাবেস।'),
       ('cloud-arrow-up', 'প্রতিদিনের ব্যাকআপ', 'তথ্য নিরাপদ থাকে, আর তথ্য সবসময় আপনার।'), ('wifi', 'অফলাইনে নিরাপদ', 'ইন্টারনেট গেলেও নগদ বিক্রি চলে, ফিরলে সিঙ্ক হয়।'),
       ('receipt', 'ক্যাশ মেলানো', 'শিফট শেষে হওয়ার কথা কত, গোনা হলো কত, পার্থক্য কত।'), ('scale-balanced', 'অর্থবছর লক', 'পোস্ট করা হিসাব পরে বদলানো যায় না, শুধু সংশোধনী এন্ট্রি।')]
add('নিরাপত্তা', 'নিরাপত্তা ও বিশ্বাস',
    head('নিরাপত্তা ও বিশ্বাস', 'আপনার তথ্য, আপনার টাকা, <em>আপনার নিয়ন্ত্রণ</em>', 'ছোট দোকানেও ক্যাশ ও তথ্য সুরক্ষিত রাখা জরুরি। DT Restora প্রতিটি স্তরে নিয়ন্ত্রণ ও স্বচ্ছতা রাখে।')
    + f'<div style="margin-top:22px">{bullets(sec, 2)}</div>'
    + stage(card('admin-33-activity-log', 275, 60, 1525, 700, 698, style='left:0;top:0') + chip('clock-rotate-left', 'কে, কখন, কী করল, সব লেখা থাকে', 'left:30px;top:-8px', 'n'), 698, 340, 'margin-top:20px')
    + f'<div style="margin-top:14px">{callout("shield-halved", "আস্থার সঙ্গে চালান", "দিন শেষে আপনি জানেন ঠিক কী ঘটেছে, কে করেছে আর কখন।", True)}</div>')

# ---------------------------------------------------------------- 22 roles
roles = [('crown', 'মালিক', 'ড্যাশবোর্ড, লাভ, শাখার তুলনা, অনুমোদন ও সব রিপোর্ট'), ('building', 'শাখা ম্যানেজার', 'দৈনন্দিন কাজ, স্টকের অনুরোধ, শিফট, ছাড়ের অনুমোদন'),
         ('cash-register', 'ক্যাশিয়ার', 'পিওএস, পেমেন্ট, বিল ভাগ, ক্যাশ গণনাসহ শিফট'), ('chair', 'ওয়েটার', 'টেবিলের দৃশ্য, অর্ডার নেওয়া ও নিজের অর্ডার'),
         ('kitchen-set', 'কিচেন ম্যানেজার', 'কিচেন ডিসপ্লে, রেসিপি, স্টক গ্রহণ, অপচয় ও স্টক গণনা'), ('calculator', 'হিসাবরক্ষক', 'জার্নাল, ভাউচার, দেনা, স্টেটমেন্ট ও সময়কাল লক'),
         ('motorcycle', 'রাইডার', 'নির্ধারিত ডেলিভারি, অবস্থার বোতাম, পথনির্দেশ ও ক্যাশের সারসংক্ষেপ')]
ro = ''.join(f'<div class="bl" style="align-items:flex-start;padding:14px"><div class="bi" style="width:38px;height:38px;background:var(--blue);color:#fff;font-size:17px">{ic(i)}</div><div><h4 style="font-size:14.5px">{a}</h4><p style="font-size:12.3px">{b}</p></div></div>' for i, a, b in roles)
add('ভূমিকা', 'প্রত্যেকের জন্য আলাদা স্ক্রিন',
    head('প্রত্যেকের জন্য আলাদা স্ক্রিন', 'প্রত্যেকে দেখেন <em>শুধু নিজের কাজ</em>', 'প্রতিটি ভূমিকার জন্য পরিষ্কার স্ক্রিন, শুধু প্রয়োজনীয় টুল নিয়ে। তাই প্রশিক্ষণ দ্রুত হয়, ভুলও কম হয়।')
    + f'<div class="bls c2" style="margin-top:20px">{ro}</div>'
    + stage(card('admin-01-login', 900, 640, 880, 340, 698, chrome=False, style='left:0;top:0'), 698, 275, 'margin-top:16px'))

# ---------------------------------------------------------------- 23 steps
st = [('১', 'লাইভ ডেমো দেখুন', f'{URL} খুলে প্রতিটি স্ক্রিন চালিয়ে দেখুন। কিছু ইনস্টল করতে হবে না, ঝুঁকিও নেই।', 'circle-play'),
      ('২', 'আমরা ইনস্টল করে দিই', 'ডাটাটেক বিডি একবারেই ইনস্টল করে দেয়। আপনার নিজের সিস্টেম, নিজের তথ্য, আপনার শাখাগুলো।', 'cloud-arrow-up'),
      ('৩', 'মেনু, রেসিপি ও টিম যোগ করুন', 'দাম, সাইজ, টেবিল, উপকরণ ও কর্মীর ভূমিকা একবারই সাজানো হয়।', 'clipboard-list'),
      ('৪', 'কর্মীদের প্রশিক্ষণ দিন', 'প্রতিটি ভূমিকার জন্য বাংলা ও ইংরেজিতে ছোট নির্দেশিকা। প্রত্যেকে শুধু নিজের স্ক্রিন শেখেন।', 'users'),
      ('৫', 'চালু করুন, লাভ দেখুন', 'অর্ডার নেওয়া শুরু করুন। প্রতিদিন ড্যাশবোর্ড দেখুন আর ফুটো ধরা পড়লেই বন্ধ করুন।', 'rocket')]
stp = ''.join(f'<div style="display:flex;gap:16px;align-items:stretch;margin-top:12px"><div style="display:flex;flex-direction:column;align-items:center"><div style="width:46px;height:46px;border-radius:50%;background:var(--blue);color:#fff;display:grid;place-items:center;font-size:22px;font-weight:700;flex:none">{n}</div>' + ('<div style="flex:1;width:3px;background:linear-gradient(var(--blue),var(--sky-soft));margin-top:4px"></div>' if n != '৫' else '') + f'</div><div class="bl" style="flex:1;align-items:center;padding:13px 16px"><div class="bi" style="width:38px;height:38px">{ic(ico)}</div><div><h4 style="font-size:15.5px">{a}</h4><p>{b}</p></div></div></div>' for n, a, b, ico in st)
need = [('cash-register', 'কাউন্টারের জন্য ট্যাবলেট, টাচ মনিটর বা কম্পিউটার'), ('print', 'রসিদের জন্য ৮০ মিমি থার্মাল প্রিন্টার'), ('bell-concierge', 'রান্নাঘরের জন্য একটি স্ক্রিন বা প্রিন্টার'), ('wifi', 'অনলাইন অর্ডার ও এসএমএসের জন্য ইন্টারনেট')]
nd = ''.join(f'<div style="display:flex;gap:9px;align-items:center;font-size:12.8px;font-weight:600;padding:5px 0"><span style="color:var(--sky);font-size:15px">{ic(i)}</span>{t}</div>' for i, t in need)
add('শুরু করুন', 'কীভাবে ডিজিটাল হবেন',
    head('কীভাবে ডিজিটাল হবেন', 'পাঁচটি সহজ ধাপে <em>ডিজিটাল হন</em>', 'প্রযুক্তি জানতে হবে না। ডেমো দিয়ে শুরু করুন, চলুন আপনার রান্নাঘরের গতিতে।')
    + stp
    + f'<div style="display:grid;grid-template-columns:1.1fr 1fr;gap:14px;margin-top:16px"><div class="bl" style="display:block"><h4 style="margin-bottom:4px">যা যা লাগবে</h4>{nd}</div>{callout("handshake", "কোথা থেকে শুরু বুঝতে পারছেন না?", f"{PHONE} নম্বরে ফোন বা হোয়াটসঅ্যাপ করুন, সঠিক প্রথম ধাপ আমরা বলে দেব।")}</div>'
    + '<div style="margin-top:16px;font-size:15px;font-weight:700">ধাপে ধাপে, নিজের গতিতে বাড়ান</div><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:8px">' + ''.join(f'<div class="bl" style="display:block;padding:13px 15px"><span class="pill">{a}</span><h4 style="margin-top:6px">{b}</h4><p style="font-size:12.2px">{c}</p></div>' for a, b, c in [('ধাপ ১', 'কাউন্টার ও রান্নাঘর', 'পিওএস, রসিদ, কিচেন ডিসপ্লে ও দৈনিক বিক্রি।'), ('ধাপ ২', 'স্টক ও খরচ', 'রেসিপি, অপচয়, ক্রয় ও খাবারের খরচ।'), ('ধাপ ৩', 'বিক্রি বাড়ানো', 'ওয়েবসাইট, কিউআর, ডেলিভারি, লয়্যালটি ও আরও শাখা।')]) + '</div>')

# ---------------------------------------------------------------- 24 price
inc = ['পিওএস, কিচেন ডিসপ্লে ও অফলাইন মোড', 'স্টক, রেসিপি, অপচয় ও ক্রয়', 'অর্ডারের ওয়েবসাইট, কিউআর ও ডেলিভারি টুল', 'ড্যাশবোর্ড, রিপোর্ট ও হিসাবরক্ষণ', 'লয়্যালটি, কুপন, কর্মী ও বেতন']
inc_h = ''.join(f'<div style="display:flex;gap:10px;align-items:center;padding:9px 0;font-size:14.5px;font-weight:600"><span style="color:var(--sky);font-size:15px">{ic("circle-check")}</span>{t}</div>' for t in inc)
add('মূল্য', 'মূল্য ও অফার',
    head('সহজ ও স্বচ্ছ মূল্য', 'ফ্রিতে শুরু করুন, তারপর <em>মাসে ১,০০০ টাকা</em>', 'লুকানো খরচ নেই। যা দেখছেন, তা-ই দিচ্ছেন।')
    + f'<div class="abs" style="left:48px;right:48px;top:300px;display:grid;grid-template-columns:1.15fr 1fr;gap:16px">'
    f'<div style="border-radius:24px;padding:28px;background:linear-gradient(145deg,#0B63CE,#1A78E6);color:#fff"><span class="pill" style="background:rgba(255,255,255,.2);color:#fff">ছাড়কৃত মাসিক মূল্য</span><div style="font-size:72px;font-weight:700;line-height:1.05;margin-top:12px">১,০০০</div><div style="font-size:16px;margin-top:2px">টাকা প্রতি মাসে</div><div style="margin-top:6px;font-size:13.5px;opacity:.9">নিয়মিত মূল্য ৫,৭০০ টাকা, প্রতি মাসে আপনি ৪,৭০০ টাকা কম দেন।</div></div>'
    f'<div style="display:grid;gap:12px"><div class="bl" style="align-items:center"><div class="bi" style="background:var(--green);color:#fff">{ic("hand-holding-dollar")}</div><div><h4>প্রথম মাস ফ্রি</h4><p>ইনস্টলেশনের পর থেকে আপনার ফ্রি মাস শুরু।</p></div></div>'
    f'<div class="bl" style="align-items:center"><div class="bi">{ic("wand-magic-sparkles")}</div><div><h4>২,৫০০ টাকা ইনস্টলেশন</h4><p>এককালীন ফি। এক বছর ব্যবহারের পর বাতিল করলে ফেরতযোগ্য।</p></div></div>'
    f'<div class="bl" style="align-items:center"><div class="bi">{ic("calendar-check")}</div><div><h4>বার্ষিক প্ল্যানে ২ মাস ফ্রি</h4><p>দশ মাসের টাকা দিন, ব্যবহার করুন বারো মাস।</p></div></div></div></div>'
    + f'<div class="abs" style="left:48px;right:48px;top:600px;display:grid;grid-template-columns:1fr 1fr;gap:16px"><div class="bl" style="display:block;padding:18px 20px"><h4 style="font-size:16px;margin-bottom:6px">মাসিক মূল্যেই অন্তর্ভুক্ত</h4>{inc_h}</div>'
    f'<div class="bl" style="display:block;padding:18px 20px"><h4 style="font-size:16px;margin-bottom:6px">কখন কত দেবেন</h4>'
    f'<div style="display:flex;justify-content:space-between;padding:13px 0;border-bottom:1px dashed var(--line);font-size:14.5px"><span>ইনস্টলেশনের সময়</span><b>২,৫০০ টাকা, একবার</b></div>'
    f'<div style="display:flex;justify-content:space-between;padding:13px 0;border-bottom:1px dashed var(--line);font-size:14.5px"><span>প্রথম মাস</span><b style="color:var(--green)">ফ্রি</b></div>'
    f'<div style="display:flex;justify-content:space-between;padding:13px 0;border-bottom:1px dashed var(--line);font-size:14.5px"><span>মাসিক প্ল্যান</span><b>১,০০০ টাকা</b></div>'
    f'<div style="display:flex;justify-content:space-between;padding:13px 0;font-size:14.5px"><span>বার্ষিক প্ল্যান</span><b>২ মাস ফ্রি</b></div></div></div>'
    + f'<div class="abs" style="left:48px;right:48px;bottom:84px">{callout("phone", "আপনার ফ্রি ডেমো বুক করুন", f"{PHONE} (ফোন ও হোয়াটসঅ্যাপ) · {EMAIL}")}</div>')

# ---------------------------------------------------------------- 25 FAQ
faq = [('আমি কম্পিউটার ভালো পারি না।', 'প্রতিটি ভূমিকা শুধু নিজের টুলসহ সহজ স্ক্রিন দেখে, আর বাংলা ও ইংরেজিতে ছোট নির্দেশিকা দেওয়া হয়।'),
       ('ইন্টারনেট চলে গেলে কী হবে?', 'পিওএস ও কিচেন স্ক্রিন চলতেই থাকে। নগদ অর্ডার জমা থাকে, সংযোগ ফিরলে সিঙ্ক হয়ে যায়।'),
       ('আমার একাধিক শাখা থাকলে?', 'শাখার ব্যবস্থা আগে থেকেই আছে। ম্যানেজার নিজের শাখা দেখেন, আপনি দেখেন সবগুলো, লাভ পাশাপাশি।'),
       ('বিকাশ ও নগদে টাকা নিতে পারব?', 'হ্যাঁ। নগদ টাকা, বিকাশ, নগদ ও কার্ড, সাথে একাধিক পদ্ধতিতে ভাগ করে পেমেন্টও।'),
       ('ওয়েবসাইট কি আমার লাগবেই?', 'প্রথম দিন থেকে নয়। কাউন্টার ও রান্নাঘর দিয়ে শুরু করুন, চাইলে পরে অনলাইন অর্ডার চালু করুন।'),
       ('তথ্য কার?', 'আপনার। প্রতিটি গ্রাহকের আলাদা ইনস্টলেশন ও ডাটাবেস, সাথে প্রতিদিনের ব্যাকআপ।'),
       ('ডেলিভারি অ্যাপ যুক্ত করা যাবে?', 'যেকোনো ডেলিভারি অ্যাপের অর্ডার প্রথম দিন থেকেই একটি স্ক্রিনে হাতে এন্ট্রি দেওয়া যায়। সরাসরি সংযোগ নির্ভর করে প্রতিটি প্ল্যাটফর্মের পার্টনার অনুমতির ওপর।'),
       ('ভ্যাট ও রসিদের কী হবে?', 'প্রতিটি শাখার জন্য ভ্যাট ও সার্ভিস চার্জ ঠিক করা যায়। স্থানীয় নিয়ম আপনার হিসাবরক্ষকের সাথে মিলিয়ে নিন।')]
fq = ''.join(f'<div class="bl" style="gap:13px;align-items:flex-start;padding:16px 16px"><div class="bi" style="background:var(--blue);color:#fff;font-weight:700;font-size:17px;width:34px;height:34px">?</div><div><h4 style="font-size:15px">{a}</h4><p style="font-size:13.2px">{b}</p></div></div>' for a, b in faq)
add('প্রশ্নোত্তর', 'মালিকদের সাধারণ প্রশ্ন',
    head('জেনে রাখা ভালো', 'সাধারণ প্রশ্নের <em>সংক্ষিপ্ত উত্তর</em>', f'সিদ্ধান্তের আগে নিজে দেখুন। {URL} এ লাইভ ডেমো খুলে প্রতিটি স্ক্রিনে ঘুরে দেখুন।')
    + f'<div class="bls c1" style="margin-top:22px;gap:13px">{fq}</div>')

# ---------------------------------------------------------------- 26 contact
add('যোগাযোগ', '', f'''
<div class="corner tl"></div><div class="corner br"></div>
<div class="abs" style="left:56px;top:54px;display:flex;align-items:center;gap:12px">{mark(46)}<div style="font-family:Poppins;font-weight:600;font-size:26px;line-height:1">DT <span style="color:#38A8FF">Restora</span></div></div>
<div class="abs" style="left:56px;top:150px;width:680px"><div style="font-size:15px;font-weight:700;color:#38A8FF">আপনার পরের পদক্ষেপ</div>
<div style="font-size:46px;line-height:1.2;font-weight:700;margin-top:8px">নিজের আসল হিসাব <span style="color:#38A8FF">দেখতে প্রস্তুত?</span></div>
<p class="lead" style="margin-top:12px;font-size:16.5px;color:#C4D3EA">ফ্রি ডেমো বুক করুন। আপনার মেনু, শাখা আর প্রশ্ন মাথায় রেখে আমরা পুরো সিস্টেম দেখিয়ে দেব।</p></div>
<div class="abs" style="left:56px;top:370px;width:420px;height:300px">{card('admin-02-dashboard', 0, 0, 1800, 1100, 470, style='left:0;top:10px;transform:rotate(-2deg)')}</div>
<div class="abs" style="left:420px;top:350px;width:300px;height:340px">{phone('mobile-03-menu', 170, 0, 1150, style='left:110px;top:0;transform:rotate(4deg)')}</div>
<div class="abs" style="left:56px;right:56px;top:720px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
<div class="bl" style="align-items:center"><div class="bi" style="background:var(--blue);color:#fff">{ic("phone")}</div><div><p style="font-size:11.5px">ফোন</p><h4>{PHONE}</h4></div></div>
<div class="bl" style="align-items:center"><div class="bi" style="background:#25D366;color:#fff">{ic("comments")}</div><div><p style="font-size:11.5px">হোয়াটসঅ্যাপ</p><h4>{PHONE}</h4></div></div>
<div class="bl" style="align-items:center"><div class="bi" style="background:var(--blue);color:#fff">{ic("envelope")}</div><div><p style="font-size:11.5px">ইমেইল</p><h4>{EMAIL}</h4></div></div>
<div class="bl" style="align-items:center"><div class="bi" style="background:var(--blue);color:#fff">{ic("globe")}</div><div><p style="font-size:11.5px">লাইভ ডেমো</p><h4>{URL}</h4></div></div>
<div class="bl" style="grid-column:1/3;align-items:center"><div class="bi" style="background:var(--blue);color:#fff">{ic("location-dot")}</div><div><p style="font-size:11.5px">ঠিকানা</p><h4 style="font-size:14px">{ADDR}</h4></div></div></div>
<div class="abs" style="left:56px;right:56px;bottom:50px;display:flex;justify-content:space-between;align-items:center;font-size:12.5px;color:#9FB6D6"><span>ডাটাটেক বিডি লিমিটেড · সফটওয়্যার যা ব্যবসা চালায়</span><span class="pill" style="font-size:13px">মাসে ১,০০০ টাকা · প্রথম মাস ফ্রি · ২,৫০০ টাকা ইনস্টলেশন</span></div>
''', dark=True, nohead=True)

# ---------------------------------------------------------------- assemble
out = []
for n, (title, html) in enumerate(PAGES, 1):
    out.append(html.replace('{N}', bn(n)))
doc = f'<!doctype html><html lang="bn"><head><meta charset="utf-8"><title>DT Restora প্রোডাক্ট বুক</title><meta name="viewport" content="width=device-width,initial-scale=1"><style>{CSS}</style></head><body>' + ''.join(out) + '</body></html>'
path = os.path.join(HERE, 'dt-restora-product-book-bn.html')
open(path, 'w').write(doc)
print(len(PAGES), 'pages ->', path, round(len(doc) / 1024), 'KB')
