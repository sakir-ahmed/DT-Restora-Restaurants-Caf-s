# -*- coding: utf-8 -*-
"""What the camera does on each scene of each reel (the text and voiceover live in reels_data.py).

VISUALS["R01"][i] describes scene i (0-based, same order as the scenes in reels_data.py):
  focus:    (x, y, zoom)  where the camera settles, x and y as a fraction of the screenshot (0 to 1), zoom 1.0 to 2.0
  callouts: [(text, x, y, seconds)]  a ring on that point of the screenshot and a bubble that pops in after `seconds`
            (the first callout is also where the cursor clicks). Keep the text short and about what the voice says.
A scene without an entry falls back to FOCUS in build_video.py and has no callouts.
"""

VISUALS = {
    "R01": [
        {"focus": (0.52, 0.36, 1.3), "callouts": [("বিক্রি, লাভ, বাকি: সব তৈরি", 0.52, 0.375, 0.9)]},
        {"focus": (0.52, 0.40, 1.3), "callouts": [("মোট বিক্রি", 0.22, 0.245, 0.9), ("নিট লাভ", 0.52, 0.375, 2.0), ("বাকি টাকা", 0.52, 0.50, 3.1)]},
        {"focus": (0.50, 0.33, 1.2), "callouts": [("নতুন অর্ডার", 0.27, 0.27, 0.8), ("রান্না চলছে", 0.57, 0.42, 1.5), ("পরিবেশনে তৈরি", 0.85, 0.56, 2.2)]},
        {"focus": (0.70, 0.55, 1.3), "callouts": [("প্রতি পদের খরচ", 0.60, 0.575, 1.0), ("লাভ কত শতাংশ", 0.965, 0.70, 2.4)]},
        {"focus": (0.56, 0.32, 1.3), "callouts": [("প্রতি ব্রাঞ্চের লাভ", 0.96, 0.31, 0.6)]},
        {"focus": (0.45, 0.30, 1.3), "callouts": [("৩ ব্রাঞ্চ, এক অ্যাপ", 0.35, 0.26, 0.8)]},
    ],
}
