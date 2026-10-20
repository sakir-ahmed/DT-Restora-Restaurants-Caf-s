"""Generate social post graphics (HTML), post.txt files, the render manifest and the posting calendar."""
import os, json, re, csv, sys
sys.path.insert(0, os.path.dirname(__file__))
from common import *
import posts_en, posts_bn, posts_q
from tpl2 import TPL2
TPL.update(TPL2)

SOCIAL = os.path.abspath(os.path.join(os.path.dirname(__file__), '..')) + '/'
OUT = SOCIAL + 'posts/'
BUILD = SOCIAL + '_build/'
CONTACT_NAME = 'Datatech BD Ltd.'
CONTACT_PHONE = '+880 1575-888775'
CONTACT_EMAIL = 'sales@datatechbd.com'
CONTACT_EN = f'To book a free demo, contact {CONTACT_NAME}: {CONTACT_PHONE}, {CONTACT_EMAIL}'
CONTACT_BN = f'ফ্রি ডেমোর জন্য যোগাযোগ করুন: ডাটাটেক বিডি লিমিটেড {CONTACT_PHONE}, {CONTACT_EMAIL}'
os.makedirs(BUILD, exist_ok=True)

CATS = {
    'feature': ('01-feature-spotlights', 'Feature spotlight', 'ফিচার'),
    'problem': ('02-problem-solution', 'Owner problem', 'মালিকের সমস্যা'),
    'offer': ('03-offers-pricing', 'Launch offer', 'বিশেষ অফার'),
    'tip': ('04-owner-tips-seo', 'Owner tip', 'মালিকের টিপস'),
    'steps': ('05-carousels-video', 'Step by step', 'ধাপে ধাপে'),
    'faq': ('06-faq-and-myths', 'Quick answer', 'দ্রুত উত্তর'),
    'engage': ('07-engagement', 'Question', 'প্রশ্ন'),
    'bn': ('08-bangla-posts', '', ''),
    'qa': ('09-quick-answers', 'Quick answer', 'দ্রুত উত্তর'),
    'quiz': ('10-quiz-and-polls', 'Quiz', 'কুইজ'),
    'myth': ('11-myth-vs-fact', 'Myth vs fact', 'ভুল ধারণা বনাম সত্য'),
    'chat': ('12-owner-chat', 'Owner chat', 'মালিকের কথোপকথন'),
    'info': ('13-mini-infographics', 'Infographic', 'ইনফোগ্রাফিক'),
}
SHOT_DESC = {
    'pos-cart': 'the POS with cart, discount and payment panel', 'pos-split': 'the POS split payment panel', 'pos-paid': 'a confirmed payment on the POS',
    'pos-empty': 'the empty POS screen', 'pos-dark': 'the POS in dark mode', 'dash': 'the owner dashboard with sales and profit', 'dash-dark': 'the owner dashboard in dark mode',
    'kds': 'the kitchen display with pending, preparing and ready tickets', 'recipes': 'the recipe list with food cost', 'ingredients': 'the ingredient stock list',
    'purchases': 'the purchases and goods received screen', 'wastage': 'the wastage log', 'stockcount': 'the stock count screen', 'transfers': 'the branch stock transfers screen',
    'attendance': 'the staff attendance screen with device support', 'payroll': 'the payroll table with salary slips', 'staff': 'the staff list', 'letters': 'the HR letters screen',
    'loyalty': 'the loyalty settings screen', 'coupons': 'the coupons screen', 'customers': 'the customer directory', 'delivery': 'the delivery dispatch board',
    'aggr': 'the delivery-app channels screen', 'accounting': 'the accounting screen with trial balance', 'reports': 'the sales report', 'expenses': 'the expenses screen',
    'branches': 'the branches screen', 'tables': 'the tables and QR codes screen', 'settings': 'the settings screen', 'activity': 'the activity log', 'sync': 'the sync conflicts screen',
    'menu': 'the menu management screen', 'dishmedia': 'the dish photos and videos screen', 'blogadmin': 'the blog admin screen', 'careers': 'the careers admin screen', 'orders': 'the orders list',
    'w-home': 'the restaurant website home page', 'w-menu': 'the online menu', 'w-dish': 'a dish page with photo gallery', 'w-gallery': 'the photo gallery', 'w-blog': 'the blog',
    'w-locations': 'the locations page', 'w-careers': 'the careers page', 'w-checkout': 'the online checkout', 'w-track': 'live order tracking', 'w-placed': 'the order placed confirmation',
    'm-home': 'the website on a phone', 'm-menu': 'the menu on a phone', 'm-checkout': 'the checkout on a phone', 'm-dish': 'a dish page on a phone',
}
THEMES = ['dark', 'light', 'amber', 'dark', 'light', 'dark', 'light']
BASE_TAGS = ['DTRestora', 'RestaurantSoftware', 'RestaurantPOS', 'BangladeshRestaurants', 'RestaurantManagement']
TIMES = ['Tue 8:00 pm', 'Wed 12:30 pm', 'Thu 8:00 pm', 'Fri 4:00 pm', 'Sat 8:30 pm', 'Sun 8:00 pm', 'Mon 12:30 pm']


def url(pid, src):
    return f'{SITE}/?utm_source={src}&utm_medium=social&utm_campaign=launch-90&utm_content=post-{pid:03d}'


def hashtags(p, n=6):
    base = [t for t in p['tags']] + BASE_TAGS
    seen = []
    for t in base:
        t = re.sub(r'\W', '', t)
        if t and t.lower() not in [x.lower() for x in seen]:
            seen.append(t)
    return seen[:n]


def load_posts():
    posts = []
    for p in posts_en.POSTS + posts_bn.POSTS + posts_q.POSTS:
        posts.append(p)
    # order: english by category, bangla last; assign ids in category order
    order = ['feature', 'problem', 'offer', 'tip', 'steps', 'faq', 'engage', 'bn', 'qa', 'quiz', 'myth', 'chat', 'info']
    posts.sort(key=lambda p: order.index(p['cat']))
    for i, p in enumerate(posts, 1):
        p['id'] = i
        p['bn'] = p.get('lang') == 'bn'
        p.setdefault('theme', THEMES[i % len(THEMES)])
        folder, le, lb = CATS[p['cat']]
        p['folder'] = folder
        p.setdefault('label', lb if p['bn'] and lb else le)
        p['dir'] = f'{folder}/{i:03d}-{p["slug"]}'
    return posts


def render_slides(p):
    pid = p['id']
    bn = p['bn']
    out = []
    if p.get('slides'):
        total = len(p['slides']) + 2
        out.append(t_cover(p, f'p{pid}-1', bn, total))
        for n, sl in enumerate(p['slides'], 2):
            out.append(t_step(p, f'p{pid}-{n}', bn, n - 1, total - 1, sl))
        out.append(t_cta(p, f'p{pid}-{total}', bn, total))
    else:
        out.append(TPL[p['tpl']](p, f'p{pid}-1', bn))
    return out


def alt_text(p, spec, n=None):
    s = parse_shot(spec)
    d = SHOT_DESC.get(s[0], 'the software screen') if s else 'the software'
    return f'{plain(p["title"])} - screenshot of DT Restora restaurant software showing {d}.'


def build_txt(p, n_images):
    pid = p['id']
    bn = p['bn']
    title = plain(p['title']).replace('\n', ' ')
    sub = plain(p['sub']).replace('\n', ' ')
    tags = hashtags(p, 8)
    tagline = ' '.join('#' + t for t in tags)
    ig_tags = ' '.join('#' + t for t in (hashtags(p, 12) + ['CafeBusiness', 'FoodBusinessBD', 'DigitalBangladesh', 'RestaurantOwners', 'POSSystem'])[:15])
    bullets = '\n'.join('- ' + b for b in p['bullets']) if p.get('bullets') and p['tpl'] not in ('poll',) else ''
    cap = p['caption'].strip()
    wa = WA
    L = []
    L.append(f'POST {pid:03d} | {CATS[p["cat"]][1] or "Bangla post"} | {"Bangla" if bn else "English"}')
    L.append(f'Folder: {p["dir"]}')
    L.append(f'Images: {n_images} ({"carousel / video slides" if n_images > 1 else "single image"}), 1080x1350 (4:5). Works on Facebook, Instagram, LinkedIn and X.')
    L.append(f'Best time to post (Bangladesh time): {TIMES[pid % len(TIMES)]}')
    L.append('')
    # ---------------- Facebook
    L.append('=== FACEBOOK ===')
    L.append(title)
    L.append('')
    L.append(cap)
    if bullets:
        L.append('')
        L.append(bullets)
    L.append('')
    if bn:
        L.append(CONTACT_BN)
    else:
        L.append(CONTACT_EN)
    L.append('')
    L.append(tagline)
    L.append('')
    # ---------------- LinkedIn
    L.append('=== LINKEDIN ===')
    L.append(title)
    L.append('')
    L.append(cap)
    if bullets:
        L.append('')
        L.append('Key points:' if not bn else 'মূল বিষয়:')
        L.append(bullets)
    L.append('')
    if bn:
        L.append('আপনার রেস্টুরেন্ট বা ক্যাফের জন্য সঠিক সমাধান নিয়ে কথা বলতে ইনবক্স করুন।')
        L.append(CONTACT_BN)
    else:
        L.append(f'If you run or advise a restaurant or cafe group, message or call {CONTACT_NAME}: {CONTACT_PHONE}')
    L.append(' '.join('#' + t for t in (hashtags(p, 4) + ['SaaS'])[:5]))
    L.append('')
    # ---------------- X
    tw_tags = ' '.join('#' + t for t in hashtags(p, 2))
    u = f'{CONTACT_NAME} {CONTACT_PHONE}'
    tweet = f'{title}\n\n{sub}\n\n{u}\n{tw_tags}'
    def tlen(t):
        return len(t)
    if tlen(tweet) > 280:
        room = 280 - tlen(f'{title}\n\n\n\n{u}\n{tw_tags}') - 1
        sub2 = sub[:max(0, room)].rsplit(' ', 1)[0] + '.' if room > 20 else ''
        tweet = f'{title}\n\n{sub2}\n\n{u}\n{tw_tags}' if sub2 else f'{title}\n\n{u}\n{tw_tags}'
    L.append('=== X (TWITTER) ===')
    L.append(tweet)
    L.append(f'(characters counted by X: {tlen(tweet)} of 280)')
    if n_images > 1:
        L.append('')
        L.append('Optional thread (one tweet per slide, attach the matching image):')
        for i, sl in enumerate(p['slides'], 1):
            L.append(f'{i}/ {plain(sl["head"])} - {plain(sl["text"])}'[:270])
    L.append('')
    # ---------------- Instagram
    L.append('=== INSTAGRAM ===')
    L.append(title)
    L.append('')
    L.append(cap)
    L.append('')
    L.append(CONTACT_EN + ' (or DM "DEMO").' if not bn else CONTACT_BN)
    L.append('')
    L.append(ig_tags)
    L.append('')
    # ---------------- Google Business
    gbp = (sub + ' ' + (f'Try the free demo. Call {CONTACT_NAME}: {CONTACT_PHONE}.' if not bn else f'ফ্রি ডেমো দেখুন। যোগাযোগ: {CONTACT_NAME} {CONTACT_PHONE}।'))[:300]
    L.append('=== GOOGLE BUSINESS PROFILE POST (max 300 characters) ===')
    L.append(gbp)
    L.append('')
    # ---------------- SEO
    seo_title = (title[:48] + ' | DT Restora') if not bn else (title[:48] + ' | DT Restora')
    meta = (sub + ' ' + cap.split('. ')[0]).replace('\n', ' ')
    meta = meta[:152].rsplit(' ', 1)[0] + '...' if len(meta) > 155 else meta
    L.append('=== SEO (use for a blog post, landing page or video description) ===')
    L.append(f'SEO title: {seo_title}')
    L.append(f'Meta description: {meta}')
    L.append(f'Primary keyword: {p["kw"]}')
    L.append('Secondary keywords: ' + ', '.join([re.sub(r"(?<!^)(?=[A-Z])", " ", t).lower() for t in p['tags']] + ['restaurant management software bangladesh', 'restaurant pos system dhaka']).replace('b kash', 'bkash'))
    L.append(f'Suggested URL slug: /blog/{p["slug"]}')
    L.append('')
    # ---------------- Alt text
    L.append('=== IMAGE ALT TEXT ===')
    if n_images > 1:
        shots = [p.get('shot')] + [s.get('shot') for s in p['slides']] + [None]
        for i, sp in enumerate(shots, 1):
            L.append(f'image-{i:02d}.png: ' + (alt_text(p, sp) if sp else f'DT Restora call to action slide with contact details.'))
    else:
        L.append('image-01.png: ' + alt_text(p, p.get('shot')))
    L.append('')
    # ---------------- Video
    if n_images > 1:
        L.append('=== VIDEO SCRIPT (turn the images into a Reel, Short or LinkedIn video) ===')
        L.append(f'Format: vertical 9:16 or 4:5, about {3 + 4 * len(p["slides"]) + 3} seconds. Show each image full screen, 3 to 4 seconds each, with a gentle zoom.')
        L.append(f'Music: soft upbeat instrumental, low volume. Add captions in {"Bangla" if bn else "English"}.')
        L.append(f'Opening (3s): {title}')
        for i, sl in enumerate(p['slides'], 1):
            L.append(f'Slide {i} (4s) - on screen: {plain(sl["head"])} | voice-over: {plain(sl["text"])}')
        L.append('Closing (3s): ' + ('ফ্রি ডেমো দেখুন, প্রথম মাস ফ্রি। ' + CONTACT_NAME + ' ' + CONTACT_PHONE if bn else 'Try the free demo. First month free after installation. Call ' + CONTACT_NAME + ' ' + CONTACT_PHONE))
        L.append(f'Video title: {title} | DT Restora')
        L.append(f'Video description: {sub} {cap.split(chr(10))[0]} Contact: {CONTACT_NAME} {CONTACT_PHONE}')
        L.append('Video tags: ' + ', '.join([p['kw'], 'restaurant software', 'restaurant pos bangladesh', 'DT Restora']))
        L.append('')
    return '\n'.join(L)


def main():
    posts = load_posts()
    manifest = []
    by_file = {}
    calendar = []
    index = []
    for p in posts:
        secs = render_slides(p)
        by_file.setdefault(p['folder'], []).extend(secs)
        d = OUT + p['dir']
        os.makedirs(d, exist_ok=True)
        n = len(secs)
        for i in range(1, n + 1):
            manifest.append({'file': p['folder'] + '.html', 'id': f'p{p["id"]}-{i}', 'out': f'posts/{p["dir"]}/image-{i:02d}.png', 'w': 1080, 'h': 1350})
        open(d + '/post.txt', 'w').write(build_txt(p, n) + '\n')
        index.append(f'{p["id"]:03d} | {p["dir"]} | {"BN" if p["bn"] else "EN"} | {n} img | {plain(p["title"]).replace(chr(10), " ")}')
    for fname, secs in by_file.items():
        open(BUILD + fname + '.html', 'w').write(page(secs))
    json.dump(manifest, open(BUILD + 'manifest.json', 'w'))
    open(SOCIAL + 'post-index.txt', 'w').write('DT Restora social media pack - post index\n(English 80 percent, Bangla 20 percent)\n\n' + '\n'.join(index) + '\n')
    en = sum(1 for p in posts if not p['bn'])
    print('posts', len(posts), 'english', en, 'bangla', len(posts) - en, 'images', len(manifest))


if __name__ == '__main__':
    main()
