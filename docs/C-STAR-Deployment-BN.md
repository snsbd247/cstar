# C-STAR — cPanel-এ Deployment গাইড

> সংস্করণ ১.০ · ০৭ অক্টোবর ২০২৬ · Sprint ১৭
> কার জন্য: যিনি C-STAR cPanel server-এ বসাবেন ও পরে হালনাগাদ করবেন। cPanel-এ লগইন করতে পারেন, File Manager / Terminal ব্যবহার জানেন — Laravel জানার দরকার নেই।

---

## ১. যা লাগবে

| বিষয় | প্রয়োজন |
|---|---|
| Hosting | cPanel shared hosting — **PHP 8.3 বা তার বেশি** (MultiPHP Manager), MySQL 5.7+/MariaDB 10.4+ |
| PHP extension | pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, gd, zlib, curl, bcmath (cPanel → Select PHP Version → Extensions) |
| Domain | যেমন `cstar.com.bd` — SSL (AutoSSL / Let's Encrypt) চালু |
| Terminal | cPanel → **Terminal** (না থাকলে hosting-কে SSH চালু করতে বলুন) |
| Email | একটি email account, যেমন `noreply@cstar.com.bd` (অভিভাবক/staff-কে email-এর জন্য) |
| আপনার কম্পিউটারে (package বানাতে) | Git, Node.js, PHP 8.3, Composer — server-এ এগুলো লাগবে না |

## ২. Server-এ ফোল্ডারের গঠন

```
/home/<cpanel-user>/
├── cstar-app/        ← প্রোগ্রাম (Laravel) — public_html-এর বাইরে, তাই .env ও রোগীর ফাইল কখনো ওয়েব থেকে দেখা যায় না
│   ├── .env          ← পাসওয়ার্ড ও সেটিং (আপনি বানাবেন)
│   └── storage/      ← রোগীর document, backup, log
└── public_html/      ← ওয়েবসাইটের মূল ফোল্ডার: index.php, .htaccess, spa/ (staff ও অভিভাবকের app), build/ (website-এর style)
```

> domain যদি `public_html`-এ না হয়ে addon domain-এর ফোল্ডারে থাকে (যেমন `public_html/cstar.com.bd`), তবে ওই ফোল্ডারকেই "public_html" ধরে নিন এবং `cstar-app/.public-path` ফাইলে সেই পথ লিখুন (যেমন `../public_html/cstar.com.bd`), আর `index.php`-এর `/../cstar-app/` অংশ ঠিক করুন।

## ৩. Package তৈরি (আপনার কম্পিউটারে)

```bash
git pull                              # সর্বশেষ কোড
node scripts/build-release.mjs        # release/cstar-<তারিখ>-<commit>.zip তৈরি হয়
```

Package সবসময় **সর্বশেষ commit** থেকে তৈরি হয় (না-commit-করা পরিবর্তন থাকে না)। ভেতরে আছে `cstar-app/`, `public_html/` ও `VERSION.txt`।

## ৪. প্রথমবার বসানো (একবারই)

1. **Database:** cPanel → *MySQL® Databases* → নতুন database (যেমন `user_cstar`) ও user বানান, user-কে database-এ **ALL PRIVILEGES** দিন। নাম ও পাসওয়ার্ড লিখে রাখুন।
2. **PHP:** *MultiPHP Manager* → domain-এর জন্য **PHP 8.3** বেছে নিন। *Select PHP Version → Extensions*-এ উপরের extension-গুলো চালু আছে দেখুন।
3. **Upload:** *File Manager* → home ফোল্ডারে (`/home/<user>`) zip upload করুন → **Extract**। `cstar-app` ও `public_html` ফোল্ডার আসবে (public_html আগে থেকে থাকলে ভেতরের ফাইলগুলো মিশে যাবে — পুরনো `index.html` থাকলে মুছে দিন)।
4. **.env:** `cstar-app/.env.production.example` কপি করে নাম দিন `.env`, তারপর edit করে প্রতিটি `CHANGE-ME` পূরণ করুন:
   - `APP_URL=https://cstar.com.bd`
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — ধাপ ১-এর তথ্য
   - `SANCTUM_STATEFUL_DOMAINS=cstar.com.bd` (www থাকলে `cstar.com.bd,www.cstar.com.bd`)
   - `MAIL_*` — email account-এর তথ্য
   - `APP_KEY` খালি রাখুন — পরের ধাপে নিজে তৈরি হবে
5. **SSL:** *SSL/TLS Status* → AutoSSL চালান; `https://` কাজ করার পর এগোন (C-STAR নিজেই http থেকে https-এ নিয়ে যাবে)।
6. **Install:** *Terminal* খুলে:
   ```bash
   cd ~/cstar-app
   php artisan cstar:deploy
   ```
   (cPanel-এ `php` যদি পুরনো version হয়, `/opt/cpanel/ea-php83/root/usr/bin/php artisan cstar:deploy` লিখুন।)
   এটি server পরীক্ষা করে, `APP_KEY` বানায়, database table তৈরি করে, role/শাখা/সেবা/হিসাবের খাত বসায়, cache তৈরি করে ও শেষে **go-live checklist** দেখায়। ✘ চিহ্ন থাকলে সেটা ঠিক করে আবার চালান।
7. **.env-এর কপি রাখুন** (নিরাপদ জায়গায়, server-এর বাইরে) — `APP_KEY` হারালে কেউ লগইন করতে পারবেন না।
8. **প্রথম Super Admin:**
   ```bash
   php artisan cstar:create-admin
   ```
   নাম, email, মোবাইল ও শক্ত password দিন।
9. **Cron** (cPanel → *Cron Jobs*, "Once Per Minute"):
   ```
   * * * * * cd /home/<user>/cstar-app && php artisan schedule:run >> /dev/null 2>&1
   ```
   এর মাধ্যমে চলে: রাত ২:৩০-এ backup, অভিভাবকের reminder (সন্ধ্যা ৬টা), therapist-এর note reminder (সকাল ৮টা), সাপ্তাহিক appointment তৈরি (রাত ১টা), মাসিক training fee (মাসের ১ তারিখ), package-এর মেয়াদ, নিয়মিত খরচ, email পাঠানো।
10. **পরীক্ষা:** `https://cstar.com.bd` (website), `https://cstar.com.bd/login` (Super Admin দিয়ে লগইন) → *Settings → System*-এ go-live checklist দেখুন।

## ৫. Go-live-এর আগে আসল তথ্য বসানো

Super Admin হিসেবে **Settings → Go-live** খুলে ধাপে ধাপে:

1. *Settings → Center Information* ও *General* — নাম, ঠিকানা, ফোন, রেজিস্ট্রেশন নম্বর (সব PDF-এ ছাপা হয়)
2. *Branches* — প্রতিটি শাখার ঠিকানা, ফোন, সময়; *Settings → Patient ID* — ID-র ধরন
3. *Therapy Services* ও *Packages* — আসল সেবা, সময় ও দাম; *Settings → Billing* — ভর্তি ফি, training fee
4. *Users → Add User* — প্রত্যেক staff-এর login; *Staff → All Staff* — employee, বেতন কাঠামো; *Therapists* ও *Trainers* — কাজের সময়
5. *Training → Classes* — class ও সময়সূচি
6. **Opening balances** — go-live দিনের cash box, ব্যাংক, bKash/Nagad, ঋণ ইত্যাদি (সিদ্ধান্ত A9)
7. *Fixed Assets* — সরঞ্জাম; *Vendors* — সরবরাহকারীর কাছে আগের দেনা (opening balance)
8. **শিশুদের import** — template ডাউনলোড → Excel-এ পূরণ → "CSV UTF-8" হিসেবে save → *Check file* → সমস্যা ঠিক করে আবার → *Import*; তারপর প্রত্যেককে তাদের প্রোগ্রামে enroll
9. *Website / CMS* — আসল ছবি, সেবা, টিম, FAQ; go-live দিনে *SEO → "Hide from search engines"* বন্ধ করুন
10. *Settings → System* — checklist-এর সব ✔ হলে go-live

Terminal থেকেও একই checklist: `php artisan cstar:go-live-check`

## ৬. পরে হালনাগাদ (নতুন version)

1. আপনার কম্পিউটারে: `git pull` → `node scripts/build-release.mjs`
2. Server-এ আগে backup: *Settings → Backup → Back up now* → Download (নিরাপদে রাখুন)
3. চাইলে maintenance mode: `php artisan down --retry=60`
4. zip upload করে extract — **`cstar-app/.env` ও `cstar-app/storage/` কখনো মুছবেন না বা বদলাবেন না** (package-এ এগুলো নেই, তাই extract করলে আগেরটাই থাকে)
5. `cd ~/cstar-app && php artisan cstar:deploy` — নতুন table/permission যোগ হয়; আপনার বদলানো role, website-এর লেখা ও settings অপরিবর্তিত থাকে
6. `php artisan up`

## ৭. Backup ও ফেরত আনা (restore)

- **প্রতিদিন রাত ২:৩০** database backup হয় `cstar-app/storage/app/private/backups/`-এ (*Settings → Backup*-এ কত দিন রাখা হবে)। সপ্তাহে অন্তত একবার একটি download করে server-এর বাইরে রাখুন।
- রোগীর document ও ছবি `cstar-app/storage/`-এ — cPanel-এর *Backup* (Home Directory) দিয়ে নিয়মিত সংরক্ষণ করুন।
- **Restore:** cPanel → *phpMyAdmin* → database বেছে নিন → *Import* → `.sql.gz` ফাইল। (আগে বর্তমান database-এর একটি backup নিন।)

## ৮. সমস্যা হলে

| লক্ষণ | কী দেখবেন |
|---|---|
| সাদা পাতা / "500 Server Error" | `cstar-app/storage/logs/laravel-<তারিখ>.log`-এর শেষ অংশ; `.env`-এর DB তথ্য; `php artisan cstar:deploy` আবার চালান |
| লগইন হয়ে আবার login পাতায় ফেরে | `SANCTUM_STATEFUL_DOMAINS` ও `APP_URL` ঠিক আছে কিনা (www সহ/ছাড়া), `SESSION_SECURE_COOKIE=true` হলে https দিয়ে খুলছেন কিনা |
| Website-এর design নেই | `public_html/build/` ফোল্ডার আছে কিনা |
| Staff app খোলে না ("Frontend not built") | `public_html/spa/index.html` আছে কিনা |
| Reminder/backup হচ্ছে না | Cron job ঠিক আছে কিনা; *Settings → System*-এ "Scheduler last ran" |
| Email যায় না | `MAIL_*` তথ্য; cPanel email account-এর password |
| ভুল কিছু হলে আগের অবস্থায় ফেরা | আগের zip আবার extract করে `php artisan cstar:deploy`; database ভুল হলে update-এর আগের backup restore |

**কখনো করবেন না:** `.env` বা `storage/` public_html-এ রাখা · `APP_DEBUG=true` live server-এ · `php artisan migrate:fresh` (সব তথ্য মুছে যায়) · demo seeder চালানো।

## ৯. প্রশিক্ষণের জন্য আলাদা copy (training copy)

প্রশিক্ষণ ও UAT আসল server-এ নয়, আলাদা একটি copy-তে করুন:

1. cPanel → *Subdomains* → যেমন `training.cstar.com.bd` (নিজস্ব ফোল্ডার, যেমন `public_html/training`) এবং আলাদা একটি database।
2. একই zip আরেকটি ফোল্ডারে (যেমন `~/cstar-training-app`) extract করুন; `.public-path` ও `index.php` সেই subdomain-এর ফোল্ডার অনুযায়ী ঠিক করুন (§২-এর নোট)।
3. `.env`-এ **`APP_ENV=staging`** (production নয়) — তাহলে `php artisan cstar:deploy` প্রথমবার demo শিশু (Ayan, Sara, Rafi) ও demo login (`…@cstar.test`, password `Cstar@1234`) বসায়।
4. এই copy-তে কখনো আসল শিশুর তথ্য দেবেন না। Demo password সবার জানা, তাই প্রশিক্ষণ শেষ হলে subdomain ও database মুছে দিন (বা cPanel → *Directory Privacy* দিয়ে password-সুরক্ষিত রাখুন)।
