"""Render the 10 launch starter graphics (1080x1350) with Pillow. Run: python3 launch_starters.py"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..')) + '/'
OUT = ROOT + 'social-media-posts/posts/14-launch-starters/'
S = 2
W, H = 1080, 1350
AV = '/System/Library/Fonts/Avenir Next.ttc'
INK, INK2, AMBER, DEEP, SOFT, PAPER, WHITE = '#0F172A', '#1E293B', '#F59E0B', '#B45309', '#FEF3C7', '#F8FAFC', '#FFFFFF'
PHONE, NAME = '+880 1575-888775', 'Md. Sakir Ahmed'


def font(size, w='heavy'):
    idx = {'heavy': 8, 'bold': 0, 'demi': 2, 'med': 5, 'reg': 7}[w]
    return ImageFont.truetype(AV, int(size * S), index=idx)


def hex2rgb(h):
    h = h.lstrip('#')
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


class Card:
    def __init__(self, theme):
        self.theme = theme
        base = {'dark': INK, 'amber': AMBER, 'light': PAPER}[theme]
        self.im = Image.new('RGBA', (W * S, H * S), hex2rgb(base) + (255,))
        glow = {'dark': [(1.0, 0.0, 700, AMBER, 95), (0.0, 1.0, 600, AMBER, 50)],
                'amber': [(0.0, 0.0, 700, '#FDE68A', 160), (1.0, 1.0, 600, DEEP, 60)],
                'light': [(1.0, 0.0, 700, '#FDE68A', 200), (0.0, 1.0, 500, '#FEF3C7', 160)]}[theme]
        for fx, fy, r, col, a in glow:
            layer = Image.new('RGBA', self.im.size, (0, 0, 0, 0))
            d = ImageDraw.Draw(layer)
            cx, cy = fx * W * S, fy * H * S
            d.ellipse([cx - r * S, cy - r * S, cx + r * S, cy + r * S], fill=hex2rgb(col) + (a,))
            layer = layer.filter(ImageFilter.GaussianBlur(160 * S))
            self.im = Image.alpha_composite(self.im, layer)
        self.d = ImageDraw.Draw(self.im)
        self.fg = WHITE if theme == 'dark' else INK
        self.hl = {'dark': AMBER, 'amber': WHITE, 'light': DEEP}[theme]
        self.acc = {'dark': AMBER, 'amber': INK, 'light': INK}[theme]

    # ---- primitives
    def ring(self, cx, cy, r, w=60):
        col = {'dark': (245, 158, 11, 36), 'amber': (255, 255, 255, 70), 'light': (245, 158, 11, 46)}[self.theme]
        layer = Image.new('RGBA', self.im.size, (0, 0, 0, 0))
        d = ImageDraw.Draw(layer)
        d.ellipse([(cx - r) * S, (cy - r) * S, (cx + r) * S, (cy + r) * S], outline=col, width=w * S)
        self.im = Image.alpha_composite(self.im, layer)
        self.d = ImageDraw.Draw(self.im)

    def text(self, x, y, s, size, w='heavy', fill=None, anchor='la', shadow=None):
        f = font(size, w)
        fill = fill or self.fg
        if shadow:
            self.d.text((x * S, (y + shadow[1]) * S), s, font=f, fill=shadow[0], anchor=anchor)
        self.d.text((x * S, y * S), s, font=f, fill=fill, anchor=anchor)

    def width(self, s, size, w='heavy'):
        return self.d.textlength(s, font=font(size, w)) / S

    def wrap(self, s, size, maxw, w='heavy'):
        words, lines, cur = s.split(), [], ''
        for wd in words:
            t = (cur + ' ' + wd).strip()
            if self.width(t, size, w) <= maxw:
                cur = t
            else:
                lines.append(cur)
                cur = wd
        lines.append(cur)
        return lines

    def para(self, x, y, s, size, maxw, w='demi', lh=1.3, fill=None):
        for ln in self.wrap(s, size, maxw, w):
            self.text(x, y, ln, size, w, fill)
            y += size * lh
        return y

    def rrect(self, box, r, fill=None, outline=None, width=2):
        x0, y0, x1, y1 = box
        layer = Image.new('RGBA', self.im.size, (0, 0, 0, 0))
        ImageDraw.Draw(layer).rounded_rectangle([x0 * S, y0 * S, x1 * S, y1 * S], radius=r * S, fill=fill, outline=outline, width=width * S)
        self.im = Image.alpha_composite(self.im, layer)
        self.d = ImageDraw.Draw(self.im)

    # ---- composite blocks
    def header(self, badge):
        mark = Image.open(ROOT + 'logo/dt-restora-mark.png').convert('RGBA')
        k = int(mark.width * 0.06)
        mark = mark.crop((k, k, mark.width - k, mark.height - k)).resize((56 * S, 56 * S), Image.LANCZOS)
        mk = Image.new('L', mark.size, 0)
        ImageDraw.Draw(mk).rounded_rectangle([0, 0, mark.width - 1, mark.height - 1], radius=13 * S, fill=255)
        mark.putalpha(mk)
        self.im.alpha_composite(mark, (72 * S, 62 * S))
        self.d = ImageDraw.Draw(self.im)
        self.text(144, 66, 'DT Restora', 38, 'heavy')
        f = font(24, 'heavy')
        t = badge.upper()
        tw = sum(self.d.textlength(ch, font=f) for ch in t) / S + 3 * (len(t) - 1)
        bw = tw + 52
        x1 = W - 72
        x0 = x1 - bw
        bg = {'dark': AMBER, 'amber': INK, 'light': INK}[self.theme]
        fg = {'dark': INK, 'amber': '#FBBF24', 'light': '#FBBF24'}[self.theme]
        self.rrect((x0, 62, x1, 118), 28, fill=bg)
        cx = x0 + 26
        for ch in t:
            self.d.text((cx * S, 90 * S), ch, font=f, fill=fg, anchor='lm')
            cx += self.d.textlength(ch, font=f) / S + 3

    def footer(self, left, show_phone=True):
        y = H - 64 - 78
        self.d.line([(72 * S, y * S), ((W - 72) * S, y * S)], fill=(148, 163, 184, 90) if self.theme != 'amber' else (15, 23, 42, 70), width=3 * S)
        self.text(72, y + 38, left, 26, 'demi', self.fg + '' if False else None)
        if show_phone:
            col = {'dark': AMBER, 'amber': INK, 'light': DEEP}[self.theme]
            self.text(W - 72, y + 18, PHONE, 40, 'heavy', col, anchor='ra')
            self.text(W - 72, y + 62, NAME, 24, 'demi', None, anchor='ra')

    def chip(self, x, y, label, size=34):
        f = font(size, 'heavy')
        tw = self.d.textlength(label, font=f) / S
        w, h = tw + 60, size + 36
        if self.theme == 'amber':
            self.rrect((x, y, x + w, y + h), 20, fill=INK)
            self.d.text(((x + 30) * S, (y + h / 2) * S), label, font=f, fill='#FBBF24', anchor='lm')
        else:
            self.rrect((x, y, x + w, y + h), 20, fill=(245, 158, 11, 46), outline=AMBER, width=3)
            self.d.text(((x + 30) * S, (y + h / 2) * S), label, font=f, fill=self.fg, anchor='lm')
        return x + w + 16

    def row(self, y, n, title, sub=None, h=None, tsize=44):
        h = h or (150 if sub else 120)
        x0, x1 = 72, W - 72
        if self.theme == 'dark':
            self.rrect((x0, y, x1, y + h), 28, fill=(255, 255, 255, 22), outline=(255, 255, 255, 36), width=2)
        elif self.theme == 'light':
            self.rrect((x0, y + 6, x1, y + h + 6), 28, fill=(15, 23, 42, 14))
            self.rrect((x0, y, x1, y + h), 28, fill=WHITE, outline='#E2E8F0', width=2)
        else:
            self.rrect((x0, y, x1, y + h), 28, fill=(255, 255, 255, 140), outline=(15, 23, 42, 40), width=2)
        nb = {'dark': AMBER, 'amber': INK, 'light': AMBER}[self.theme]
        nf = {'dark': INK, 'amber': '#FBBF24', 'light': INK}[self.theme]
        ny = y + (h - 84) / 2
        self.rrect((x0 + 30, ny, x0 + 114, ny + 84), 24, fill=nb)
        self.d.text(((x0 + 72) * S, (ny + 42) * S), str(n), font=font(46, 'heavy'), fill=nf, anchor='mm')
        tx = x0 + 144
        if sub:
            self.text(tx, y + 24, title, tsize, 'heavy')
            self.text(tx, y + 24 + tsize + 10, sub, 28, 'demi', None)
        else:
            self.d.text((tx * S, (y + h / 2) * S), title, font=font(tsize, 'heavy'), fill=self.fg, anchor='lm')
        return y + h + 20

    def shot(self, name, x, y, w, h, top=0.0):
        im = Image.open(ROOT + 'assets/shots/' + name).convert('RGBA')
        sc = w * S / im.width
        im = im.resize((int(w * S), int(im.height * sc)), Image.LANCZOS)
        bar = 38 * S
        fr = Image.new('RGBA', (int(w * S), int(h * S)), (17, 24, 39, 255))
        fd = ImageDraw.Draw(fr)
        fd.rectangle([0, 0, fr.width, bar], fill='#1E293B')
        for i, c in enumerate(['#EF4444', '#FBBF24', '#22C55E']):
            fd.ellipse([(16 + i * 22) * S, 13 * S, (16 + i * 22 + 13) * S, 26 * S], fill=c)
        crop = im.crop((0, int(top * S), im.width, int(top * S) + fr.height - bar))
        fr.paste(crop, (0, bar))
        mask = Image.new('L', fr.size, 0)
        ImageDraw.Draw(mask).rounded_rectangle([0, 0, fr.width - 1, fr.height - 1], radius=22 * S, fill=255)
        shadow = Image.new('RGBA', self.im.size, (0, 0, 0, 0))
        ImageDraw.Draw(shadow).rounded_rectangle([x * S, (y + 24) * S, (x + w) * S, (y + h + 24) * S], radius=22 * S, fill=(0, 0, 0, 120))
        shadow = shadow.filter(ImageFilter.GaussianBlur(26 * S))
        self.im = Image.alpha_composite(self.im, shadow)
        self.im.paste(fr, (int(x * S), int(y * S)), mask)
        self.d = ImageDraw.Draw(self.im)
        self.d.rounded_rectangle([x * S, y * S, (x + w) * S, (y + h) * S], radius=22 * S, outline=(255, 255, 255, 60), width=3 * S)

    def save(self, slug):
        os.makedirs(OUT + slug, exist_ok=True)
        self.im.convert('RGB').resize((W, H), Image.LANCZOS).save(OUT + slug + '/image-01.png', optimize=True)


def big(c, lines, size, y, colors=None, lh=0.93, shadow=None):
    for i, ln in enumerate(lines):
        col = (colors or {}).get(i, c.fg)
        c.text(72, y, ln, size, 'heavy', col, shadow=shadow if col == c.hl and shadow else None)
        y += size * lh
    return y


# ---------------- cards
def c1():
    c = Card('dark'); c.ring(900, 640, 380); c.header('Now live')
    y = big(c, ['We are', 'LIVE.'], 190, 150, {1: AMBER})
    y = c.para(72, y + 30, "The owner control system for restaurants and cafes in Bangladesh.", 38, 900, 'demi', 1.3)
    x = 72
    for t in ['Sell faster', 'Waste less', 'Know your profit']:
        x = c.chip(x, y + 20, t)
    c.shot('dash-dark.jpg', 72, y + 120, 936, 330)
    c.footer('Restaurant software for Bangladesh'); c.save('144-we-are-live')


def c2():
    c = Card('amber'); c.ring(60, 1180, 350); c.header('Meet us')
    y = big(c, ['One system.', 'Eight jobs.'], 118, 150, {1: WHITE}, shadow=(INK, 6))
    items = ['POS and billing', 'Kitchen display', 'Recipes and stock', 'Staff and payroll', 'Loyalty and coupons', 'Ordering website', 'Delivery tools', 'Accounts and reports']
    y += 44
    for i, t in enumerate(items):
        col, row = i % 2, i // 2
        x0 = 72 + col * 478
        y0 = y + row * 178
        dark = (col + row) % 2 == 0
        c.rrect((x0, y0, x0 + 458, y0 + 158), 28, fill=INK if dark else WHITE)
        c.text(x0 + 26, y0 + 22, '%02d' % (i + 1), 30, 'heavy', AMBER if dark else DEEP)
        ls = c.wrap(t, 38, 400)
        yy = y0 + 158 - 22 - len(ls) * 42
        for ln in ls:
            c.text(x0 + 26, yy, ln, 38, 'heavy', WHITE if dark else INK); yy += 42
    c.footer('No bundle of subscriptions'); c.save('145-meet-dt-restora')


def c3():
    c = Card('dark'); c.ring(900, 100, 380); c.header('Why we built it')
    y = big(c, ['Sales is', 'not profit.'], 176, 150, {1: AMBER})
    y = c.para(72, y + 46, 'Most owners learn the real number weeks too late.', 38, 900, 'demi')
    y += 36
    rows = [('Sales', 640, '#10B981', '100'), ('Food cost', 250, '#F43F5E', ''), ('Waste', 90, '#F43F5E', ''), ('Salary', 170, '#F43F5E', ''), ('Rent, bills', 130, '#F43F5E', ''), ('Profit', 120, AMBER, '???')]
    for n, w, col, v in rows:
        c.text(72, y, n, 34, 'bold', AMBER if n == 'Profit' else None)
        c.rrect((330, y - 2, 330 + w, y + 44), 14, fill=col)
        if v:
            c.text(330 + w + 20, y, v, 34, 'heavy', AMBER if n == 'Profit' else None)
        y += 62
    c.text(72, y + 24, 'We show you that number, every day.', 44, 'heavy')
    c.footer('Illustration only, not real data'); c.save('146-sales-is-not-profit')


def c4():
    c = Card('light'); c.ring(920, 800, 380); c.header('Is it for you?')
    y = big(c, ['If you run a', 'kitchen,', 'yes.'], 126, 150, {1: DEEP})
    y += 44
    for i, (t, s) in enumerate([('Cafes and bakeries', 'One counter or many'), ('Restaurants and fast food', 'Dine-in, takeaway, delivery'), ('Cloud kitchens', 'Delivery-app orders in one place'), ('Multi-branch groups', 'One screen, every outlet')]):
        y = c.row(y, i + 1, t, s, h=136)
    c.footer('Start small. Add modules as you grow.'); c.save('147-is-it-for-you')


def c5():
    c = Card('dark'); c.ring(40, 700, 360); c.header('Follow us')
    y = big(c, ['Here is what', 'to expect.'], 140, 150, {1: AMBER})
    y += 60
    for i, (t, s) in enumerate([('Daily owner tips', 'Waste, stock, cash, profit'), ('Short demos', 'The real system, no slides'), ('Honest answers', 'Ask us anything in the comments'), ('Launch offers', 'First month free after installation')]):
        y = c.row(y, i + 1, t, s)
    c.footer('Tap Follow so you do not miss a post'); c.save('148-what-to-expect')


def c6():
    c = Card('amber'); c.ring(900, 1250, 380); c.header('10-second test')
    y = big(c, ['3 questions.', 'Be honest.'], 140, 150, {1: WHITE}, shadow=(INK, 6))
    y += 70
    for i, t in enumerate(["Do you know today's profit?", "Do you know this week's waste?", 'Does the cash drawer match every night?']):
        y = c.row(y, i + 1, t, None, h=130, tsize=35)
    c.text(72, y + 24, 'Any "no"? Then keep reading.', 44, 'heavy')
    c.footer('Comment your score: 0, 1, 2 or 3'); c.save('149-three-honest-questions')


def c7():
    c = Card('dark'); c.ring(900, 100, 400); c.header('See it')
    y = big(c, ['See it.', 'Do not just', 'read it.'], 104, 150, {1: AMBER, 2: AMBER})
    c.shot('pos-dark.jpg', 72, y + 50, 936, 470)
    c.shot('kds.jpg', 72, y + 50 + 470 + 24, 936, 190, top=0)
    c.footer('POS, kitchen display and more'); c.save('150-see-it-not-read-it')


def c8():
    c = Card('light'); c.ring(60, 1250, 380); c.header('Free demo')
    y = big(c, ['A free demo', 'in 3 steps.'], 140, 150, {1: DEEP})
    y += 60
    for i, (t, s) in enumerate([('Message or call us', 'WhatsApp works best'), ('Pick a time', 'Today or tomorrow'), ('See it on your own menu', 'Your dishes, your branches')]):
        y = c.row(y, i + 1, t, s)
    y += 24
    c.rrect((72, y, W - 72, y + 150), 30, fill=INK)
    c.text(110, y + 36, 'Call or WhatsApp', 32, 'demi', WHITE)
    c.text(110, y + 80, NAME, 36, 'heavy', WHITE)
    c.text(W - 110, y + 75, PHONE, 60, 'heavy', AMBER, anchor='rm')
    c.footer('Free. No pressure. No installation to try.', show_phone=False); c.save('151-free-demo-in-three-steps')


def c9():
    c = Card('dark'); c.ring(900, 900, 380); c.header('Our promise')
    y = big(c, ['Local team.', 'Local support.'], 118, 150, {1: AMBER})
    y = c.para(72, y + 50, 'Built by Datatech BD Ltd for the way Bangladesh restaurants and cafes really work.', 40, 900, 'demi')
    y += 20
    for i, (t, s) in enumerate([('Bangla and English', 'Screens, guides and messages'), ('bKash and Nagad ready', 'Cash, cards and mobile money'), ('We install it for you', 'Your own system and database')]):
        y = c.row(y, i + 1, t, s)
    c.footer('Md. Sakir Ahmed, Managing Director'); c.save('152-local-team-local-support')


def c10():
    c = Card('amber'); c.ring(40, 780, 450); c.header('Say hello')
    c.text(72, 170, 'Comment', 150, 'heavy')
    c.text(72, 310, 'DEMO', 262, 'heavy', WHITE, shadow=(INK, 12))
    y = c.para(72, 700, 'and we will message you a free walkthrough.', 52, 900, 'heavy', 1.2)
    x = 72
    for t in ['Free', 'On your menu', 'No pressure']:
        x = c.chip(x, y + 50, t)
    c.footer('Or call and WhatsApp us directly'); c.save('153-comment-demo')


if __name__ == '__main__':
    for fn in (c1, c2, c3, c4, c5, c6, c7, c8, c9, c10):
        fn()
    print('rendered 10')
