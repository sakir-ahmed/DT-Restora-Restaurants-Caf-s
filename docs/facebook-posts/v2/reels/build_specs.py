"""Build reels/specs/Rxxx.json for all reels from reels-plan.json (software-first, silent captioned)."""
import json, re, os
HERE = os.path.dirname(os.path.abspath(__file__))
plan = json.load(open(HERE + '/../../../marketing/social-media-posts/reels-plan.json', encoding='utf-8'))

# shot -> (crop on 1600x880, callout)
F = {
 'profit':   ('dash.jpg', [916, 636, 668, 232], 'Net profit, built up daily'),
 'cards':    ('dash.jpg', [236, 166, 1348, 104], 'Sales, food cost and profit at a glance'),
 'topitems': ('dash.jpg', [236, 636, 668, 232], 'Top selling dishes'),
 'branches': ('dash.jpg', [197, 745, 900, 135], 'Every branch, one screen'),
 'pos':      ('pos-cart.jpg', [1165, 600, 410, 270], 'Tap, pay, print'),
 'split':    ('pos-split.jpg', [1165, 600, 410, 270], 'Cash + bKash + Nagad + card'),
 'paid':     ('pos-paid.jpg', [1165, 560, 410, 310], 'Payment done, receipt ready'),
 'kds':      ('kds.jpg', [240, 70, 900, 430], 'One live kitchen screen'),
 'reorder':  ('ingredients.jpg', [194, 105, 760, 330], 'Reorder alerts'),
 'stockcount': ('stockcount.jpg', [600, 90, 990, 230], 'Count the shelf, see the difference'),
 'wastage':  ('wastage.jpg', [900, 140, 690, 230], 'Every loss logged with a reason'),
 'recipes':  ('recipes.jpg', [190, 50, 1000, 400], 'Cost of every dish'),
 'payroll':  ('payroll.jpg', [900, 105, 690, 320], 'Attendance to salary slip'),
 'loyalty':  ('loyalty.jpg', [190, 100, 900, 260], 'Points on every order'),
 'coupons':  ('coupons.jpg', [190, 100, 900, 230], 'Coupons you control'),
 'delivery': ('delivery.jpg', [190, 100, 1100, 250], 'Assign riders, track trips'),
 'aggr':     ('aggr.jpg', [200, 100, 1000, 210], 'Delivery apps and commission'),
 'website':  ('w-home.jpg', [230, 40, 1140, 520], 'Your own ordering website'),
 'accounting': ('accounting.jpg', [900, 150, 690, 260], 'Profit and loss, ready'),
 'activity': ('activity.jpg', [200, 110, 1200, 300], 'Who changed what, and when'),
 'staff':    ('staff.jpg', [190, 130, 1100, 350], 'Each role sees only its job'),
 'sync':     ('sync.jpg', [300, 100, 1000, 300], 'Offline orders sync cleanly'),
 'dishmedia': ('dishmedia.jpg', [190, 300, 900, 420], 'Photos and videos for every dish'),
 'letters':  ('letters.jpg', [190, 50, 1000, 430], 'HR letters on your letterhead'),
 'attendance': ('attendance.jpg', [190, 50, 1000, 430], 'Daily attendance for payroll'),
 'customers': ('customers.jpg', [190, 50, 1000, 430], 'Know your regular customers'),
 'reports':  ('reports.jpg', [190, 50, 1000, 430], 'Reports for PDF and Excel'),
 'tables':   ('tables.jpg', [190, 50, 1000, 430], 'Tables and QR ordering'),
 'purchases': ('purchases.jpg', [190, 50, 1000, 430], 'Purchases and supplier prices'),
}
# ordered rules on title: primary, secondary, [third]
RULES = [
 (r'live|launch|Launch|Local team|Comment DEMO|Is the first month|33 taka|Training|demo|Myth|live\?', ['cards','kds','pos']),
 (r'missing profit|Sales is not|calculated|profit', ['profit','cards']),
 (r'3 questions|morning', ['cards','profit']),
 (r'branch|three kitchens', ['branches','profit']),
 (r'Split', ['split','paid']),
 (r'learns the POS|POS in|choose a restaurant POS', ['pos','paid']),
 (r'Kitchen display|Peak hour', ['kds','cards']),
 (r'rice ran out|reorder|Reorder', ['reorder','kds']),
 (r'stock checklist|FIFO', ['stockcount','reorder']),
 (r'waste|Waste', ['wastage','profit']),
 (r'Recipe costing|plate|Food cost percentage|Which dish|Menu pricing', ['recipes','topitems']),
 (r'cash drawer|shift|Cash handling', ['paid','activity']),
 (r'Discount|void|Void', ['activity','paid']),
 (r'payroll|Payroll|attendance', ['payroll','attendance']),
 (r'HR letters', ['letters','payroll']),
 (r'Loyalty|loyalty', ['loyalty','coupons']),
 (r'delivery apps|Delivery|Rider|rider', ['aggr','delivery']),
 (r'ordering website|online|website|QR|Photos|photos', ['website','dishmedia']),
 (r'accountant|Month end|Accounting|Reports', ['accounting','reports']),
 (r'internet|offline', ['sync','pos']),
 (r'Roles|roles', ['staff','activity']),
 (r'Refundable|Google|FOREVER', ['cards','accounting']),
]
TRUST = ["Your own system and database","Daily backups of your data","Installation refundable after one year","Every void and discount is logged","Bangla and English screens and guides","Try the free demo before you decide","We install it for you, local support"]
TRUST2 = {0:["Your own system and database","Daily backups of your data","Installation refundable after one year"],
          1:["Every void and discount is logged","Your own database, daily backups","Bangla and English screens"],
          2:["We install it for you","Local support, Bangla and English","First month free after installation"]}

def hook_lines(text):
    from PIL import ImageFont
    f = ImageFont.truetype('/System/Library/Fonts/Avenir Next.ttc', 120, index=8)
    words, lines, cur = text.split(), [], ''
    for w in words:
        t = (cur + ' ' + w).strip()
        if f.getlength(t) <= 900: cur = t
        else: lines.append(cur); cur = w
    lines.append(cur)
    return lines[:4]

def sentences(vo):
    s = re.split(r'(?<=[.?!])\s+', vo.strip())
    return [x.strip() for x in s if x.strip()]

def feats(title):
    for rx, keys in RULES:
        if re.search(rx, title, re.I): return keys
    return ['cards', 'profit']

def chunk(sents, n):
    # split sentences into n captions, balanced
    if n == 1: return [' '.join(sents)]
    out, per = [], max(1, round(len(sents) / n))
    for i in range(n):
        part = sents[i * per:(i + 1) * per] if i < n - 1 else sents[i * per:]
        out.append(' '.join(part) if part else sents[-1])
    return out

os.makedirs(HERE + '/specs', exist_ok=True)
for r in plan['reels']:
    rid = r['id']
    keys = feats(r['title'])
    vo = sentences(r['script']['voice_over'])
    hook = r['script']['hook_0_3s']
    body = vo[1:] if len(vo) > 1 else vo
    caps = chunk(body, len(keys))
    scenes = [{'kind':'problem','title':hook_lines(hook),'small':'','caption':vo[0] if len(vo[0])<90 else hook,'dur':3.8}]
    for k, cap in zip(keys, caps):
        shot, crop, call = F[k]
        words = len(cap.split())
        scenes.append({'kind':'screen','shot':shot,'crop':crop,'callout':call,'caption':cap,'dur':round(max(4.4, min(8.0, words * 0.42 + 1.6)), 1)})
    tl = TRUST2[int(rid[1:]) % 3]
    scenes.append({'kind':'trust','lines':tl,'caption':'Owners rely on it because it is theirs.','dur':4.0})
    scenes.append({'kind':'phone','caption':'Free demo. Call or email us.','dur':4.0})
    scenes = json.loads(json.dumps(scenes).replace('Or call and WhatsApp us directly.', 'Or call us directly.'))
    json.dump({'voice':'none','scenes':scenes}, open(HERE + '/specs/%s.json' % rid, 'w'), indent=1)
print('specs', len(plan['reels']))
