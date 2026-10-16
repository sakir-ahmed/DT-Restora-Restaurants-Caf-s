"""Brand assets: covers, banners, stories, ads, profile pictures and video thumbnails."""
from common import *

SECS = []
MAN = []
NOTES = []


def add(aid, w, h, theme, inner, note, bn=False):
    cls = f'a {theme}{" bn" if bn else ""}'
    SECS.append(f'<section class="{cls}" id="{aid}" style="width:{w}px;height:{h}px">{inner}</section>')
    MAN.append({'file': '00-brand-assets.html', 'id': aid, 'out': f'brand-assets/{aid}.png', 'w': w, 'h': h})
    NOTES.append(f'{aid}.png  ({w}x{h})  {note}')


def lr(aid, w, h, theme, title, sub, shot, note, cta='Try the free demo', bn=False, fs=None, pad=56, shot_w=None, shot_h=None, price=False, fx=0):
    sw = shot_w or int(w * 0.46)
    sh = shot_h or int(h * 0.72)
    fs = fs or int(h * 0.13)
    badge = ''
    if price:
        badge = f'<div style="display:inline-flex;align-items:baseline;gap:12px;margin-top:18px"><span style="font-size:{int(h*0.15)}px;font-weight:800;letter-spacing:-.05em;color:#F59E0B">{"১,০০০" if bn else "1,000"}</span><span style="font-size:{int(h*0.045)}px;font-weight:700;line-height:1.2">{"টাকা<br>প্রতি মাসে" if bn else "BDT<br>per month"}</span></div>'
    left = (f'<div style="display:flex;flex-direction:column;justify-content:center;flex:1;padding:{pad}px 0 {pad}px {pad}px;gap:{int(h*0.03)}px">'
            f'<div class="brand" style="font-size:{int(h*0.05)}px">{mark(int(h*0.075))}<div>DT <span>Restora</span></div></div>'
            f'<h2 class="big" style="font-size:{fs}px">{em(title)}</h2>'
            f'<p style="font-size:{int(h*0.05)}px;line-height:1.4;font-weight:500;opacity:.8">{em(sub)}</p>{badge}'
            f'<div class="swipe" style="align-self:flex-start;font-size:{int(h*0.04)}px;padding:{int(h*0.02)}px {int(h*0.04)}px">{cta}</div></div>')
    right = f'<div style="display:flex;align-items:center;margin-right:{-fx}px;padding-right:{pad if not fx else 0}px"><div class="browser" style="width:{sw}px"><div class="bar"><i></i><i></i><i></i><span>dt-restora.datatechbd.com</span></div>{crop(shot, sw, sh)}</div></div>'
    add(aid, w, h, theme, left + right, note, bn)


def story(aid, theme, title, sub, shot, chips, cta, note, bn=False, price=None):
    ch = ''.join(f'<div class="chip">{ic("check")}{c}</div>' for c in chips)
    pr = ''
    if price:
        pr = f'<div style="display:flex;align-items:baseline;gap:20px"><span style="font-size:190px;font-weight:800;letter-spacing:-.06em;color:#F59E0B;line-height:.9">{price[0]}</span><span style="font-size:36px;font-weight:700;line-height:1.25">{price[1]}</span></div>'
    inner = (f'<div style="padding:250px 72px 240px;display:flex;flex-direction:column;gap:30px;width:100%;height:100%">'
             f'<div class="brand">{mark(52)}<div>DT <span>Restora</span></div></div>'
             f'<h2 class="big" style="font-size:{86 if not bn else 74}px">{em(title)}</h2><p style="font-size:32px;line-height:1.4;font-weight:500;opacity:.8">{em(sub)}</p>{pr}'
             f'<div class="fwrap" style="align-self:center">{frame(shot, 936, 560)}</div><div class="chips">{ch}</div>'
             f'<div class="bar-cta" style="margin-top:auto"><span>{cta}</span>{ic("arrow")}</div></div>')
    add(aid, 1080, 1920, theme, inner, note, bn)


def square(aid, theme, title, sub, shot, cta, note, bn=False, price=None):
    pr = ''
    if price:
        pr = f'<div style="display:flex;align-items:baseline;gap:16px"><span style="font-size:130px;font-weight:800;letter-spacing:-.06em;color:#F59E0B;line-height:.9">{price[0]}</span><span style="font-size:28px;font-weight:700;line-height:1.2">{price[1]}</span></div>'
    inner = (f'<div style="padding:56px 60px 48px;display:flex;flex-direction:column;gap:20px;width:100%;height:100%">'
             f'<div class="hd"><div class="brand" style="font-size:26px">{mark(44)}<div>DT <span>Restora</span></div></div></div>'
             f'<h2 class="big" style="font-size:{66 if not bn else 56}px">{em(title)}</h2><p style="font-size:26px;line-height:1.35;font-weight:500;opacity:.8">{em(sub)}</p>{pr}'
             f'<div class="fwrap" style="margin-top:auto">{frame(shot, 960, 330)}</div>'
             f'<div class="bar-cta" style="font-size:30px;padding:20px 30px"><span>{cta}</span>{ic("arrow")}</div></div>')
    add(aid, 1080, 1080, theme, inner, note, bn)


def logo_square(aid, theme, note):
    if theme == 'dark':
        bg, t, r = 'a dark', '#F59E0B', '#0F172A'
    else:
        bg, t, r = 'a amber', '#0F172A', '#F59E0B'
    SECS.append(f'<section class="{bg}" id="{aid}" style="width:1080px;height:1080px;align-items:center;justify-content:center"><svg style="width:640px;height:640px;--t:{t};--r:{r}"><use href="#mk"/></svg></section>')
    MAN.append({'file': '00-brand-assets.html', 'id': aid, 'out': f'brand-assets/{aid}.png', 'w': 1080, 'h': 1080})
    NOTES.append(f'{aid}.png  (1080x1080)  {note}')


def build():
    # ---- Facebook covers 1640x624 (keep the bottom-left clear for the profile picture)
    lr('fb-cover-01-dashboard', 1640, 624, 'dark', 'Know your *real profit*.', 'POS, kitchen, stock, staff, loyalty, delivery and accounts in one system.', 'dash:0.62,0.55,1.1', 'Facebook page cover. Pin the free demo link in the page button.', cta='Free live demo', fs=76, shot_w=760, shot_h=470, fx=60)
    lr('fb-cover-02-offer', 1640, 624, 'amber', 'Start free. Then *1,000 BDT* a month.', 'First month free after installation. Yearly plan: 2 months free.', 'pos-cart:0.5,0.5,1.0', 'Facebook cover for the launch offer period.', cta='Book a free demo', fs=70, shot_w=760, shot_h=470, fx=60)
    lr('fb-cover-03-pos', 1640, 624, 'light', 'The restaurant system *your team will love*.', 'Fast POS, live kitchen screen, stock control and reports that write themselves.', 'kds:0.55,0.4,1.0', 'Facebook cover, light version.', cta='See it live', fs=70, shot_w=760, shot_h=470, fx=60)
    lr('fb-cover-04-bangla', 1640, 624, 'dark', 'শুধু বিক্রি নয়, জানুন আপনার *আসল লাভ*।', 'পিওএস, রান্নাঘর, স্টক, কর্মী, লয়্যালটি, ডেলিভারি ও হিসাব, সব এক সিস্টেমে।', 'dash:0.62,0.55,1.1', 'Facebook cover in Bangla.', cta='ফ্রি লাইভ ডেমো', bn=True, fs=62, shot_w=760, shot_h=470, fx=60)
    # ---- LinkedIn banners 1584x396
    lr('linkedin-banner-01', 1584, 396, 'dark', 'Restaurant management software for *Bangladesh*.', 'POS, stock, staff, loyalty, delivery, website and accounts.', 'dash:0.62,0.5,1.2', 'LinkedIn company page banner.', cta='dt-restora.datatechbd.com', fs=52, pad=44, shot_w=640, shot_h=260, fx=40)
    lr('linkedin-banner-02', 1584, 396, 'amber', 'Digitalize your restaurant. *Pay 1,000 BDT a month.*', 'First month free after installation.', 'pos-cart:0.5,0.5,1.0', 'LinkedIn banner for offers.', cta='Book a demo', fs=50, pad=44, shot_w=640, shot_h=260, fx=40)
    # ---- X header 1500x500
    lr('x-header-01', 1500, 500, 'dark', 'Run every branch. *Know every number.*', 'DT Restora, the owner control system for restaurants and cafes.', 'dash:0.62,0.5,1.2', 'X (Twitter) header. Keep the left 300px clear of the avatar on mobile.', cta='Free live demo', fs=56, pad=48, shot_w=620, shot_h=330, fx=40)
    lr('x-header-02', 1500, 500, 'light', 'From order to profit, *on autopilot*.', 'POS, kitchen display, stock, payroll and accounts together.', 'kds:0.55,0.4,1.0', 'X header, light version.', cta='See the demo', fs=56, pad=48, shot_w=620, shot_h=330, fx=40)
    # ---- YouTube banner 2560x1440 (safe area is the central 1546x423)
    lr('youtube-banner', 2560, 1440, 'dark', 'Restaurant software that shows your *real profit*.', 'Tutorials, feature tours and tips for restaurant and cafe owners.', 'dash:0.6,0.5,1.0', 'YouTube channel art. Text sits inside the safe area; the rest is background.', cta='Subscribe', fs=120, pad=420, shot_w=900, shot_h=560, fx=-300)
    # ---- profile pictures
    logo_square('profile-logo-dark', 'dark', 'Profile picture for Facebook, Instagram, LinkedIn and X.')
    logo_square('profile-logo-amber', 'amber', 'Alternative profile picture on brand amber.')
    # ---- stories 1080x1920
    story('story-01-offer', 'dark', 'Start free. Then *1,000 BDT* a month.', 'First month free after installation.', 'pos-cart:0.88,0.8,2.2', ['2,500 BDT one-time installation', 'Refundable after one year if you cancel', 'Yearly plan: 2 months free'], 'Book a free demo', 'Story: main offer.')
    story('story-02-demo', 'amber', 'See it before you decide.', 'Open the live demo and click every screen.', 'dash:0.62,0.55,1.1', ['No installation to try', 'Works on phone and laptop'], 'dt-restora.datatechbd.com', 'Story: free demo call to action.')
    story('story-03-pos', 'dark', 'A till your cashier learns in minutes.', 'Tables, hold, split bills and every payment method.', 'pos-cart:0.5,0.5,1.0', ['Cash, bKash, Nagad, card', 'Split one bill', 'Receipts and cash count'], 'Try the POS demo', 'Story: POS feature.')
    story('story-04-kitchen', 'light', 'No more lost paper slips.', 'Orders reach the kitchen screen instantly.', 'kds:0.55,0.4,1.0', ['Live ticket board', 'Time on every ticket'], 'See the kitchen screen', 'Story: kitchen display feature.')
    story('story-05-profit', 'dark', 'Profit, cost by cost.', 'Net sales minus food cost, waste, salary and expenses.', 'dash:0.78,0.68,2.0', ['Per branch', 'Today, week or month'], 'See your real profit', 'Story: dashboard.')
    story('story-06-first-month', 'amber', 'First month free.', 'Try everything with no monthly charge after installation.', 'w-home:0.5,0.4,1.1', ['No lock-in surprises', 'We install it for you'], 'Claim your free month', 'Story: first month free.')
    story('story-07-bangla-offer', 'dark', 'ফ্রিতে শুরু করুন। তারপর মাসে *১,০০০ টাকা*।', 'ইনস্টলেশনের পর প্রথম মাস ফ্রি।', 'pos-cart:0.88,0.8,2.2', ['২,৫০০ টাকা এককালীন ইনস্টলেশন', 'এক বছর পর বাতিল করলে ফেরতযোগ্য', 'বার্ষিক প্ল্যানে ২ মাস ফ্রি'], 'ফ্রি ডেমো বুক করুন', 'Story in Bangla: main offer.', bn=True)
    story('story-08-bangla-demo', 'amber', 'সিদ্ধান্তের আগে নিজে দেখুন।', 'লাইভ ডেমো খুলে প্রতিটি স্ক্রিন চালিয়ে দেখুন।', 'dash:0.62,0.55,1.1', ['ইনস্টল ছাড়াই দেখা যায়', 'ফোন ও ল্যাপটপে চলে'], 'dt-restora.datatechbd.com', 'Story in Bangla: demo call to action.', bn=True)
    # ---- square ads 1080x1080
    square('ad-sq-01-offer', 'dark', 'Start free. Then *1,000 BDT* a month.', 'First month free after installation.', 'pos-cart:0.88,0.8,2.0', 'Book a free demo', 'Facebook and Instagram feed ad.', price=('1,000', 'BDT<br>per month'))
    square('ad-sq-02-profit', 'light', 'See your *real profit* every day.', 'Sales, food cost, waste and salary in one dashboard.', 'dash:0.78,0.68,1.8', 'See the live demo', 'Feed ad: profit angle.')
    square('ad-sq-03-waste', 'amber', 'Stop losing money to *waste*.', 'Recipes, auto stock deduction and a wastage log.', 'recipes:0.5,0.35,1.5', 'Try the demo', 'Feed ad: waste angle.')
    square('ad-sq-04-kitchen', 'dark', 'Orders reach the *kitchen instantly*.', 'Website, QR and counter orders on one live board.', 'kds:0.5,0.4,1.2', 'See it live', 'Feed ad: kitchen angle.')
    square('ad-sq-05-bangla', 'dark', 'আপনার রেস্টুরেন্টের *আসল লাভ* জানুন।', 'পিওএস, স্টক, কর্মী ও হিসাব এক সিস্টেমে।', 'dash:0.78,0.68,1.8', 'ফ্রি ডেমো দেখুন', 'Feed ad in Bangla.', bn=True, price=('১,০০০', 'টাকা<br>প্রতি মাসে'))
    square('ad-sq-06-staff', 'light', 'Attendance to *salary slip*, automatically.', 'Fingerprint device support, advances and payroll.', 'payroll:0.6,0.35,1.5', 'Try the demo', 'Feed ad: staff and payroll angle.')
    # ---- landscape link ads 1200x628
    lr('ad-wide-01-offer', 1200, 628, 'amber', 'Digitalize your restaurant for *1,000 BDT* a month.', 'First month free after installation.', 'pos-cart:0.5,0.5,1.0', 'Link ad / LinkedIn sponsored post (1.91:1).', cta='Book a free demo', fs=58, shot_w=520, shot_h=380)
    lr('ad-wide-02-profit', 1200, 628, 'dark', 'Know your *real profit*, every day.', 'One dashboard for every branch.', 'dash:0.62,0.55,1.1', 'Link ad: profit.', cta='See the demo', fs=58, shot_w=520, shot_h=380)
    lr('ad-wide-03-all-in-one', 1200, 628, 'light', 'POS, stock, staff and website, *all in one*.', 'Built for restaurants and cafes in Bangladesh.', 'w-home:0.5,0.4,1.1', 'Link ad: all in one.', cta='Try it free', fs=58, shot_w=520, shot_h=380)
    lr('ad-wide-04-bangla', 1200, 628, 'dark', 'মাসে *১,০০০ টাকায়* পুরো রেস্টুরেন্ট ডিজিটাল।', 'ইনস্টলেশনের পর প্রথম মাস ফ্রি।', 'pos-cart:0.5,0.5,1.0', 'Link ad in Bangla.', cta='ফ্রি ডেমো', bn=True, fs=50, shot_w=520, shot_h=380)
    # ---- video thumbnails 1280x720
    lr('thumb-01-demo', 1280, 720, 'dark', 'Restaurant *software demo*', 'DT Restora full tour in minutes.', 'dash:0.62,0.55,1.1', 'YouTube thumbnail: full demo.', cta='Watch now', fs=92, shot_w=560, shot_h=420)
    lr('thumb-02-pos', 1280, 720, 'amber', 'POS that *splits bills*', 'Cash, bKash, Nagad and card.', 'pos-cart:0.88,0.8,1.8', 'YouTube thumbnail: POS.', cta='Watch now', fs=92, shot_w=560, shot_h=420)
    lr('thumb-03-profit', 1280, 720, 'light', 'Find your *real profit*', 'Cost by cost, branch by branch.', 'dash:0.78,0.68,1.8', 'YouTube thumbnail: profit.', cta='Watch now', fs=92, shot_w=560, shot_h=420)
    lr('thumb-04-bangla', 1280, 720, 'dark', 'রেস্টুরেন্টের *ডিজিটাল ডেমো*', 'সম্পূর্ণ সিস্টেম বাংলায় বোঝানো।', 'dash:0.62,0.55,1.1', 'YouTube thumbnail in Bangla.', cta='দেখুন', bn=True, fs=80, shot_w=560, shot_h=420)
    readme = ('BRAND ASSETS - DT Restora campaign\n\nSizes follow each platform recommendation. Replace the profile picture and banner on every page before the first post.\n\n' + '\n'.join(NOTES)
              + '\n\nTip: pin the best offer post and the free demo link on every page. Use ad-sq and ad-wide files for paid campaigns, stories for daily reminders.\n')
    return SECS, MAN, readme
