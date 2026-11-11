# নভেম্বর ২০২৬: DT Restora-র ২০টি রিল

প্রতিটা রিল একটা মানুষের মুহূর্ত দিয়ে শুরু হয় (রাতের চিন্তা, কাস্টমারের হতাশা, বেতনের তর্ক), তারপর DT Restora সমাধান হিসেবে আসে। কৌশলের পুরো ব্যাখ্যা: [00-brief/strategy.md](00-brief/strategy.md)

```
00-brief/      strategy.md (কৌশল, ক্যালেন্ডার, A/B), অফার, মডিউল তালিকা, এলাকা, যোগাযোগ, হুক, যাচাইয়ের তালিকা
scripts/       ২০টি রিলের দৃশ্যভিত্তিক স্ক্রিপ্ট (মানবিক সূত্র, হুক ও বিকল্প, প্রথম ফ্রেম, ক্যাপশন, পিন কমেন্ট)
voiceover/     প্রতি রিলের পরিষ্কার ভয়েসওভার লেখা (.txt) ও ALL.txt (TTS বা ভয়েস আর্টিস্টের জন্য)
audio/         তৈরি অডিও (R01 থেকে R03)। স্ক্রিপ্ট বদলেছে, তাই এগুলো নতুন ভয়েসওভার দিয়ে আবার বানাতে হবে
screenshots/   admin-desktop, admin-desktop-bangla, settings, invoice-print, website-*, admin-mobile, android-app
reel-index.md  ২০ রিলের তালিকা
_tools/        reels_data.py (লেখা), build_scripts.py, capture.mjs (স্ক্রিনশট)
```

## কাজের ধারা

1. লেখা বদলাতে `_tools/reels_data.py` সম্পাদনা করে `python3 _tools/build_scripts.py` চালান। স্ক্রিপ্ট, ভয়েসওভার ও তালিকা নিজে নতুন হয়ে যায়।
2. স্ক্রিপ্ট চালালে সময়ের হিসাব, ভয়েসের দৈর্ঘ্য, ছবির অস্তিত্ব আর প্রোডাক্টের নাম আছে কি না, সব নিজে যাচাই হয় এবং সমস্যা থাকলে সতর্কবার্তা দেয়।
3. ভয়েসওভার `voiceover/` থেকে নিয়ে অডিও বানান, `audio/R01/` ধরনের ফোল্ডারে রাখুন।
4. স্ক্রিনশট নতুন করে তুলতে অ্যাপ চালু রেখে: `SHOTS_LOGIN=... SHOTS_PASSWORD=... SHOTS_CHANNEL=chrome node _tools/capture.mjs`
5. প্রকাশের আগে `00-brief/claims-to-verify.md` দেখে নিন।
