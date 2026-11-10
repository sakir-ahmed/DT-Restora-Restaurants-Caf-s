# -*- coding: utf-8 -*-
"""Builds scripts/*.md, voiceover/*.txt, voiceover/ALL.txt and the reel index from reels_data.py.

Run from anywhere:  python3 marketing-assets/november-2026/reel/_tools/build_scripts.py
It also reports any screenshot a script points at that is missing from screenshots/.
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, HERE)

from reels_data import BASE_TAGS, CTA_SCREEN, CTA_VO, DEMO, EMAIL, PHONE, REELS  # noqa: E402

SCRIPTS = os.path.join(ROOT, "scripts")
VOICE = os.path.join(ROOT, "voiceover")
SHOTS = os.path.join(ROOT, "screenshots")
os.makedirs(SCRIPTS, exist_ok=True)
os.makedirs(VOICE, exist_ok=True)

missing = []


def end_time(scenes):
    last = scenes[-1][0].split("-")[1]
    return last


def script_md(reel):
    scenes = reel["scenes"]
    lines = []
    lines.append(f"# {reel['id']}: {reel['title']}")
    lines.append("")
    lines.append(f"- **এলাকা:** {reel['area']}")
    lines.append(f"- **দৈর্ঘ্য:** {reel['length']}")
    lines.append(f"- **ফরম্যাট:** ভার্টিকাল ৯:১৬, ১০৮০ x ১৯২০")
    lines.append(f"- **হুক (প্রথম ৩ সেকেন্ড):** {reel['hook']}")
    lines.append(f"- **কেন দরকার:** {reel['why']}")
    lines.append("")
    lines.append("## দৃশ্য ও ভয়েসওভার")
    lines.append("")
    lines.append("| সময় | স্ক্রিনশট | স্ক্রিনে লেখা | ভয়েসওভার |")
    lines.append("| --- | --- | --- | --- |")
    for time, shot, on_screen, vo in scenes:
        if not os.path.exists(os.path.join(SHOTS, shot)):
            missing.append((reel["id"], shot))
        lines.append(f"| {time} | `screenshots/{shot}` | {on_screen or '-'} | {vo} |")
    start_cta = end_time(scenes)
    lines.append(f"| {start_cta} (শেষ ৫ সেকেন্ড) | `screenshots/website-desktop/01-home.png` | " + " / ".join(CTA_SCREEN) + f" | {CTA_VO} |")
    lines.append("")
    lines.append("## শেষের কার্ড (CTA)")
    lines.append("")
    for item in CTA_SCREEN:
        lines.append(f"- {item}")
    lines.append("- প্রতি ব্রাঞ্চ মাসে ১০০০ টাকা। ইনস্টলেশন ২৫০০ টাকা। প্রথম মাস ও ট্রেনিং ফ্রি।")
    lines.append("")
    lines.append("## ক্যাপশন")
    lines.append("")
    lines.append(reel["caption"])
    lines.append("")
    lines.append("ফ্রি ডেমো: https://" + DEMO)
    lines.append(f"কল / হোয়াটসঅ্যাপ: {PHONE}")
    lines.append(f"ইমেইল: {EMAIL}")
    lines.append("")
    lines.append(" ".join(reel["tags"] + BASE_TAGS))
    lines.append("")
    lines.append("## সম্পাদনার নোট")
    lines.append("")
    lines.append("- প্রতিটা দৃশ্যে স্ক্রিনশটটা ধীরে জুম করুন (১০০% থেকে ১১০%), যেন ছবি নড়ছে মনে হয়।")
    lines.append("- স্ক্রিনে লেখা মোটা বাংলা ফন্টে (Hind Siliguri বা Noto Sans Bengali Bold), নিচের তিন ভাগের এক ভাগে রাখুন।")
    lines.append("- হুকের লেখা বড় ও হলুদ বা সাদা, ব্যাকগ্রাউন্ডে হালকা কালো ছায়া দিন।")
    lines.append("- ভয়েসওভারের সঙ্গে বাংলা সাবটাইটেল রাখুন, কারণ অনেকে শব্দ ছাড়া ভিডিও দেখে।")
    lines.append("")
    return "\n".join(lines)


def voice_text(reel):
    parts = [vo for _, _, _, vo in reel["scenes"]]
    parts.append(CTA_VO)
    return "\n".join(parts) + "\n"


index = []
all_voice = []
for reel in REELS:
    name = f"{reel['id']}-{reel['slug']}"
    with open(os.path.join(SCRIPTS, name + ".md"), "w", encoding="utf-8") as f:
        f.write(script_md(reel))
    text = voice_text(reel)
    with open(os.path.join(VOICE, name + ".txt"), "w", encoding="utf-8") as f:
        f.write(text)
    all_voice.append(f"===== {reel['id']}: {reel['title']} =====\n{text}")
    index.append((reel, name))

with open(os.path.join(VOICE, "ALL.txt"), "w", encoding="utf-8") as f:
    f.write("\n".join(all_voice))

rows = ["| # | রিল | এলাকা | দৈর্ঘ্য | হুক | স্ক্রিপ্ট | ভয়েসওভার |", "| --- | --- | --- | --- | --- | --- | --- |"]
for reel, name in index:
    rows.append(f"| {reel['id']} | {reel['title']} | {reel['area']} | {reel['length']} | {reel['hook']} | [scripts/{name}.md](scripts/{name}.md) | [voiceover/{name}.txt](voiceover/{name}.txt) |")

with open(os.path.join(ROOT, "reel-index.md"), "w", encoding="utf-8") as f:
    f.write("# ২০টি রিলের তালিকা\n\n" + "\n".join(rows) + "\n")

print(f"built {len(index)} reels")
if missing:
    print("MISSING screenshots:")
    for rid, shot in missing:
        print(" ", rid, shot)
else:
    print("all referenced screenshots exist")
