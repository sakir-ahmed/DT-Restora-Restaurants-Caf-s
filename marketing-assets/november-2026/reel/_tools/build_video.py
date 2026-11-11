# -*- coding: utf-8 -*-
"""Builds one animated reel video (1080x1920, 30fps, with voiceover) from reels_data.py.

  python3 _tools/build_video.py R01

Voiceover source, first match wins:
  1. <reel>/audio/<ID>-voiceover.mp3 (or .wav/.m4a): the real voice. Scene timing is detected from the pauses in the file.
  2. <reel>/audio/placeholder/<ID>-NN.aiff: one file per voiceover line from _tools/make_placeholder_voice.sh (macOS "Piya").
  3. none: silent, timing is estimated from the word count.
Every scene (headline, UI card, karaoke subtitle) is timed to the voiceover line it belongs to.
Output: <reel>/video/<ID>-draft.mp4
"""
import glob
import json
import os
import re
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, HERE)
from reels_data import CTA_SCREEN, CTA_VO, DEMO, PHONE, EMAIL, REELS  # noqa: E402
from reel_visuals import VISUALS  # noqa: E402

FPS = 30
LEAD = 0.3        # silence before the first word (placeholder voice)
GAP = 0.45        # silence between lines (placeholder voice)
CTA_HOLD = 1.6    # seconds the end card stays after the last word

# Where the camera looks on each screenshot: (centre x, centre y, zoom), centre as a fraction of the picture.
FOCUS = {
    "admin-desktop/01-dashboard.png": (0.58, 0.30, 1.7),
    "admin-desktop/02-pos-cart.png": (0.78, 0.50, 1.45),
    "admin-desktop/05-kitchen-display.png": (0.50, 0.30, 1.4),
    "admin-desktop/12-recipes.png": (0.50, 0.32, 1.6),
    "admin-desktop/28-reports.png": (0.50, 0.28, 1.7),
    "admin-desktop/30-branches.png": (0.50, 0.22, 1.7),
}
DEFAULT_FOCUS = (0.55, 0.35, 1.5)


def run(cmd, **kw):
    return subprocess.run(cmd, check=True, **kw)


def duration(path):
    r = run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", path], capture_output=True, text=True)
    return float(r.stdout.strip())


def segments_from_file(path, n, lines):
    """Split one voiceover file into n speech segments.

    The voice also pauses at commas, so the longest pauses are not enough. Instead each line boundary is expected where
    the cumulative word/character share says, and is snapped to the nearest pause (longer pauses win ties).
    """
    r = subprocess.run(["ffmpeg", "-i", path, "-af", "silencedetect=noise=-38dB:d=0.15", "-f", "null", "-"], capture_output=True, text=True)
    total = duration(path)
    sil = []
    start = None
    for line in r.stderr.splitlines():
        m = re.search(r"silence_start: ([\d.]+)", line)
        if m:
            start = float(m.group(1))
        m = re.search(r"silence_end: ([\d.]+) \| silence_duration: ([\d.]+)", line)
        if m and start is not None:
            sil.append((start, float(m.group(1))))
            start = None
    speech_start = sil[0][1] if sil and sil[0][0] < 0.05 else 0.0
    speech_end = sil[-1][0] if sil and abs(sil[-1][1] - total) < 0.3 else total
    inner = [s for s in sil if s[0] > speech_start + 0.1 and s[1] < speech_end - 0.1]
    if len(inner) < n - 1:
        return None
    weights = [len(l) + 3 for l in lines]
    tot = sum(weights)
    edges, acc, used = [speech_start], 0, set()
    for k in range(n - 1):
        acc += weights[k]
        want = speech_start + (speech_end - speech_start) * acc / tot
        best = min((i for i in range(len(inner)) if i not in used),
                   key=lambda i: abs((inner[i][0] + inner[i][1]) / 2 - want) - 4 * (inner[i][1] - inner[i][0]))
        used.add(best)
        edges.append(best)
    cuts = sorted(inner[i] for i in edges[1:])
    flat = [speech_start] + [x for c in cuts for x in c] + [speech_end]
    return [(flat[2 * i], flat[2 * i + 1]) for i in range(n)]


def segments_by_words(lines, a, b):
    weights = [len(l) + 3 for l in lines]
    s = sum(weights)
    out, acc = [], 0
    for w in weights:
        out.append((a + (b - a) * acc / s, a + (b - a) * (acc + w) / s - 0.15))
        acc += w
    return out


def build_placeholder(rid, folder, n):
    files = sorted(glob.glob(os.path.join(folder, "audio", "placeholder", f"{rid}-*.aiff")))
    if len(files) != n:
        return None
    out = os.path.join(folder, "audio", "placeholder", f"{rid}-voiceover-placeholder.wav")
    inputs, filters, segs, t = [], [], [], LEAD
    for i, f in enumerate(files):
        d = duration(f)
        inputs += ["-i", f]
        filters.append(f"[{i}:a]aresample=44100,aformat=channel_layouts=mono,adelay={int(t * 1000)}:all=1,apad[a{i}]")
        segs.append((t, t + d))
        t += d + GAP
    mix = "".join(f"[a{i}]" for i in range(n)) + f"amix=inputs={n}:normalize=0:duration=longest,volume=1.0[m]"
    run(["ffmpeg", "-y", "-loglevel", "error"] + inputs + ["-filter_complex", ";".join(filters) + ";" + mix, "-map", "[m]", "-t", f"{segs[-1][1] + 1}", out])
    return out, segs


def main(rid):
    reel = next(r for r in REELS if r["id"] == rid)
    folder = glob.glob(os.path.join(ROOT, f"{rid}-*"))[0]
    shots = sorted(glob.glob(os.path.join(folder, "screenshots", "[0-9]*.png")))
    vo_lines = [s[3] for s in reel["scenes"]] + [CTA_VO]
    n = len(vo_lines)

    audio, segs, source = None, None, "silent"
    for ext in ("mp3", "wav", "m4a"):
        p = os.path.join(folder, "audio", f"{rid}-voiceover.{ext}")
        if os.path.exists(p):
            audio, source = p, "final voiceover"
            segs = segments_from_file(p, n, vo_lines)
            if segs is None:
                print("could not find the pauses between lines, splitting by word count")
                segs = segments_by_words(vo_lines, 0.0, duration(p) - 0.3)
            break
    if audio is None:
        ph = build_placeholder(rid, folder, n)
        if ph:
            audio, segs = ph
            source = "placeholder voice (macOS Piya)"
    if segs is None:
        segs, t = [], LEAD
        for l in vo_lines:
            d = max(1.8, len(l.split()) / 2.3)
            segs.append((t, t + d)); t += d + GAP

    # Scene i shows from just before its line starts until just before the next line starts.
    starts = [0.0]
    for i in range(1, n):
        gap = segs[i][0] - segs[i - 1][1]
        starts.append(segs[i][0] - 0.3 if gap > 0.6 else (segs[i][0] + segs[i - 1][1]) / 2)
    total = segs[-1][1] + CTA_HOLD
    if audio:
        total = max(total, 0)
    ends = starts[1:] + [total]

    work = os.path.join(folder, "video", "_work")
    os.makedirs(work, exist_ok=True)
    scenes = []
    for i, (time, shot_key, on_screen, vo) in enumerate(reel["scenes"]):
        vis = (VISUALS.get(rid) or [None] * 99)[i] or {}
        cx, cy, z = vis.get("focus") or FOCUS.get(shot_key, DEFAULT_FOCUS)
        dur = ends[i] - starts[i]
        callouts = [{"text": t, "sx": x, "sy": y, "delay": min(d, max(0.5, dur * 0.6))} for t, x, y, d in vis.get("callouts", [])]
        scenes.append({"kind": "scene", "start": starts[i], "end": ends[i], "head": on_screen, "sub": vo, "shot": shots[i],
                       "cx": cx, "cy": cy, "z": z, "callouts": callouts, "audio_start": segs[i][0], "audio_end": segs[i][1]})
    brand, pill = CTA_SCREEN[0].split(": ", 1)
    scenes.append({"kind": "cta", "start": starts[-1], "end": ends[-1], "head": "", "sub": CTA_VO,
                   "audio_start": segs[-1][0], "audio_end": segs[-1][1],
                   "cta": {"brand": brand, "pill": pill, "url": DEMO, "contact": CTA_SCREEN[2], "email": EMAIL,
                           "price": ["প্রতি ব্রাঞ্চ মাসে ১০০০ টাকা", "প্রথম মাস ও ট্রেনিং ফ্রি"]}})
    spec = os.path.join(work, "spec.json")
    json.dump({"fps": FPS, "total": total, "scenes": scenes}, open(spec, "w"), ensure_ascii=False, indent=1)

    binary = os.path.join(os.environ.get("TMPDIR", "/tmp"), "render_video")
    src = os.path.join(HERE, "render_video.swift")
    if not os.path.exists(binary) or os.path.getmtime(binary) < os.path.getmtime(src):
        subprocess.run(["swiftc", "-O", src, "-o", binary], stderr=subprocess.DEVNULL)  # xcrun cache warnings are harmless
    out = os.path.join(folder, "video", f"{rid}-draft.mp4")
    enc = ["ffmpeg", "-y", "-loglevel", "error", "-f", "rawvideo", "-pix_fmt", "rgba", "-s", "1080x1920", "-r", str(FPS), "-i", "-"]
    if audio:
        enc += ["-i", audio, "-map", "0:v", "-map", "1:a", "-c:a", "aac", "-b:a", "192k", "-af", f"apad,atrim=0:{total}"]
    enc += ["-t", f"{total:.3f}", "-c:v", "libx264", "-preset", "medium", "-crf", "18", "-pix_fmt", "yuv420p", "-movflags", "+faststart", out]
    render = subprocess.Popen([binary, spec], stdout=subprocess.PIPE)
    run(enc, stdin=render.stdout)
    render.wait()
    print(f"wrote {out} ({total:.1f}s, voiceover: {source})")
    for i, (a, b) in enumerate(segs):
        print(f"  line {i + 1}: voice {a:5.1f}-{b:5.1f}s  scene {starts[i]:5.1f}-{ends[i]:5.1f}s")


main(sys.argv[1])
