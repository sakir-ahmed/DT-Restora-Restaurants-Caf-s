# DT Restora: self-driven growth plan for a brand-new page

Owner and face of the brand: Md. Sakir Ahmed, 01304734623 (phone and WhatsApp).
Schedule start: Monday 2026-10-19 (change `schedule_start_date` in the JSON files if you start later).
All budget, reach and conversion figures are planning assumptions. Replace them with real numbers after week 3.

## Principle

A page with no followers does not win on posting alone. People buy a restaurant system from a person they trust, so the plan has two engines:

1. **Person to person (80 percent of early effort):** your personal profile, WhatsApp, groups, phone calls, visits. The page is the proof that the business is real.
2. **Page content (20 percent, then growing):** daily posts and 2 videos a week from `content-plan.json` and `video-plan.json`, boosted only after they prove themselves.

Every post and video ends with one action: message or call Md. Sakir Ahmed on 01304734623, or comment/DM the word DEMO. No links are used, so the number is the funnel.

## Phase 0: before day 1 (2 to 3 days)

- Set profile picture and cover from `brand-assets/`. Add the WhatsApp button and phone number to the page.
- Turn on Messenger instant reply and the saved replies in `../facebook-page/03-messenger-and-replies.md`.
- Publish 9 warm-up posts so the feed is not empty (list in `../facebook-page/05-launch-30-days.md`, section 2).
- Record V-101 (founder intro) and V-102 (60-second tour). These two videos do more for trust than ten graphics.
- Create the lead sheet from `campaign/lead-tracker-template.csv`. Put the post id (for example P012) in the `post_tag` column.

## Phase 1: days 1 to 14, be seen

| Daily action | Target | Why |
|---|---|---|
| Publish the day's post from `content-plan.json` | 1 | Keeps the page alive |
| Share the post to your personal profile and 3 WhatsApp groups | 1 | Your network is the first audience |
| Invite friends and contacts to like the page (Facebook "Invite" button) | 30 to 50 | Fastest way to a first 500 followers |
| Comment helpfully on posts by restaurant owners and in food-business groups | 10 | Gets the name seen without selling |
| Personal WhatsApp or call to a restaurant or cafe owner you know | 10 | Warm conversations turn into demos |
| Reply to every comment and message | within 15 minutes in business hours | Speed is the biggest conversion lever |

Weekly: join 5 more restaurant, cafe and food-business groups (read the rules, never spam). Hold the live demo (V-109) every Friday evening and create the event 2 days before.

## Phase 2: days 15 to 30, be trusted

- Offer a **free 15-minute profit check**: the owner shares last month's rough sales and costs, you show where the leaks are. This is the lead magnet, make it the main call to action in comments and stories.
- Post the objection posts (offline mode, price myth, refundable installation) and answer real questions in short videos (V-110).
- Visit one cluster of restaurants and cafes per week with a tablet and run the demo on site.
- Ask every contact "which post or video did you see?" and record the post id.
- First paid tests (see below).

## Phase 3: days 31 to 90, scale what works

- Every Sunday sort `performance` by `messages` and `leads`. Make 3 more posts like the top 3 and stop the bottom 3 formats.
- Publish the first customer story (V-112) only with the customer's written permission.
- Referral ask to every live customer: one introduction to another owner in exchange for a month discount you decide in advance.
- Partner with equipment suppliers, interior designers, accountants and consultants who meet new restaurant owners.

## Post promotion (boosting)

Rule: **never boost a post that has not proven itself organically.** Wait 24 hours.

| Step | Rule |
|---|---|
| Candidates | Posts marked `boost.recommended: true`, plus any post in the top 20 percent of reach after 24 hours |
| Objective | Messages (opens Messenger or WhatsApp). Not likes, not traffic |
| Budget | Start with 300 to 500 BDT per day for 3 days per post. Total test budget about 5,000 BDT per week (as in the sales plan). Assumption, adjust to what you can spend |
| Audience | Bangladesh, the cities you serve first; interests: restaurants, cafes, food business, restaurant management, small business owners; age 25 to 55. Add a Bangla ad set |
| Creatives | `brand-assets/ad-sq-*.png`, `ad-wide-*.png`, V-113 (15-second Bangla offer video), and the top organic posts |
| Retarget | People who watched 50 percent or more of a video or engaged with the page in 30 days; show the launch offer post (P043) |
| Stop rule | If cost per message is more than twice your best ad after 3 days, pause it |
| Record | Set `boost.is_boosted`, `boost.budget_bdt`, and then `performance` in the JSON |

## Video plan summary (`video-plan.json`)

- 12 carousel posts already have scripts and are turned into Reels from their images (no shooting needed).
- 13 new videos need your camera or a screen recording: founder intro, 60-second tour, profit leaks, kitchen display, offline demo, recipe costing, price myth, full demo, weekly live, owner question series, behind the scenes, customer story, and a paid ad.
- Cadence: 2 Reels a week (Tuesday and Saturday). Record in one batch every Sunday: 30 minutes for talking heads, 30 minutes for screen recordings from the live demo.
- Post every Reel to Facebook Reels, Instagram Reels, YouTube Shorts and TikTok, with captions on screen. Each has its own `is_posted_*` flag.
- Phone setup: vertical, window light, clip-on microphone, plain wall. Speak Bangla for owners, add English captions.

## Easy ways for people to connect

- One number everywhere: 01304734623 (phone, WhatsApp, Messenger).
- Comment "DEMO" on any post and reply with a private message the same hour.
- Weekly live demo with Q&A, same day and time.
- Free 15-minute profit check.
- Demo on the owner's own menu, run at their shop.
- Pin the launch offer post (P043) and the intro post (P000) on the page.

## Weekly review (Sunday, 30 minutes)

1. Update `is_posted_*` flags, `posted_at` and `performance` in `content-plan.json` and `video-plan.json`.
2. Count: followers gained, messages, demos booked, customers signed (see `campaign/weekly-targets.csv`).
3. Pick next week's boosts, move missed posts to new dates.
4. Record the next batch of videos.

Targets for the first 30 days (assumptions): 500 page followers, 100 conversations, 30 demos, 5 customers. The 90-day goal of 100 customers is in `campaign/100-sales-plan-90-days.md`.
