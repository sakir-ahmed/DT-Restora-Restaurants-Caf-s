# DT Restora social media posts

Contact on every post: Md. Sakir Ahmed, 01304734623. No links in captions.

```
content-plan.json   master tracker: 146 posts (images, captions per platform, schedule, is_posted_* flags, performance)
video-plan.json     25 videos (12 from carousels, 13 new shoots) with scripts and their own is_posted_* flags
growth-plan.md      self-driven growth and promotion plan for a new page
posts/              14 category folders, one folder per post with image-NN.png and post.txt
brand-assets/       covers, banners, profile pictures, stories, ads, video thumbnails
campaign/           90-day sales plan, dated posting calendar, lead tracker, weekly targets
_source/            generator for the images and post.txt (re-run build.py then render.mjs)
```

## Tracking a post

In `content-plan.json` each post has:

- `images[].id` and `images[].file`: image ids and paths (`IMG-012-01`).
- `schedule`: `day_number`, `planned_date`, `best_time_bd`, `phase` (`launch-30`, `scale-90` or `reserve`).
- `planned_platforms`: where it should go.
- `status.is_posted_facebook`, `is_posted_instagram`, `is_posted_linkedin`, `is_posted_x`, `is_posted_google_business`, `is_posted_facebook_story`, `is_posted_instagram_story`, `is_posted_whatsapp_status`, `is_posted_threads`.
- `posted_at` and `post_urls`: fill per platform after posting.
- `content`: caption per platform (copy and paste).
- `boost` and `performance`: promotion and weekly results.
- `cta.lead_source_code`: write this id in the lead tracker `post_tag` column.

Videos in `video-plan.json` have `is_posted_facebook_reel`, `instagram_reel`, `youtube_shorts`, `youtube`, `tiktok`, `facebook_video`, `linkedin_video` and `whatsapp_status`, plus `production.is_recorded` and `is_edited`.

## Notes

- Day 1 is 2026-10-19. Days 1 to 30 follow the Facebook launch plan, days 31 to 90 follow the old 90-day calendar order, and 56 posts are in a `reserve` bank for gaps.
- `post.txt` files were rewritten without links and are generated from the same text as the JSON. If you edit a caption, edit the JSON and `post.txt` together.
- The artwork inside some images still prints the product website. Re-render those images from `_source/` if you want them link-free.

## Facebook scheduling status

126 posts are scheduled on the DT Restora Facebook page in Meta Business Suite: 5 per day at 09:00, 12:30, 16:00, 20:00 and 22:00, from 2026-10-17 to 2026-11-11, English only. 113 single-image posts carry a Send WhatsApp message button. The 12 carousels and the intro post go without the button. `status.is_scheduled_facebook` is true for these; set `status.is_posted_facebook` after each goes live. Instagram, LinkedIn, X, stories, reels and the other platforms are not scheduled yet.
