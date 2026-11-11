# Agent playbook: product marketing reels, Facebook posting and Meta Ads (learned from the DT Restora sessions)

Purpose: this file records what the owner actually did, wanted and corrected across a long working session, so a new AI agent in another project can start with the right habits and do the routine parts without being told. Read all of it before acting. Section 1 and 2 are the owner's intent and style; section 3 onwards are procedures; section 9 lists mistakes that already cost time.

Written 12 Nov 2026. Source project: DT Restora (restaurant management and POS software by Datatech BD Ltd., Dhaka, Bangladesh). The patterns apply to any similar product-marketing work.

---

## 1. Who the owner is and what they are trying to achieve

- A software company owner in Bangladesh selling a SaaS product to restaurant and cafe owners.
- Business goal: qualified WhatsApp inquiries from restaurant owners and managers, then free demos, then paid subscriptions. Real sales matter more than likes or clicks.
- Offer used everywhere (keep identical across all content): 1,000 BDT per branch per month, 2,500 BDT installation **once per business** (confirmed by the owner, not per branch), first month free, training free, free demo always. Contact: WhatsApp and call +880 1575-888775, email sales@datatechbd.com, demo and site https://dt-restora.datatechbd.com.
- Language: natural Bangla first, English only for product and technical terms (POS, KDS, Restaurant Software Bangladesh). Voice is Bangla.
- Rule from the project: never use emojis or Unicode symbol icons anywhere (UI, captions, ad text, commit messages, comments).
- Budget discipline: the Meta Ads daily budget is 200 BDT. Never raise it without explicit approval.

## 2. How the owner works with an agent (read this carefully)

1. **Short, direct instructions, often with typos or loose grammar.** Interpret intent (for example "runes" meant "rules"). If truly ambiguous, ask one short question, otherwise act.
2. **They give a goal and expect execution, not a menu of options.** "Do it faster", "do it and publish it", "commit and push" mean: act, then report.
3. **They notice when you ignore their input and get frustrated.** Example: they supplied ElevenLabs audio, the agent had filed it as "old" and said no audio existed. The owner reacted strongly. Rule: **never move, rename, archive or call "stale" any asset the owner supplied. Use it. If you doubt it matches, use it anyway and say what to check.**
4. **They judge by the visual and audible result, not by logs.** They want videos that look like a polished SaaS marketing video, with real screens, full data and animation relevant to what is said.
5. **They review output in chat.** Send finished files (videos, screenshots) with `SendUserFile` and say plainly what was and was not verified (for example "checked frames, not full playback").
6. **They give staged approvals.** Pattern seen: audit and plan first, then "yes, build it", then "publish it". Treat each as separate. Spending money, posting publicly and publishing ads require the explicit go-ahead for that action (they did give it; do not assume it for the next one).
7. **They commit and push in main when asked** ("add git commit and push in main, all changes"). Commit only when asked; push through the user's own terminal because the sandbox blocks GitHub.
8. **They want documentation of what was learned** so future agents need no instruction (this file, plus `marketing-assets/november-2026/reel/REEL-WORKFLOW.md`).
9. **They value honesty about gaps.** Say what was completed, what is pending approval, what is only a recommendation, and what could not be verified. Never invent performance numbers.

Communication style that worked: a short opening line saying what you are doing when work takes long, a concise final summary with what changed, what to check, and the one decision needed, no padding.

## 3. Project and repository facts

- Monorepo `dt-restora`: `web-app/` (Laravel 13 + Vue SPA, sqlite in dev, demo mode via `IS_DEMO`), `flutter-app/`, `docs/`, `marketing-assets/`.
- Project rules in `CLAUDE.md`: work from inside the app folder, no emojis, bilingual user text (`web-app/lang/en` and `bn`), run pint, phpstan and tests before finishing web changes, do not commit `.env` or build output, ask before assuming when something is unclear.
- Marketing lives in `marketing-assets/november-2026/reel/`. One folder per reel `RNN-slug/` with `script.md`, `voiceover.txt`, `screenshots/` (copies in scene order), `audio/` (the owner's voiceover as `RNN-voiceover.mp3`, old files in `audio/archive/`), `video/` (`RNN-draft.mp4`, `RNN-upload.mp4`). Shared: `00-brief/`, `_library/screenshots/`, `_tools/`, `REEL-WORKFLOW.md`, `reel-index.md`, `ALL-voiceover.txt`.
- Brand and claims sources: `marketing-assets/.../00-brief/offer-and-pricing.md` and `claims-to-verify.md`. Characters in scripts are dramatisations, never presented as real customers.

## 4. Reel production workflow (done for R01, R02, R03; repeat for R04 to R20)

Full commands are in `REEL-WORKFLOW.md`. The shape of the job:

1. **Voice first.** The owner generates the voiceover (ElevenLabs, Bangla) and places it at `RNNN-.../audio/RNN-voiceover.mp3`. Check `audio/archive/` and ask nothing: if that is where the owner's file is, move it back and use it. Never generate a substitute for the final cut. The macOS "Piya" voice is only a timing placeholder and cannot run in the sandbox.
2. **Pick the screen that proves each voiceover line.** Existing library shot, or add a capture. One relevant screen per line, never reused generically.
3. **Make the data full and healthy before capturing.** Use the separate marketing database, not the owner's real one. The showcase seeder already fixes: live kitchen queue (old orders closed, a fresh pending/preparing/ready queue), dues, recipes for every menu item, reservations, SMS history, letters, stock transfers, a varied activity log, healthy expense levels, liquid-ingredient stock units, product photos (PNG copies).
4. **Capture through the user's own terminal**, with demo UI hidden: no "Free Demo" banner, no bottom-right "Demo guide" button. Look at every new PNG. Reject any with empty states, demo UI, or bad numbers (negative profit, red food cost).
5. **Write per-scene camera and callouts** in `_tools/reel_visuals.py`: focus point and zoom 1.2 to 1.4, short Bangla callout labels with no hard-coded numbers, staggered heights so bubbles do not overlap, one callout in scenes shorter than 3 seconds.
6. **Build.** `build_scripts.py` refreshes folders; `build_video.py RNN` renders a 1080x1920 30 fps video with word-by-word headlines, zooming browser-window card, callouts with cursor tap, karaoke subtitles timed to the voice, and a call-to-action end card. Line cuts are chosen by dynamic programming over the pauses in the owner's audio. Portrait pictures are padded onto a landscape backdrop.
7. **Review frames, send the file, state limits honestly.** Then commit and push when asked.
8. **Social posting versions:** `RNN-upload.mp4` re-encoded (about 5 MB) because the browser upload tool limits a call to 10 MB.

Quality bar the owner reacts to: near-acceptable earlier drafts were improved by (a) no demo banner, (b) full data, (c) screens and animation relevant to the script, (d) the owner's real voice. Keep every future reel at that bar from the first build.

## 5. Facebook posting workflow (done for R01 to R03)

- Target page: **Datatech BD Ltd.** (`facebook.com/datatechbdltd`, page id 1124225807788567). The owner manages other pages (Restao, Suleman Restaurant & Kabab, Tech Alap, Price In BD and more). Always confirm you are on Datatech BD Ltd. for DT Restora content. The `dt.restora` handle in older docs does not exist.
- Learn the page first: open its Reels tab, read two or three captions. Existing style: Bangla hook question, pain list, product line, offer, contact, about eight hashtags. Reels there have no voice; existing top reel had about 5.6K views, others under 300.
- Use Claude in Chrome (the owner's signed-in Chrome). Create reel at `https://www.facebook.com/reels/create`; the composer says "What's on your mind, Datatech BD Ltd.?" when acting as the page. Upload with `file_upload` to the hidden file input. Type the caption, click Next, set the AI label, then Post.
- **Turn on "Add AI label"** for any reel with an AI-generated voice (Meta policy).
- Caption pattern that was used, no emojis: Bangla hook line; one or two benefit sentences; "DT Restora - <category keyword in Bangla> (English SEO phrase)" followed by 3 to 4 dash bullets; the offer line; free demo link, WhatsApp number, email; a comment-prompt question; 10 hashtags starting `#DTRestora` and including `#RestaurantSoftware #RestaurantPOS #POSBangladesh #BangladeshRestaurant #DatatechBD` plus a local one (Mirpur, Dhanmondi, Dhaka).
- Always confirm afterwards on the Reels tab. The newest tile takes a minute or two to appear.

## 6. Meta Ads workflow (done once: audit, plan, build, publish)

Order that the owner approved: browser and account check, audit of old campaigns, strategy and plan, then build and publish after approval. Detailed results are in `docs/demo/marketing/facebook-page/08-meta-ads-audit-and-plan.md`; keep that file updated.

1. **Health check, read only.** Ads Manager campaigns (set date to Maximum), billing (`business.facebook.com/latest/billing_hub/payment_settings?asset_id=<act id>`), Business Support Home for restrictions, the Page's linked accounts (`facebook.com/settings/?tab=linked_profiles`) for Instagram and WhatsApp. Ad account: 362928581585627. Facebook shows redirect interstitials on some `navigate` calls; click Continue. Do not switch the Facebook identity just to read account quality.
2. **Audit with real numbers only.** Lifetime view before this campaign: one campaign, 1,767.65 BDT spent, 7,455 impressions, 5,031 reach, 51 WhatsApp conversations at 34.66 BDT. Best ad was the Bangla profit reel (34 conversations at 34.26 BDT), then the recipe costing post (16 at 32.23); English quiz reels got 0 or 1. Men 25 to 44 produced nearly all results; Facebook feed was cheapest; Instagram never delivered. Lesson: Bangla copy about a money problem beats English and feature lists.
3. **Strategy for 200 BDT per day.** One campaign, one ad set, three to four ads. More ads dilute the budget (about 5 to 6 conversations per day means each of ten ads would get about 20 BDT). Single consolidated audience, Advantage+ placements with Audience Network off, objective Engagement with WhatsApp destination, "Send WhatsApp message" button.
4. **Build in the UI as drafts, then publish.** Notes that cost time:
   - New campaign default daily budget is about 1,986 BDT; **always set 200** and read back the "maximum daily spend" line.
   - Choose "Manual destination" and tick WhatsApp only; untick Messenger and Instagram destinations.
   - Location search "Dhaka" picks "Dhaka, Dhaka Division + 40 km", which already covers Gazipur, Savar, Narayanganj; do not add them separately. Min age is a custom dropdown; click it and choose 25.
   - Detailed targeting is only a suggestion under Advantage+; job titles used: Restaurant management, Restaurateur, Restaurant General Manager, Kitchen Manager/Chef, Head Chef.
   - Placement platforms: untick Audience Network in the Platforms list.
   - Creative wizard: Video ad, then Media, Page tab (newest Page videos come first), Skip video length, Crop defaults, Text, Creative generation (skip), Enhancements. **Turn off Video touch-ups and Text improvements** (Meta AI would alter exact Bangla copy and UI visuals). They reset to defaults on media change; recheck.
   - Typing into a text box that grows pushes the next field down. Take a screenshot between fields, or you will type into the wrong box.
   - Text area edits: click, `cmd+a`, then type.
   - Conversation template: "Start conversations" with a Bangla welcome message and pre-filled message (80 characters maximum). Save, then it is selected for the ad.
   - Duplicate an ad with the "..." menu, Quickly duplicate, untick the AI recommendation checkbox, Duplicate.
   - Pressing Escape inside the editor closes it (draft stays saved).
   - **Publish only your own items.** The account had five old unpublished draft edits. The top-bar "Review and publish (N)" would publish them. Select your ads in the Ads table, click the row Publish button, and in "Review draft items" tick only your ads ("2 of 7"). Verify afterwards that the old campaign is still off and the old drafts remain.
   - After publish Meta offers "additional ad variations (Meta AI)"; click Skip.
5. **Verify after publish.** Ads table: all three ads Processing (review). Campaign toggle on, budget 200 BDT daily. Report review or payment issues; do not claim delivery before the status says Active.

Facts to disclose every time: Meta may spend up to about 350 BDT on one day for a 200 BDT budget (weekly cap 1,400 BDT) and about 15 percent tax is billed on top.

## 7. Operating rules distilled (apply in any project for this owner)

Do automatically, without being asked:
- Check for assets the owner already provided before deciding something is missing.
- Look at every generated image and video frame before showing it.
- Keep documentation current (workflow file, plan file) when you change a procedure.
- Use separate working data for demos and marketing; never modify the real database.
- Offer a short list of decisions at the end; do not ask questions that the available information already answers.
- Prefer small safe steps and verify each (for example confirm budget readback, confirm selected rows count).

Ask first, never assume:
- Publishing, posting, sending, spending money or changing a budget (even though earlier approvals existed).
- Which Facebook page or ad account when several exist.
- Claims about price or product that conflict with `claims-to-verify.md`.
- Installing third-party code or skills from GitHub (the owner suggested three; none were needed and none were installed).

Never do:
- Archive or rename owner-supplied files.
- Put emojis anywhere.
- Present dramatised stories as real customers or invent statistics.
- Click "Discard Drafts" or the global "Review and publish" in Ads Manager.
- Raise the 200 BDT budget unprompted.

## 8. Environment and tooling facts

- The Claude sandbox cannot launch Chrome, run macOS `say`, reach localhost with `curl`, or reach GitHub. Use the user's Terminal panel tools (`run_in_terminal`) for the dev server, Playwright capture with `SHOTS_CHANNEL=chrome`, and `git pull --rebase` plus `git push`.
- `ffmpeg`, `python3`, `php`, `sqlite3`, `swiftc` work in Bash. Swift compiles print harmless `xcrun` cache warnings. Bengali text must be shaped by AppKit (Swift) or a browser; Pillow without raqm and ffmpeg without drawtext cannot do it.
- The marketing server: `bash marketing-assets/november-2026/reel/_tools/serve_marketing.sh` serves `http://localhost:8001` with 8 workers, 2 GB PHP memory, and points `public/storage` at this checkout so product photos load. The stale `public/hot` file is moved aside and restored on exit; `web-app/public/hot.marketing-bak` is a local artifact that is never committed.
- Rebuild marketing data: `bash marketing-assets/november-2026/reel/_tools/prepare_marketing_db.sh` (about a minute, writes `web-app/database/marketing.sqlite`, git-ignored).
- The Claude in Chrome tools: call `tabs_context_mcp` first, open your own tab, close it when done. `browser_batch` runs several actions in one call; every item needs `tabId`, and clicks need `action_summary`.
- Known product issues found while building data, worth telling the product team: the kitchen display page ran out of PHP memory with about 33,000 orders at the default 128 MB; the stock-transfers page is empty for an owner who has not chosen a branch (it reads the session branch); the demo seeder stores litre-based ingredient stock in the wrong unit (POS reported "Not enough stock for Cooking Oil").

## 9. Mistakes already made (do not repeat)

1. Filed the owner's R01 to R03 audio under `archive` as "old script" and told the owner no audio existed. Fix: always inspect archives for owner files.
2. Built a first draft video with static frames, no audio and no animation; the owner wanted a SaaS-style animated video with audio and synced text.
3. Screenshots showed the Free Demo banner, a "Demo guide" button, empty kitchen, empty dues, and 15,800 stuck pending kitchen orders. Fix is in the seeder and capture script.
4. Typed into the wrong Meta text field after layout shifts and had to retype primary text and headline.
5. Selected the wrong Page video tile for ad B (assumed oldest first; the library lists newest first). Verify the ad preview shows the intended reel before publishing.
6. The first "Publish" only published the selected ad; ads A and B stayed in draft. Always re-open the Ads table to verify every ad's status.
7. Assumed a Facebook handle from a doc; it did not exist. Find the page through the signed-in account.

## 10. What an agent should do next without being asked (backlog)

- Reels R04 to R20: follow `REEL-WORKFLOW.md`. For each, check the audio folder first, build, send the draft, state what was checked.
- Meta Ads: check status the next day (approved? spending? within 350 BDT per day, 1,400 per week?), report cost per WhatsApp conversation and qualified chats, apply the day-4 and day-7 rules from the plan, and propose (not apply) changes. Candidate new ads: the recipe-costing creative, R03 for cafes, a square image ad.
- Keep a lead log (date, restaurant, area, branches, ad name, outcome) because Meta cannot see demos or sales.
- Keep the plan document and this playbook current after every change.
- Before any publish, spend or post: summarise the exact configuration and wait for the go-ahead.

## 11. Reference locations

- Workflow and rules for reels: `marketing-assets/november-2026/reel/REEL-WORKFLOW.md`
- Project agent rules: `CLAUDE.md` (section "Marketing reels")
- Ads audit, plan and launch record: `docs/demo/marketing/facebook-page/08-meta-ads-audit-and-plan.md`
- Facebook page setup guides: `docs/demo/marketing/facebook-page/01` to `07`
- Offer and claims: `marketing-assets/november-2026/reel/00-brief/`
- Build tools: `marketing-assets/november-2026/reel/_tools/` (`build_video.py`, `render_video.swift`, `reel_visuals.py`, `reels_data.py`, `capture.mjs`, `MarketingShowcaseSeeder.php`, `serve_marketing.sh`, `prepare_marketing_db.sh`)
