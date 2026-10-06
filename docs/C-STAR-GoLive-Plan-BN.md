# C-STAR — Go-live ও প্রশিক্ষণ পরিকল্পনা

> সংস্করণ ১.০ · ০৭ অক্টোবর ২০২৬ · Sprint ১৭
> কার জন্য: C-STAR-এর পরিচালক / কর্তৃপক্ষ এবং IT দায়িত্বপ্রাপ্ত ব্যক্তি — কবে কী হবে, কাকে কতটুকু প্রশিক্ষণ, go-live দিনে কী করবেন, সমস্যা হলে কী করবেন।

সংশ্লিষ্ট document: [UAT চেকলিস্ট](C-STAR-UAT-BN.md) · [Deployment গাইড](C-STAR-Deployment-BN.md) · [Staff User Guide](C-STAR-User-Guide-BN.md)

---

## ১. সময়রেখা (G = go-live দিন)

| কবে | কাজ | কে |
|---|---|---|
| G − ২১ দিন | UAT শুরু — staff demo login দিয়ে [চেকলিস্ট](C-STAR-UAT-BN.md) ধরে পরীক্ষা | সব বিভাগ |
| G − ১৪ | UAT-এর গুরুতর সমস্যা ঠিক; UAT sign-off | IT + কর্তৃপক্ষ |
| G − ১৪ | Domain, cPanel hosting (PHP 8.3), email account, SSL প্রস্তুত | IT |
| G − ১০ | Server-এ install ([Deployment গাইড](C-STAR-Deployment-BN.md) §৪), Super Admin তৈরি | IT |
| G − ১০ থেকে − ৩ | **আসল তথ্য বসানো:** center info, শাখা, সেবা ও দাম, package, staff login, employee ও বেতন কাঠামো, class ও সময়সূচি, থেরাপিস্টের কাজের সময় | রিসেপশন প্রধান + হিসাবরক্ষক + IT |
| G − ৭ থেকে − ৩ | **প্রশিক্ষণ** (নিচে §২) — প্রশিক্ষণের জন্য আলাদা **training copy** (demo data) ব্যবহার করুন, আসল server নয় | প্রশিক্ষক + সবাই |
| G − ৩ | বর্তমান শিশুদের তালিকা template-এ পূরণ (কাগজের register থেকে), import, প্রোগ্রামে enroll | রিসেপশন |
| G − ১ | দিনের শেষে কাগজে cash, ব্যাংক, bKash-এর হিসাব বন্ধ → **opening balance** বসানো; Fixed Assets ও vendor-এর দেনা | হিসাবরক্ষক |
| **G** | নতুন সব কাজ system-এ (§৩) | সবাই |
| G + ১ থেকে + ১৪ | **সমান্তরাল সময়:** system-ই মূল, তবে দিনের collection ও হাজিরা কাগজেও টুকে রাখুন; প্রতিদিন সন্ধ্যায় মিলিয়ে দেখুন | রিসেপশন + হিসাবরক্ষক |
| G + ৭ | প্রথম সপ্তাহের পর্যালোচনা সভা | কর্তৃপক্ষ + সব প্রধান |
| G + ৩০ | প্রথম মাস বন্ধ (*Accounting Periods → Close month*), প্রথম payroll, অভিভাবকদের portal চালু (ধাপে ধাপে) | হিসাবরক্ষক + রিসেপশন |

## ২. প্রশিক্ষণ

প্রতিটি সেশন ছোট দলে, নিজের ফোন/কম্পিউটারে হাতে-কলমে। প্রশিক্ষণ হবে **training copy**-তে (demo শিশু Ayan, Sara, Rafi) — ভুল করলে কোনো ক্ষতি নেই। প্রত্যেকে সেশনের শেষে নিজের ভূমিকার UAT লাইনগুলো নিজে করে দেখাবেন।

| দল | সময় | যা শেখানো হবে | অনুশীলন (UAT লাইন) |
|---|---|---|---|
| সবাই (একসাথে) | ৩০ মিনিট | লগইন, password, menu ও খোঁজা, ঘণ্টা, sign out, তথ্যের গোপনীয়তা (কে কী দেখেন, সব কাজ লেখা থাকে) | — |
| রিসেপশন | ২ সেশন × ২ ঘণ্টা | নিবন্ধন, ভর্তি, appointment ও check-in, টাকা নেওয়া ও রসিদ, ছাড়ের নিয়ম, অনলাইন অনুরোধ, cash closing, ছোট খরচ, অভিভাবকের portal চালু | R1–R18 |
| থেরাপিস্ট | ১.৫ ঘণ্টা | আজকের তালিকা, session note ও finalize (অভিভাবকের জন্য সংক্ষেপ বাংলায়), assessment ও সুপারিশ, plan-এর লক্ষ্য | T1–T7 |
| ট্রেইনার | ১ ঘণ্টা | ফোনে হাজিরা, training record, ITP | G1–G3 |
| হিসাবরক্ষক | ২ সেশন × ২ ঘণ্টা | খরচ ও voucher, অনুমোদন, cash গ্রহণ, vendor, ব্যাংক মেলানো, payroll, রিপোর্ট, মাস বন্ধ, opening balance | A1–A9 |
| ব্রাঞ্চ অ্যাডমিন / পরিচালক | ১.৫ ঘণ্টা | অনুমোদন, dashboard ও রিপোর্ট, staff ও ছুটি, activity log | B1–B6 |
| IT / Super Admin | ২ ঘণ্টা | Settings, backup ও restore, user ও role, CMS, update চালানো ([Deployment গাইড](C-STAR-Deployment-BN.md) §৬–৮) | S1–S10, X1–X5 |

**"প্রথম সহায়ক" (super user):** রিসেপশন ও হিসাব বিভাগে একজন করে যিনি বেশি শিখবেন এবং প্রথম দুই সপ্তাহ অন্যদের সাহায্য করবেন।

## ৩. Go-live দিন (G)

**আগের সন্ধ্যায় (IT):**
- [ ] *Settings → Backup → Back up now* → download করে রাখুন
- [ ] `php artisan cstar:go-live-check` — শুধু "Website open to search engines" বাকি থাকতে পারে
- [ ] Demo account নেই, প্রত্যেক staff-এর login কাজ করে
- [ ] আগামী দিনের appointment system-এ আছে (therapy enrollment-এর weekly slot)

**সকালে, center খোলার আগে:**
- [ ] রিসেপশনের কম্পিউটারে login, printer-এ একটি রসিদ print পরীক্ষা
- [ ] থেরাপিস্ট ও ট্রেইনারের ফোনে login
- [ ] *Website / CMS → SEO* → "Hide from search engines" বন্ধ

**দিনভর:**
- নতুন সব নিবন্ধন, appointment, হাজিরা, note, টাকা — শুধু system-এ (সাথে §১-এর সমান্তরাল খাতা)
- সমস্যা হলে কাজ থামাবেন না: কাগজে টুকে রাখুন, super user / IT-কে জানান, পরে system-এ তুলুন

**দিনের শেষে:**
- [ ] রিসেপশন cash closing → হিসাবরক্ষক **Receive cash**
- [ ] কাগজের খাতা ও system-এর collection মিলিয়ে দেখা
- [ ] থেরাপিস্টদের সব note finalize (*Reports → Session notes not finalized* ফাঁকা)
- [ ] সমস্যার তালিকা হালনাগাদ

## ৪. প্রথম দুই সপ্তাহের সহায়তা

| বিষয় | ব্যবস্থা |
|---|---|
| যোগাযোগ | একটি WhatsApp group (staff + IT) — screenshot সহ সমস্যা লিখুন |
| সাড়া | গুরুতর (কাজ বন্ধ / ভুল টাকা): একই দিনে · মাঝারি: ২ কর্মদিবসে · ছোট: সাপ্তাহিক হালনাগাদে |
| প্রতিদিন | IT: *Activity Logs → Login History* (ব্যর্থ লগইন), *Settings → System* (backup ও cron), *Notification Logs* |
| প্রতি সপ্তাহে | হালনাগাদ (প্রয়োজনে) — [Deployment গাইড](C-STAR-Deployment-BN.md) §৬; একটি backup download করে বাইরে রাখা |

## ৫. সফলতার মাপকাঠি (G + ৩০)

- প্রতিটি নতুন শিশু, appointment, হাজিরা ও টাকা system-এ — কাগজের খাতা বন্ধ করা যায়
- প্রতিদিনের cash closing system-এর collection-এর সাথে মেলে
- থেরাপিস্টদের ৯৫%+ session note একই দিনে finalize
- প্রথম মাস বন্ধ, Balance Sheet "Balanced", প্রথম payroll system থেকে
- অন্তত অর্ধেক অভিভাবক portal-এ লগইন করেছেন

## ৬. বিপদে পিছিয়ে আসা (rollback)

- **ছোট সমস্যা:** কাজ চলবে; সমস্যার অংশটি কাগজে, ঠিক হলে system-এ তোলা।
- **নতুন version-এ সমস্যা:** আগের zip আবার বসিয়ে `php artisan cstar:deploy` ([Deployment গাইড](C-STAR-Deployment-BN.md) §৮)।
- **System একেবারে ব্যবহার করা না গেলে (server বন্ধ ইত্যাদি):** সেদিন কাগজে কাজ; সমান্তরাল খাতা থাকায় কোনো তথ্য হারায় না; server ফিরলে কাগজের কাজ system-এ তোলা। শেষ backup থেকে restore কেবল IT ও কর্তৃপক্ষের সিদ্ধান্তে।
