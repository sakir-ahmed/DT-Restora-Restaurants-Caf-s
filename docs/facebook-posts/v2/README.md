# DT Restora posts and reels, v2 (Datatech brand)

Post graphics and reels for the DT Restora product, built with the Datatech BD Ltd. brand kit:

- **Colours:** Midnight Navy `#0A1428`, Datatech Blue `#0B63CE`, Bright Sky `#38A8FF`, Cloud `#F5F7FA`.
- **Fonts:** Poppins for headlines, Inter for body text, Hind Siliguri for Bangla.
- **Product mark and wordmark:** the DT Restora mark from the brand kit (`docs/brand-design/7-Products/dt-restora`), "DT" in the text colour and "Restora" in blue.
- **Common frame:** every post and every reel frame carries the same minimal frame, a thin inset border with Bright Sky or Datatech Blue corner brackets.
- **Contact details:** Datatech BD Ltd., +8801575888775, sales@datatechbd.com, Flat 4-A, Dhumketu Shikder Tower, Holding No. 66, Paschim Monipur, Mirpur, Dhaka-1216. No WhatsApp or personal contact is used.
- **Screenshots:** taken from the restyled app on a freshly seeded database (`docs/marketing/capture-shots.mjs`).

```
posts/    14 category folders, one folder per post with image-NN.png and post.txt (203 + 10 images)
reels/    52 vertical reels (output/Rxxx.mp4 and a thumbnail), specs/ holds the scene lists
_source/  generators for the posts (HTML, Pillow) and the render script
```

## Regenerate

1. Re-capture screenshots (app running with a fresh seeded database): `node docs/marketing/capture-shots.mjs`.
2. Posts: `python3 docs/facebook-posts/v2/_source/build.py`, then `node docs/facebook-posts/v2/_source/render.mjs`, then `python3 docs/facebook-posts/v2/_source/launch_starters.py`.
3. Reels: `python3 docs/facebook-posts/v2/reels/build_specs.py`, then `python3 docs/facebook-posts/v2/reels/reel_mg.py R001` (one reel at a time, needs ffmpeg).
