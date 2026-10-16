DT Restora social media pack
============================

00-INDEX.txt            list of every post (folder, language, image count, title)
00-brand-assets/        covers, banners, profile pictures, stories, ads, video thumbnails
01-feature-spotlights/  34 posts, one per feature
02-problem-solution/    8 owner-problem posts
03-offers-pricing/      8 offer and pricing posts
04-owner-tips-seo/      10 educational, SEO-friendly tips
05-carousels-video/     12 multi-image posts (use the images to make videos)
06-faq-and-myths/       5 posts
07-engagement/          7 polls, checklists and questions
08-bangla-posts/        21 posts in Bangla (20 percent of the pack)
09-quick-answers/       12 question-style posts with highlighted fixes (second design family)
10-quiz-and-polls/      8 quiz posts
11-myth-vs-fact/        6 myth versus fact posts
12-owner-chat/          6 chat-style question and answer posts
13-mini-infographics/   8 small infographics (formulas, cycle, timelines, comparison)
campaign/               90-day sales plan, posting calendar, lead tracker, weekly targets
_source/                generator (post data, templates, build.py, render.mjs) to edit and re-run

Each post folder holds image-01.png (and more for carousels) at 1080x1350 plus post.txt with:
Facebook, LinkedIn, X, Instagram and Google Business Profile text, SEO title and keywords,
image alt text, tracking links (utm_content=post-NNN) and, for carousels, a video script.

To change anything: edit the files in _source/, run `python3 _source/build.py` and then
`node _source/render.mjs` (needs the project's Playwright install).
