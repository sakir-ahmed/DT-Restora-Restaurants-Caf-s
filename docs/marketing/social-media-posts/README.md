# DT Restora social media posts

Contact on every post: Md. Sakir Ahmed, 01304734623. No links in captions.

```
content-plan.json   master tracker: 146 posts (images, captions per platform, schedule, is_posted_* flags, performance)
video-plan.json     25 videos (12 from carousels, 13 new shoots) with scripts and their own is_posted_* flags
growth-plan.md      self-driven growth and promotion plan for a new page
posts/              13 category folders, one folder per post with image-NN.png and post.txt
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
