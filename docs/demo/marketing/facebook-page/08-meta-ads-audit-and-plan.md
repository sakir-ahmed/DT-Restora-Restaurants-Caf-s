# Meta Ads: audit and launch plan (DT Restora)

Prepared 12 Nov 2026 from live Ads Manager data. Nothing has been published or changed in the ad account. Items marked UNVERIFIED were not visible in the account and must be checked before launch.

## 1. Health check (what was verified)

| Check | Result |
| --- | --- |
| Ad account | "Md. Sakir Ahmed", id 362928581585627, currency BDT, Dhaka time. A personal-name ad account, not a business-named one. |
| Facebook Page | Datatech BD Ltd., id 1124225807788567, 4.9K followers, in business portfolio "DataTech BD - old". The only Page that carries DT Restora content. |
| Instagram | @datatech.bd is linked to the Page. The previous campaign never delivered on Instagram (see audit). |
| WhatsApp | One number linked to the Page: +880 1575-888775 (primary). It matches the number in every caption. UNVERIFIED: whether it is the WhatsApp Business app or Cloud API (decides which automation tools exist). |
| Payment | Visa ending 9637, expires 3/30, automatic payments. No ad credits, funds 0.00. Outstanding balance 625.11 BDT plus about 93.77 estimated tax (about 15 percent on top of spend). Next charge when balance reaches 2,000 BDT or on 28 Nov 2026. |
| Limits | Meta daily spending limit 5,205.82 BDT. No account spending limit is set. |
| Restrictions | Business Support Home shows no support cases and no issue for this ad account. A different account, "Pushti Bondhu", is restricted (read-only), unrelated to DT Restora. |
| Drafts | "Review and publish (5)": five unpublished edits on the existing ads. Left untouched. Do not click Discard Drafts. |

Things that could affect delivery: the campaign is OFF; the business info has no business name or tax id (not blocking); the Page sits under an "old" business portfolio.

## 2. Audit of previous campaigns

Only one campaign has ever run (lifetime view, Oct 2023 to Nov 2026).

Campaign: boosted post "Bhai, apnar Restaurant-er Profit kothay hariye..." Objective: messaging conversations. Budget 250 BDT per day. Status: off, delivery "Ongoing" but switched off. Bid: highest volume.

| Metric | Result |
| --- | --- |
| Amount spent | 1,767.65 BDT |
| Impressions / reach | 7,455 / 5,031 (frequency about 1.5) |
| Messaging conversations started | 51 |
| Cost per conversation | 34.66 BDT |
| Cost per 1,000 impressions (derived) | about 237 BDT |

By ad (5 automatic ads in one ad set):

| Ad | Conversations | Cost each |
| --- | --- | --- |
| Bangla reel: "Profit kothay hariye jachhe" | 34 | 34.26 |
| Recipe costing post: "Know what every plate really costs" | 16 | 32.23 |
| "Restaurant owners, one minute please" | 1 | 68.96 |
| "Be honest. Can you answer these three?" | 0 | none |
| "Sales were high. Why was the bank balance low?" | 0 | none |

By placement (spend, conversations at cost):
- Facebook feed, in-app: 574.96 BDT, cost 27.38 each (cheapest).
- Facebook Reels: 474.24 BDT, 43.11 each.
- Audience Network native: 411.45 BDT, 29.39 each (quality is usually low).
- Facebook Stories: 57.55 BDT, 1 conversation.
- Instagram: no delivery at all.

By age and gender: men 25 to 34 gave 19 conversations at 34.35; men 35 to 44 gave 10 at 40.08; women 25 to 34 gave 6 at 32.85. Almost all results came from men 25 to 44.

Not measurable from the account: how many conversations were genuine restaurant owners, demos booked and sales (no conversion events, no CRM link). Targeting locations and interests of the old ad set were not inspected (opening the editor risks creating a draft). Check them in the first build step.

### Lessons
1. Bangla copy and a concrete money problem (profit, plate cost) worked; the two English "quiz" style reels produced nothing. Use Bangla.
2. The recipe-costing message is the second best and cheapest per conversation: lead with money, not features.
3. Messaging cost is about 33 to 35 BDT, so 200 BDT per day should give roughly 5 to 6 conversations per day. Meta needs about 50 conversions in 7 days to leave "learning"; at this budget the campaign will stay in "learning limited". Do not over-edit it.
4. Instagram was never used, and Audience Network was; switch placements so Instagram gets a fair share and Audience Network is not forced on.
5. The audit cannot tell quality. The new setup adds a short qualification flow so that "conversation" is measured as "qualified owner".

## 3. Strategy

- One campaign, one ad set, four ads. Splitting 200 BDT into areas or interests would starve each ad set.
- Objective: Engagement with the messaging destination WhatsApp, optimised for conversations (the same result type that worked: cost per messaging conversation started). If Ads Manager offers "Sales/Leads with WhatsApp" for this account, compare, but keep the budget in one place.
- Budget: 200 BDT per day at campaign or ad-set level. Meta may spend up to about 25 percent more on a single day as long as the weekly total stays within 7 x 200 BDT. Add about 15 percent tax: expect up to about 230 BDT per day billed. Say this in the approval.
- Placements: Advantage+ placements with Audience Network turned off, so Facebook feed, Reels, Stories and Instagram feed, Reels and Stories compete on price.
- Locations: Dhaka city first. Add Gazipur, Savar, Narayanganj as one list in the same ad set. Meta cannot target a neighbourhood by name in Bangladesh, so area names (Dhanmondi, Gulshan, Mirpur and so on) go into the creative, and a Dhaka radius is the targeting. Do not create one ad set per area.
- Age 25 to 55, all genders (data shows men 25 to 44 convert, but excluding women gains little and loses reach). Do not narrow by age further.
- Audience signals: Advantage+ audience with suggestions only. Detailed targeting options to search for in the UI and keep only those that exist and are relevant: restaurant owner or restaurant management, small business owner, entrepreneurship, point of sale, food service, hospitality industry. UNVERIFIED until searched. Reject broad food-lover interests.
- Lookalikes and retargeting: not practical yet. 51 conversations and a 5K follower page are too small. After 2 to 3 weeks create a custom audience of people who watched 50 percent of the reels and who messaged the Page, and test a retargeting ad set only if it has about 1,000 or more people.
- Never promise a result. No "guaranteed profit" language.

## 4. Proposed configuration (for approval)

| Item | Setting |
| --- | --- |
| Name | DT Restora | WhatsApp | Nov 2026 |
| Objective | Engagement, messaging conversations, destination WhatsApp |
| Budget | 200 BDT per day, no end date |
| Ad set | One. Page Datatech BD Ltd. WhatsApp number +880 1575-888775 |
| Locations | Dhaka, Gazipur, Savar, Narayanganj (cities with their default radius) |
| Age | 25 to 55 |
| Placements | Advantage+, Audience Network off |
| Optimisation | Conversations, highest volume |
| Ads | A, B, C, D below. All call to action: Send WhatsApp message |

### Ads

Common rules: no emojis, Bangla first, English only for product terms. Offer: 1000 BDT per branch per month, 2500 BDT installation, first month and training free (matches `00-brief/offer-and-pricing.md`).

Ad A: existing winning Bangla reel (reuse the Page's reel post that cost 34.26 each). Primary text:

```
আপনার রেস্টুরেন্টে প্রতিদিন বিক্রি হচ্ছে, কিন্তু লাভ কোথায় যাচ্ছে, জানেন?
ফুড ওয়েস্টেজ, ভুল বিলিং, স্টকের গরমিল, স্টাফের হিসাবের ভুল: প্রতিদিন একটু একটু করে লাভ খেয়ে ফেলে।
DT Restora দিয়ে POS, কিচেন, স্টক, রেসিপি কস্টিং, স্টাফ আর রিপোর্ট এক সফটওয়্যারে।
প্রতি ব্রাঞ্চ মাসে ১০০০ টাকা। ইনস্টলেশন ২৫০০ টাকা। প্রথম মাস ও ট্রেনিং ফ্রি।
ফ্রি ডেমোর জন্য WhatsApp-এ মেসেজ করুন।
```
Headline: `রেস্টুরেন্টের পুরো হিসাব এক সফটওয়্যারে`. Description: `প্রতি ব্রাঞ্চ ১০০০ টাকা/মাস। প্রথম মাস ফ্রি।`

Ad B: problem and solution on plate cost (reuse the recipe-costing post, add a Bangla version as the primary text):
```
আপনার প্রতিটা পদের আসল খরচ কত, জানেন?
রেসিপিতে গ্রাম, মিলি আর পিস ধরে উপকরণ দিন। সর্বশেষ কেনার দামে প্রতি পদের খরচ আর লাভ নিজে হিসাব হয়, তাই দাম ঠিক হয় হিসাব দেখে, আন্দাজে নয়।
কম ওয়েস্টেজ, ঠিক স্টক, পরিষ্কার লাভের হিসাব। DT Restora, প্রতি ব্রাঞ্চ মাসে ১০০০ টাকা।
ফ্রি ডেমো দেখতে WhatsApp-এ মেসেজ করুন।
```
Headline: `প্রতি পদের খরচ ও লাভ, এক নজরে`. Description: `রেসিপি কস্টিং, স্টক ও ওয়েস্টেজ রিপোর্ট`.

Ad C: new reel R01, "Dokan bondho hoy, chinta bondho hoy na?" (posted 12 Nov). Primary text from its caption, shortened to the five lines above plus the offer. Headline `দোকান বন্ধ, হিসাব তৈরি`. Description `বিক্রি, ক্যাশ আর লাভ, এক জায়গায়`.

Ad D: new reel R02 (Mirpur, paper ledger) or R03 (Dhanmondi cafe). Run only one; choose R02 for the broad restaurant owner who still keeps a khata. Headline `খাতা ছেড়ে নিরাপদ হিসাব`. Description `কে, কখন, কত টাকা: সব রেকর্ড`.

On-screen Bangla text for every reel is already in the videos; the first 3 seconds carry the hook. Feed and Reels use the 9:16 videos; for Feed a square crop is optional.

Missing assets: a 1:1 static image (screenshot with the Bangla headline) for Facebook and Instagram feed; a second Bangla problem-and-solution reel about wastage and stock (not yet made). The three posted reels use an AI voice and carry the AI label.

## 5. WhatsApp conversion setup

Prefilled message (set in the ad):

`আসসালামু আলাইকুম, আমি আমার রেস্টুরেন্টের জন্য DT Restora সফটওয়্যার সম্পর্কে বিস্তারিত জানতে চাই।`

Conversation starters (ice breakers, up to 4, in the ad):
1. `ফ্রি ডেমো দেখতে চাই`
2. `মূল্য ও অফার জানতে চাই`
3. `আমার ৩টি ব্রাঞ্চ আছে`
4. `রেসিপি কস্টিং ও স্টক কীভাবে চলে?`

Automatic welcome message (greeting, WhatsApp Business app or ad greeting):

```
আসসালামু আলাইকুম! DT Restora-তে স্বাগতম। আমি আপনাকে ডেমো দেখিয়ে পুরো সফটওয়্যারটা বুঝিয়ে দিতে পারি।
শুরুতে দুইটা কথা জানাবেন?
১) আপনার রেস্টুরেন্টের নাম ও এলাকা
২) কয়টি ব্রাঞ্চ আছে
```

Next messages (sent by a person or quick replies, one question at a time):
1. `এখন বিল বা হিসাব কীভাবে করেন? খাতা, Excel, নাকি অন্য কোনো POS?`
2. `সবচেয়ে বড় সমস্যা কোনটা: ওয়েস্টেজ, স্টকের হিসাব, স্টাফ, নাকি লাভের হিসাব?`
3. `আমরা ১৫ মিনিটের ফ্রি ডেমো দেখাতে পারি। কাল বা পরশু কোন সময় সুবিধা হবে?`

Qualified lead means: owner or manager, a real restaurant or cafe in the target area, asked for demo or price. Tag these in WhatsApp (label "Demo requested", "Qualified").

## 6. Budget and expected spend (estimates, not promises)

- Daily budget 200 BDT. About 1,400 BDT over 7 days, plus tax about 15 percent, so about 1,610 BDT billed per week.
- At the old cost of 33 to 35 BDT per conversation: about 5 to 6 conversations a day, 35 to 40 a week. If 1 in 4 is a real owner, about 9 to 10 qualified chats a week; whether any become demos or sales is unknown until measured.

## 7. 7-day monitoring and optimisation plan

Do not change targeting or budget in the first 3 days (learning). Do not raise the budget without written approval.

| Day | Check | Action |
| --- | --- | --- |
| 1 | Ads approved? Delivery started? Spend within 200 BDT plus up to 25 percent. | Fix only policy or payment problems. |
| 2 to 3 | Frequency, CPM, CTR, cost per conversation. | No edits. Reply to every chat within the hour. |
| 4 | Cost per conversation by ad and by placement. | Pause an ad only if it has spent 60 BDT or more with no conversation. |
| 5 to 6 | Share of owners vs customers/job seekers among chats. | Tighten the headline wording if too many non-owners. Add "মালিক ও ম্যানেজারদের জন্য" to the opening line. |
| 7 | Cost per conversation, qualified chats, demos booked. | Keep the best two ads, replace the weakest with a new Bangla creative. Report to the owner. |
| Day 8 to 14 | Weekly totals. | If qualified cost per chat is under about 120 BDT and demos are booked, propose (do not apply) a budget increase of at most 20 percent. |

Track daily: spend, reach, impressions, CTR, CPC, CPM, frequency, conversations, cost per conversation, qualified chats, demos booked, trials, paid customers and acquisition cost (kept by hand in a sheet until a CRM exists).

## 8. Open questions before building

1. Is the 2,500 BDT installation per branch or once per business? (Also on `claims-to-verify.md`.) The ad must say the same thing the sales team says.
2. Is free customisation or free SMS part of the offer? The ads above do not mention them.
3. Is +880 1575-888775 on the WhatsApp Business app or the Cloud API?
4. Approve using the existing winning posts (Ads A and B) as well as the new reels R01 and R02/R03.

## 9. Launch record (12 Nov 2026)

Published in Ads Manager (account 362928581585627) after owner approval.

| Item | As built |
| --- | --- |
| Campaign | "DT Restora - WhatsApp - Nov 2026" (id 120246531367260490), objective Engagement, daily budget 200 BDT at campaign level, no end date |
| Ad set | "Dhaka restaurant owners 25-55 - WhatsApp": manual destination WhatsApp only (+880 1575-888775), Dhaka +40 km, minimum age 25 (Advantage+ age suggestion is 25 to 65+, not 25 to 55), all genders, job-title suggestions (Restaurant management, Restaurateur, Restaurant General Manager, Kitchen Manager/Chef, Head Chef), Facebook, Instagram and WhatsApp Status placements, Audience Network off |
| Ads | A: existing Profit reel with Bangla text. B: reel R01. C: reel R02. All call to action Send WhatsApp message, template "Start conversations 12/11/2026" (Bangla welcome message and pre-filled message) |
| AI features | Video touch-ups off, text improvements off, AI creative generation not used. Standard enhancements still on (add video effects, enhance CTA, relevant comments, ad layout details) |
| Status after publish | All three ads "Processing" (Meta review). Old campaign untouched and still off. The 5 old unpublished draft edits were not published |

Spend note: Meta's own preview shows "maximum daily spend 350 BDT, maximum weekly 1,400 BDT" for a 200 BDT budget. Average is 200 BDT per day; one day can be higher. Tax (about 15 percent) is billed on top.

Not done: the recipe-costing creative (second best old ad) was not re-created because its media was not in the Page library; reel R03 is held back for a café-specific test; the multi-step qualification is a single welcome message, longer flows need WhatsApp Business app quick replies; Instagram connection shows a "Review connection" warning in Ads Manager.
