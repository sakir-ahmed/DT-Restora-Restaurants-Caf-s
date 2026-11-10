"""Build a motion-graphic reel locally: python3 reel_mg.py R001
Voice-over via macOS `say` (fallback voice), frames drawn with Pillow, encoded with ffmpeg (no drawtext needed)."""
import os, sys, subprocess, json, math, tempfile, shutil
from PIL import Image, ImageDraw, ImageFont, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
BASE = os.path.abspath(os.path.join(HERE, '..'))          # social-media-posts
MARK = os.path.abspath(os.path.join(BASE, '..'))          # docs/marketing
W, H, FPS = 1080, 1920, 30
AV = '/System/Library/Fonts/Avenir Next.ttc'
INK, AMBER, WHITE = (15, 23, 42), (245, 158, 11), (255, 255, 255)
PHONE = '+880 1575-888775'


def font(size, idx=8):
    return ImageFont.truetype(AV, size, index=idx)


def bg():
    im = Image.new('RGB', (W, H), INK)
    glow = Image.new('RGB', (W, H), (0, 0, 0))
    d = ImageDraw.Draw(glow)
    d.ellipse([500, -300, 1500, 700], fill=(120, 75, 5))
    d.ellipse([-500, 1300, 500, 2300], fill=(70, 45, 5))
    glow = glow.filter(ImageFilter.GaussianBlur(220))
    from PIL import ImageChops
    return ImageChops.add(im, glow)


BG = bg()
MARK_IMG = Image.open(MARK + '/logo/dt-restora-mark.png').convert('RGBA')
k = int(MARK_IMG.width * 0.06)
MARK_IMG = MARK_IMG.crop((k, k, MARK_IMG.width - k, MARK_IMG.height - k)).resize((72, 72), Image.LANCZOS)
_m = Image.new('L', MARK_IMG.size, 0)
ImageDraw.Draw(_m).rounded_rectangle([0, 0, 71, 71], radius=17, fill=255)
MARK_IMG.putalpha(_m)


def rounded(im, r):
    m = Image.new('L', im.size, 0)
    ImageDraw.Draw(m).rounded_rectangle([0, 0, im.width - 1, im.height - 1], radius=r, fill=255)
    out = im.convert('RGBA')
    out.putalpha(m)
    return out


def wrap(draw, text, f, maxw):
    lines, cur = [], ''
    for w in text.split():
        t = (cur + ' ' + w).strip()
        if draw.textlength(t, font=f) <= maxw:
            cur = t
        else:
            lines.append(cur); cur = w
    lines.append(cur)
    return lines


def ease(t):
    return 1 - (1 - t) ** 3


def frame(scene, t, dur):
    im = BG.copy().convert('RGBA')
    d = ImageDraw.Draw(im)
    im.alpha_composite(MARK_IMG, (64, 70))
    d.text((152, 84), 'DT Restora', font=font(44), fill=WHITE)
    p = min(1, t / 0.5)
    # visual
    kind = scene['kind']
    if kind == 'image':
        src = scene['img']
        zoom = 1.0 + 0.06 * (t / dur)
        w0 = 960
        w = int(w0 * zoom)
        h = int(src.height * w / src.width)
        pic = rounded(src.resize((w, h), Image.LANCZOS), 36)
        x = (W - w) // 2
        y = 230 + int((1 - ease(p)) * 60)
        a = int(255 * ease(p))
        shadow = Image.new('RGBA', im.size, (0, 0, 0, 0))
        ImageDraw.Draw(shadow).rounded_rectangle([x, y + 18, x + w, y + h + 18], 36, fill=(0, 0, 0, 120))
        shadow = shadow.filter(ImageFilter.GaussianBlur(24))
        im = Image.alpha_composite(im, shadow)
        pic.putalpha(Image.eval(pic.getchannel('A'), lambda v: v * a // 255))
        im.alpha_composite(pic, (x, y))
    elif kind == 'problem':
        d = ImageDraw.Draw(im)
        y = 470 + int((1 - ease(p)) * 40)
        d.rounded_rectangle([64, y - 20, 84, y + 360], 10, fill=(244, 63, 94))
        for i, ln in enumerate(scene['title']):
            col = WHITE if i == 0 else AMBER
            d.text((120, y + i * 175), ln, font=font(128), fill=col + (int(255 * ease(p)),))
        d.text((120, y + len(scene['title']) * 175 + 10), scene.get('small', ''), font=font(50, 5), fill=(203, 213, 225))
    elif kind == 'screen':
        src = scene['img']
        dw, dh = 984, 860
        cx, cy, cw, ch = scene['crop'] or (0, 0, src.width, src.height)
        # target box: crop expanded to the display aspect, centred, clamped inside the image
        ta = dw / dh
        tw = max(cw, ch * ta); th = tw / ta
        if th > src.height:
            th = src.height; tw = th * ta
        tx = min(max(cx + cw / 2 - tw / 2, 0), src.width - tw)
        ty = min(max(cy + ch / 2 - th / 2, 0), src.height - th)
        sw = min(src.width, tw * 1.7); sh = sw / ta
        if sh > src.height:
            sh = src.height; sw = sh * ta
        sx = min(max(cx + cw / 2 - sw / 2, 0), src.width - sw)
        sy = min(max(cy + ch / 2 - sh / 2, 0), src.height - sh)
        z = ease(min(1, max(0, (t - 0.4) / 1.8)))
        bx, by, bw, bh = sx + (tx - sx) * z, sy + (ty - sy) * z, sw + (tw - sw) * z, sh + (th - sh) * z
        view = src.crop((int(bx), int(by), int(bx + bw), int(by + bh))).resize((dw, dh), Image.LANCZOS)
        fx, fy = (W - dw) // 2, 320
        chrome = Image.new('RGBA', (dw, 46), (30, 41, 59, 255))
        cd = ImageDraw.Draw(chrome)
        for i, c in enumerate([(239, 68, 68), (251, 191, 36), (34, 197, 94)]):
            cd.ellipse([20 + i * 26, 16, 34 + i * 26, 30], fill=c)
        cd.text((dw - 30, 23), 'DT Restora', font=font(26, 2), fill=(148, 163, 184), anchor='rm')
        card = Image.new('RGBA', (dw, dh + 46))
        card.paste(chrome, (0, 0)); card.paste(view.convert('RGBA'), (0, 46))
        card = rounded(card, 28)
        shadow = Image.new('RGBA', im.size, (0, 0, 0, 0))
        ImageDraw.Draw(shadow).rounded_rectangle([fx, fy + 16, fx + dw, fy + dh + 62], 28, fill=(0, 0, 0, 130))
        im = Image.alpha_composite(im, shadow.filter(ImageFilter.GaussianBlur(22)))
        card.putalpha(Image.eval(card.getchannel('A'), lambda v: v * int(255 * ease(p)) // 255))
        im.alpha_composite(card, (fx, fy))
        d = ImageDraw.Draw(im)
        # highlight the feature area
        if scene['crop']:
            sc = dw / bw
            hx0, hy0 = fx + (cx - bx) * sc, fy + 46 + (cy - by) * sc
            hx1, hy1 = hx0 + cw * sc, hy0 + ch * sc
            pulse = 4 + int(3 * (1 + math.sin(t * 7)) / 2)
            d.rounded_rectangle([hx0 - 6, hy0 - 6, hx1 + 6, hy1 + 6], 14, outline=AMBER + (255,), width=pulse)
        # callout chip
        f = font(54)
        tw_ = d.textlength(scene['callout'], font=f)
        chip_y = 200 + int((1 - ease(p)) * -30)
        d.rounded_rectangle([(W - tw_) / 2 - 36, chip_y, (W + tw_) / 2 + 36, chip_y + 96], 48, fill=AMBER + (255,))
        d.text((W / 2, chip_y + 48), scene['callout'], font=f, fill=INK, anchor='mm')
    elif kind == 'trust':
        d = ImageDraw.Draw(im)
        a = int(255 * ease(p))
        d.ellipse([W / 2 - 110, 360, W / 2 + 110, 580], fill=AMBER + (a,))
        d.line([(W / 2 - 55, 475), (W / 2 - 12, 520), (W / 2 + 62, 425)], fill=INK + (a,), width=26, joint='curve')
        d.text((W / 2, 680), 'Why owners rely on it', font=font(76), fill=WHITE + (a,), anchor='mm')
        for i, ln in enumerate(scene['lines']):
            y = 810 + i * 130
            lay = Image.new('RGBA', im.size, (0, 0, 0, 0))
            ImageDraw.Draw(lay).rounded_rectangle([90, y, W - 90, y + 104], 30, fill=(255, 255, 255, int(34 * a / 255)), outline=(255, 255, 255, int(80 * a / 255)), width=2)
            im = Image.alpha_composite(im, lay)
            d = ImageDraw.Draw(im)
            d.text((W / 2, y + 52), ln, font=font(46, 2), fill=WHITE + (a,), anchor='mm')
    elif kind == 'title':
        d = ImageDraw.Draw(im)
        lines = scene['title']
        y = 520 + int((1 - ease(p)) * 40)
        for i, ln in enumerate(lines):
            col = AMBER if i == len(lines) - 1 else WHITE
            f = font(150)
            tw = d.textlength(ln, font=f)
            d.text(((W - tw) / 2, y), ln, font=f, fill=col + (int(255 * ease(p)),))
            y += 160
    elif kind == 'phone':
        d = ImageDraw.Draw(im)
        pulse = 1 + 0.02 * math.sin(t * 6)
        d.rounded_rectangle([90, 420, W - 90, 1100], 48, fill=(255, 255, 255, 235))
        d.text((W / 2, 520), 'FREE DEMO', font=font(120), fill=INK, anchor='mm')
        d.text((W / 2, 680), 'Call or WhatsApp', font=font(52, 5), fill=(51, 65, 85), anchor='mm')
        f = font(int(112 * pulse))
        d.text((W / 2, 830), PHONE, font=f, fill=(180, 83, 9), anchor='mm')
        d.text((W / 2, 960), 'Md. Sakir Ahmed', font=font(60), fill=INK, anchor='mm')
        d.text((W / 2, 1040), 'First month free after installation', font=font(40, 5), fill=(51, 65, 85), anchor='mm')
    # caption box
    d = ImageDraw.Draw(im)
    f = font(62)
    lines = wrap(d, scene['caption'], f, 900)
    bh = len(lines) * 78 + 56
    y0 = H - 150 - bh
    ca = int(255 * min(1, t / 0.25))
    box = Image.new('RGBA', im.size, (0, 0, 0, 0))
    ImageDraw.Draw(box).rounded_rectangle([60, y0, W - 60, y0 + bh], 40, fill=(15, 23, 42, int(215 * ca / 255)))
    im = Image.alpha_composite(im, box)
    d = ImageDraw.Draw(im)
    for i, ln in enumerate(lines):
        d.text((W / 2, y0 + 28 + i * 78 + 39), ln, font=f, fill=WHITE + (ca,), anchor='mm')
    # progress bar
    return im.convert('RGB')


def main(rid):
    spec = json.load(open(HERE + '/specs/%s.json' % rid))
    out = BASE + '/reels/output'
    os.makedirs(out, exist_ok=True)
    tmp = tempfile.mkdtemp()
    voice = spec.get('voice', 'Rishi')
    rate = str(spec.get('rate', 165))
    parts, scenes = [], []
    silent = voice == 'none'
    for i, sc in enumerate(spec['scenes']):
        if silent:
            scenes.append((sc, sc['dur'])); continue
        aif = '%s/s%d.aiff' % (tmp, i)
        subprocess.run(['say', '-v', voice, '-r', rate, '-o', aif, sc['vo']], check=True)
        wav = '%s/s%d.wav' % (tmp, i)
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', aif, '-ar', '44100', '-ac', '1', wav], check=True)
        dur = float(subprocess.check_output(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', wav]).decode())
        pad = 0.35
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', wav, '-af', 'apad=pad_dur=%s' % pad, '-t', str(dur + pad), '%s/p%d.wav' % (tmp, i)], check=True)
        parts.append('%s/p%d.wav' % (tmp, i))
        scenes.append((sc, dur + pad))
    vo = tmp + '/vo.wav'
    if silent:
        total_s = sum(d for _, d in scenes) + 0.6
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=mono', '-t', str(total_s), vo], check=True)
    else:
        lst = tmp + '/list.txt'
        open(lst, 'w').write(''.join("file '%s'\n" % p for p in parts))
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', lst, '-c', 'copy', vo], check=True)
    # prepare images
    for sc, _ in scenes:
        if sc['kind'] == 'image':
            sc['img'] = Image.open(BASE + '/' + sc['image']).convert('RGB')
        if sc['kind'] == 'screen':
            sc['img'] = Image.open(MARK + '/assets/shots/' + sc['shot']).convert('RGB')
    total = sum(d for _, d in scenes) + 0.6
    video = tmp + '/v.mp4'
    ff = subprocess.Popen(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-s', '%dx%d' % (W, H), '-r', str(FPS), '-i', '-', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '18', '-preset', 'medium', video], stdin=subprocess.PIPE)
    for sc, dur in scenes:
        n = int(dur * FPS)
        for f in range(n):
            ff.stdin.write(frame(sc, f / FPS, dur).tobytes())
    last = frame(scenes[-1][0], scenes[-1][1], scenes[-1][1])
    for _ in range(int(0.6 * FPS)):
        ff.stdin.write(last.tobytes())
    ff.stdin.close(); ff.wait()
    final = out + '/%s.mp4' % rid
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', video, '-i', vo, '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '160k', '-shortest', '-movflags', '+faststart', final], check=True)
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-ss', '2', '-i', final, '-frames:v', '1', out + '/%s.jpg' % rid], check=True)
    shutil.rmtree(tmp)
    print(final, round(total, 1), 's')


if __name__ == '__main__':
    main(sys.argv[1])
