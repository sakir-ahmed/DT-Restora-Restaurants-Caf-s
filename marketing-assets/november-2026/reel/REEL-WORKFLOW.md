# Making the next reel (same way as R01)

R01 is the reference. Every other reel is built the same way: **voice first, then relevant screens with full data, then callouts, then the video**. Run commands from the repository root unless noted.

## Rules (do not break these)

1. **Never move, rename or archive a voiceover the user supplies.** It lives at `RNN-.../audio/RNN-voiceover.mp3` (or `.wav`/`.m4a`). If it is not there, look in `audio/archive/` and ask before assuming it is stale. Once R01's audio was filed away as "old" and the video was built without it.
2. **No demo UI in any screenshot.** No top "Free Demo" banner, no bottom-right "Demo guide" button. `_tools/capture.mjs` hides both; check every new shot anyway.
3. **No empty screens.** A shot with "Nothing here yet", an empty table or an empty kitchen is worthless in a video. Fix the data (see below), do not crop around it.
4. **Numbers must look healthy.** No red negative profit, no "-109%" arrows, no 50% food cost in red. Adjust the seed, not the screenshot.
5. **Each scene shows the screen its voiceover line is about.** Line about the kitchen shows the kitchen display with live orders. Line about profit shows a profit report. Not a generic dashboard.
6. **Never use the user's real `database.sqlite`.** All marketing work uses `web-app/database/marketing.sqlite` on port 8001.
7. **No emojis or symbol icons** anywhere, as in the rest of the repo. Bangla text only for on-screen copy and callouts; callouts use words, not hard-coded numbers (the data can change).
8. Keep text out of the Instagram unsafe zones: nothing important in the top 250 px or bottom 380 px of the 1080x1920 frame. The renderer already respects this; do not move the layout.
9. Never invent a customer, review or testimonial. Stories in scripts are dramatisations.

## Files and what they do (all in `_tools/`)

| File | Purpose |
| --- | --- |
| `reels_data.py` | The words: titles, hooks, per-scene on-screen text, voiceover, caption. Also which screenshot each scene uses. |
| `reel_visuals.py` | What the camera does per scene: zoom target and callouts. |
| `build_scripts.py` | Writes each reel folder: `script.md`, `voiceover.txt`, `screenshots/` (copies in scene order). Warns about timing and missing shots. |
| `capture.mjs` | Takes the screenshots into `_library/screenshots/` using Chrome and Playwright. |
| `MarketingShowcaseSeeder.php` | Tops up the demo data (live kitchen, dues, recipes, reservations, SMS, letters, transfers). |
| `prepare_marketing_db.sh` | Builds a fresh `marketing.sqlite`: demo seed plus the top-up. About a minute. |
| `serve_marketing.sh` | Serves the app on `http://localhost:8001` with that database. |
| `build_video.py` | Builds `RNN-.../video/RNN-draft.mp4` (1080x1920, 30 fps, voiceover mixed in). |
| `render_video.swift` | The animation engine (word-by-word headlines, zooming card, callouts, cursor tap, karaoke subtitles, CTA card). |
| `make_placeholder_voice.sh` | Rough macOS voice, only if there is no real audio yet. Never use it for a final video. |

## Steps for reel RNN

### 1. Confirm the voice
- Real audio at `RNN-.../audio/RNN-voiceover.mp3`? If not, ask the user. A placeholder (`bash marketing-assets/november-2026/reel/_tools/make_placeholder_voice.sh RNN`, run in a normal terminal tab because the macOS voice is blocked in the sandbox) is only for checking timing.
- The video times every scene to its voiceover line by detecting pauses in the file and snapping the line cuts to the nearest pause. Fine-tuning is needed only if a cut lands wrong.

### 2. Choose the right screen for every scene
Open the reel's scene table in `reels_data.py`. For each line decide which page proves it, in this order of preference:
1. An existing screenshot in `_library/screenshots/` that is clearly about the line.
2. A new capture: add it to `capture.mjs` (a `go(path)` plus `snap(dir, name)`; reports use `?type=<key>`, see `ReportController`), and put the file in the scene tuple, for example `"reports/food-cost.png"` or `"admin-desktop/12-recipe-edit.png"`.
3. Never reuse the same screen in two scenes of one reel unless the callouts show different parts. Dark mode (`01-dashboard-dark.png`) is good for "night" hooks.

### 3. Make sure the data is full
Check the pages you will use on a running marketing server. If something is empty or looks bad, change `MarketingShowcaseSeeder.php` (plain `DB::table` inserts, times relative to now, Dhaka times stored as UTC), then rebuild:
```bash
bash marketing-assets/november-2026/reel/_tools/prepare_marketing_db.sh
```
The kitchen display only shows `kitchen_status` pending/preparing/ready, so old orders must be `served`. Dues are paid orders with `is_due=1` and a partial payment.

### 4. Capture (in the user's Terminal panel, not the sandbox)
Tab 1, the server (leave it running):
```bash
bash marketing-assets/november-2026/reel/_tools/serve_marketing.sh
```
Tab 2, the shots (the regex limits it to what you need; omit it for everything, which is slow):
```bash
cd marketing-assets/november-2026/reel && SHOTS_URL=http://localhost:8001 SHOTS_LOGIN=owner SHOTS_PASSWORD=Password123 SHOTS_CHANNEL=chrome node _tools/capture.mjs '^(admin-desktop/(05-kitchen)|reports/(food-cost))'
```
`owner` / `Password123` is the seeded local demo account. The capture script selects a branch first, otherwise some pages (stock transfers) are empty for the owner.

Then **look at every new picture** (Read the PNG). Reject any with demo UI, empty states or bad-looking numbers, fix the cause, capture again.

### 5. Write the camera and callouts
In `reel_visuals.py` add `VISUALS["RNN"]`, one entry per scene in order:
```python
{"focus": (0.52, 0.36, 1.3), "callouts": [("মোট বিক্রি", 0.22, 0.245, 0.9), ("নিট লাভ", 0.52, 0.375, 2.0)]}
```
- `focus` is where the camera settles: x, y as fractions of the screenshot, zoom 1.2 to 1.4 (higher crops away the context).
- Each callout is (short Bangla label, x, y of the thing it points at, seconds after the scene starts). The first callout is also where the cursor taps.
- Read the point coordinates off the screenshot (1440x900, so x = pixels / 1440, y = pixels / 900).
- Short scenes (under 3 s) get one callout. Callouts in one scene must not sit at the same height or the bubbles overlap; stagger y (0.27, 0.42, 0.56).
- Labels name the thing the voice says (sales, profit, dues, new order), never a number.

### 6. Build and review
```bash
python3 marketing-assets/november-2026/reel/_tools/build_scripts.py     # refreshes reel folders, prints warnings
python3 marketing-assets/november-2026/reel/_tools/build_video.py RNN   # about a minute
```
Then pull a few frames and look at them:
```bash
ffmpeg -ss 7 -i marketing-assets/november-2026/reel/RNN-*/video/RNN-draft.mp4 -frames:v 1 -vf scale=540:-1 $TMPDIR/f.png
```
Check: the headline words match the voice; the callout points at the right thing and does not cover it; the subtitle highlight follows the voice; the end card shows the link, phone number and price without overlapping the subtitle. The build prints each line's voice and scene times; a scene under about 2 s or a line cut that looks wrong means the audio pauses did not match, so say so to the user.

Send the finished file to the user (`SendUserFile`) and say honestly that playback was not watched, only frames.

### 7. Commit
Add the reel's `video/RNN-draft.mp4`, scripts, screenshots, seeder and capture changes. `video/_work`, `video/_frames` and `audio/placeholder` are git-ignored. Do not commit `marketing.sqlite` or `public/hot.marketing-bak`. The sandbox blocks GitHub, so push from the user's terminal tab.

## Known pitfalls

- **Sandbox:** Chrome, `say` and the network are blocked in Bash. Run the server, capture, placeholder voice and `git push` through the Terminal panel tools. `ffmpeg`, `swiftc`, `python3` and `php` work in Bash (ignore the harmless `xcrun` cache warnings).
- **`curl` to localhost fails in Bash** (network allowlist). That does not mean the server is down; read the server tab instead.
- **The kitchen page runs out of memory** on the full 33,000-order demo with PHP's default 128 MB. `serve_marketing.sh` raises the limit; the real page may need a product fix.
- **One PHP worker hangs** on pages that poll. `serve_marketing.sh` starts eight.
- **`public/hot`:** a stale Vite marker breaks asset loading; the server script moves it aside and restores it on exit.
- **Percent signs in SMS text:** the seeder uses `str_replace`, not `sprintf`.
- **Timezones:** the app stores UTC and shows Asia/Dhaka. Build Dhaka times with `Carbon::now('Asia/Dhaka')...->utc()`.
- **Recipe editor shot:** search for a specific item (`Kacchi Biryani`) before clicking Edit recipe, so it opens with ingredients.
- **Scene length comes from the audio.** Do not stretch scenes to fit more callouts.

## Done when

- The reel plays 25 to 40 seconds with the real voice, every scene shows a relevant, full, clean screen, callouts are readable, and the end card is complete.
- The user has been sent the file and told what was and was not checked.
