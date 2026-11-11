# নভেম্বর ২০২৬: DT Restora-র ২০টি রিল

প্রতিটা রিল একটা মানুষের মুহূর্ত দিয়ে শুরু হয় (রাতের চিন্তা, কাস্টমারের হতাশা, বেতনের তর্ক), তারপর DT Restora সমাধান হিসেবে আসে। কৌশলের পুরো ব্যাখ্যা: [00-brief/strategy.md](00-brief/strategy.md)

```
00-brief/            strategy.md (কৌশল, ক্যালেন্ডার, A/B), অফার, মডিউল তালিকা, এলাকা, যোগাযোগ, হুক, যাচাইয়ের তালিকা
R01-.../ থেকে R20-.../  প্রতি রিলের নিজস্ব ফোল্ডার, ভেতরে:
    script.md        দৃশ্যভিত্তিক স্ক্রিপ্ট (হুক, ক্যাপশন, পিন কমেন্ট)
    voiceover.txt    পরিষ্কার ভয়েসওভার (TTS বা ভয়েস আর্টিস্টের জন্য)
    screenshots/     এই রিলের ছবি, দৃশ্যের ক্রমে নামকরণ (01-..., 02-..., cta-...)
    audio/           নতুন তৈরি অডিও এখানে (R03-voiceover.mp3); পুরনো স্ক্রিপ্টের অডিও audio/archive/-তে
    (পরে) video/     চূড়ান্ত রিল (R03-final.mp4)
ALL-voiceover.txt    ২০ রিলের ভয়েসওভার একসঙ্গে
reel-index.md        ২০ রিলের তালিকা
_library/screenshots/  সব স্ক্রিনশটের মূল ভান্ডার (capture.mjs এখানে লেখে)
_tools/              reels_data.py (লেখা), build_scripts.py, capture.mjs (স্ক্রিনশট)
```

## কাজের ধারা

1. লেখা বদলাতে `_tools/reels_data.py` সম্পাদনা করে `python3 _tools/build_scripts.py` চালান। প্রতি রিলের script.md, voiceover.txt, screenshots/ ও তালিকা নিজে নতুন হয়ে যায় (audio/ ও video/ ছোঁয়া হয় না)।
2. স্ক্রিপ্ট চালালে সময়ের হিসাব, ভয়েসের দৈর্ঘ্য, ছবির অস্তিত্ব আর প্রোডাক্টের নাম আছে কি না, সব নিজে যাচাই হয় এবং সমস্যা থাকলে সতর্কবার্তা দেয়।
3. ভয়েসওভার রিলের ফোল্ডারের `voiceover.txt` থেকে নিয়ে অডিও বানান, ওই ফোল্ডারের `audio/` -তে রাখুন (যেমন `R03-.../audio/R03-voiceover.mp3`)।
4. স্ক্রিনশট নতুন করে তুলতে অ্যাপ চালু রেখে: `SHOTS_LOGIN=... SHOTS_PASSWORD=... SHOTS_CHANNEL=chrome node _tools/capture.mjs`
5. প্রকাশের আগে `00-brief/claims-to-verify.md` দেখে নিন।
