# -*- coding: utf-8 -*-
"""Builds scripts/*.md, voiceover/*.txt, voiceover/ALL.txt and the reel index from reels_data.py.

Run from anywhere:  python3 marketing-assets/november-2026/reel/_tools/build_scripts.py
It also checks, and prints warnings for:
  - a screenshot a script points at that is missing from screenshots/
  - a voiceover line that is too long for its scene (about 2.6 words per second)
  - scene times that are not back to back
  - a reel that never says the product name "ডিটি রেস্টোরা" in the voiceover
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, HERE)

from reels_data import BASE_TAGS, BRAND, CTA_SCREEN, CTA_SECONDS, CTA_VO, DEMO, EMAIL, PHONE, REELS  # noqa: E402

SCRIPTS = os.path.join(ROOT, "scripts")
VOICE = os.path.join(ROOT, "voiceover")
SHOTS = os.path.join(ROOT, "screenshots")
os.makedirs(SCRIPTS, exist_ok=True)
os.makedirs(VOICE, exist_ok=True)

WORDS_PER_SECOND = 2.7
SPOKEN_BRAND = "ডিটি রেস্টোরা"
BN = str.maketrans("০১২৩৪৫৬৭৮৯", "0123456789")
TO_BN = str.maketrans("0123456789", "০১২৩৪৫৬৭৮৯")

warnings = []
missing = []


def seconds(label):
    m, s = label.translate(BN).split(":")
    return int(m) * 60 + int(s)


def to_bn(n):
    return str(n).translate(TO_BN)


def clock(total):
    return to_bn(f"{total // 60}:{total % 60:02d}")


def scene_span(time):
    a, b = time.split("-")
    return seconds(a), seconds(b)


def reel_seconds(reel):
    return scene_span(reel["scenes"][-1][0])[1]


def total_seconds(reel):
    return reel_seconds(reel) + CTA_SECONDS


def check(reel):
    prev_end = 0
    for time, shot, on_screen, vo in reel["scenes"]:
        start, end = scene_span(time)
        if start != prev_end:
            warnings.append(f"{reel['id']} {time}: scene does not start where the previous one ended ({clock(prev_end)})")
        prev_end = end
        budget = (end - start) * WORDS_PER_SECOND
        words = len(vo.split())
        if words > budget + 1:
            warnings.append(f"{reel['id']} {time}: {words} words for {end - start}s (budget about {budget:.0f})")
        if not os.path.exists(os.path.join(SHOTS, shot)):
            missing.append((reel["id"], shot))
    spoken = " ".join(vo for _, _, _, vo in reel["scenes"]) + " " + CTA_VO
    if SPOKEN_BRAND not in spoken:
        warnings.append(f"{reel['id']}: product name never spoken")
    import re
    for _, _, _, vo in reel["scenes"]:
        if re.search(r"(দশের|ছয়ের|এক-এর|এক এর|ষাটের|\d-এর) ?মালিক", vo):
            warnings.append(f"{reel['id']}: unnatural area phrase in voiceover: {vo}")
        if re.search(r"[A-Za-z]", vo):
            warnings.append(f"{reel['id']}: Latin letters in voiceover (TTS may misread): {vo}")
    hook_end = scene_span(reel["scenes"][0][0])[1]
    if hook_end > 4:
        warnings.append(f"{reel['id']}: hook scene is {hook_end}s, keep it at 4s or less")
    if reel["hook"] != reel["scenes"][0][3] and reel["hook"] not in reel["scenes"][0][3]:
        # The hook text must be what the first scene actually says.
        warnings.append(f"{reel['id']}: 'hook' field differs from the first voiceover line")


def script_md(reel):
    scenes = reel["scenes"]
    cta_start = reel_seconds(reel)
    total = total_seconds(reel)
    lines = []
    lines.append(f"# {reel['id']}: {reel['title']}")
    lines.append("")
    lines.append(f"- **এলাকা:** {reel['area']}")
    lines.append(f"- **দৈর্ঘ্য:** প্রায় {to_bn(total)} সেকেন্ড")
    lines.append("- **ফরম্যাট:** ভার্টিকাল ৯:১৬, ১০৮০ x ১৯২০")
    lines.append(f"- **মানবিক সূত্র (মালিকের মনের কথা):** {reel['human']}")
    lines.append(f"- **কেন দরকার:** {reel['why']}")
    lines.append("")
    lines.append("## হুক (প্রথম ৪ সেকেন্ড)")
    lines.append("")
    lines.append(f"**মূল হুক (ভয়েসে ও স্ক্রিনে):** {reel['hook']}")
    lines.append("")
    lines.append(f"**প্রথম ফ্রেম (০ থেকে ০.৫ সেকেন্ড):** {reel['first_frame']}")
    lines.append("")
    lines.append("**A/B টেস্টের বিকল্প হুক (একই রিল, শুধু প্রথম লাইন বদলান):**")
    lines.append("")
    for alt in reel["hook_alts"]:
        lines.append(f"- {alt}")
    lines.append("")
    lines.append("## দৃশ্য ও ভয়েসওভার")
    lines.append("")
    lines.append("| সময় | স্ক্রিনশট | স্ক্রিনে লেখা | ভয়েসওভার |")
    lines.append("| --- | --- | --- | --- |")
    for time, shot, on_screen, vo in scenes:
        lines.append(f"| {time} | `screenshots/{shot}` | {on_screen or '-'} | {vo} |")
    lines.append(
        f"| {clock(cta_start)}-{clock(total)} (শেষ {to_bn(CTA_SECONDS)} সেকেন্ড) | `screenshots/website-desktop/01-home.png` | "
        + " / ".join(CTA_SCREEN)
        + f" | {CTA_VO} |"
    )
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
    lines.append(reel["engage"])
    lines.append("")
    lines.append("ফ্রি ডেমো: https://" + DEMO)
    lines.append(f"হোয়াটসঅ্যাপ / কল: {PHONE}")
    lines.append(f"ইমেইল: {EMAIL}")
    lines.append("")
    lines.append(" ".join(reel["tags"] + BASE_TAGS))
    lines.append("")
    lines.append("## পিন করা কমেন্ট")
    lines.append("")
    lines.append(f"{reel['engage']}")
    lines.append(f"ফ্রি ডেমো: https://{DEMO}")
    lines.append(f"হোয়াটসঅ্যাপ: {PHONE}")
    lines.append("")
    lines.append("## সম্পাদনা ও প্রিমিয়াম লুকের নিয়ম")
    lines.append("")
    lines.append("- প্রথম ফ্রেমেই হুকের লেখা থাকবে। লোগো ইন্ট্রো, ফেডইন বা কালো স্ক্রিন কিছু নয়।")
    lines.append("- প্রতি ২ থেকে ৩ সেকেন্ডে একটা কাট, জুম বা নতুন দৃশ্য। একই ছবি ৩ সেকেন্ডের বেশি স্থির নয়।")
    lines.append("- স্ক্রিনশট ধীরে জুম করুন (১০০% থেকে ১১২%) এবং যে জায়গাটা বলছেন সেখানে হলুদ বা সোনালি গোল বা তীর দিয়ে নজর টানুন।")
    lines.append("- রং: গাঢ় নেভি বা কালো ব্যাকগ্রাউন্ড, একটাই অ্যাকসেন্ট রং (সোনালি), বাকি সাদা। রংধনু নয়।")
    lines.append("- ফন্ট: Noto Sans Bengali Bold বা Hind Siliguri Bold। এক দৃশ্যে সর্বোচ্চ ৭ শব্দ, নিচের তিন ভাগের এক ভাগে।")
    lines.append("- সাবটাইটেল সব সময় চালু, কারণ বেশির ভাগ মানুষ শব্দ ছাড়া দেখে। ভয়েসের সঙ্গে শব্দে শব্দে মিলিয়ে।")
    lines.append(f"- প্রোডাক্টের নাম \"{BRAND}\" স্ক্রিনে অন্তত দুইবার: একবার মাঝখানে (ছোট লোগো বা লেখা), একবার শেষের কার্ডে।")
    lines.append("- ব্যাকগ্রাউন্ড মিউজিক খুব নিচু, ভয়েসের নিচে। এক সেকেন্ডের নীরবতা দিন হুকের ঠিক পরে, তারপর উত্তর।")
    lines.append("- ফোনে তোলা বা সিনেমাটিক একটা ছোট ক্লিপ (হাত, দোকান, কাপ, শাটার) প্রথম ২ সেকেন্ডে রাখলে মানুষের সংযোগ বাড়ে। না পেলে প্রথম ফ্রেমের লেখাই যথেষ্ট।")
    lines.append("")
    return "\n".join(lines)


def voice_text(reel):
    parts = [vo for _, _, _, vo in reel["scenes"]]
    parts.append(CTA_VO)
    return "\n".join(parts) + "\n"


index = []
all_voice = []
for reel in REELS:
    check(reel)
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

rows = [
    "| # | রিল | এলাকা | সময় | মানবিক হুক | স্ক্রিপ্ট | ভয়েসওভার |",
    "| --- | --- | --- | --- | --- | --- | --- |",
]
for reel, name in index:
    rows.append(
        f"| {reel['id']} | {reel['title']} | {reel['area']} | {to_bn(total_seconds(reel))} সেকেন্ড | {reel['hook']} | "
        f"[scripts/{name}.md](scripts/{name}.md) | [voiceover/{name}.txt](voiceover/{name}.txt) |"
    )

with open(os.path.join(ROOT, "reel-index.md"), "w", encoding="utf-8") as f:
    f.write("# ২০টি রিলের তালিকা\n\nপ্রতিটা রিল একটা মানবিক মুহূর্ত দিয়ে শুরু হয়, ফিচার দিয়ে নয়। বিস্তারিত কৌশল: [00-brief/strategy.md](00-brief/strategy.md)\n\n" + "\n".join(rows) + "\n")

print(f"built {len(index)} reels")
if missing:
    print("MISSING screenshots:")
    for rid, shot in missing:
        print(" ", rid, shot)
else:
    print("all referenced screenshots exist")
if warnings:
    print("WARNINGS:")
    for w in warnings:
        print(" ", w)
else:
    print("no timing or content warnings")
