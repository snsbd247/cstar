# C-STAR — UAT চেকলিস্ট (User Acceptance Testing)

> সংস্করণ ১.০ · ০৭ অক্টোবর ২০২৬ · Sprint ১৬
> কার জন্য: C-STAR-এর যেসব staff system পরীক্ষা করবেন (রিসেপশন, থেরাপিস্ট, ট্রেইনার, হিসাবরক্ষক, ব্রাঞ্চ অ্যাডমিন) এবং পরীক্ষার সমন্বয়কারী।

## ১. কীভাবে পরীক্ষা করবেন

1. ঠিকানা: **http://localhost/cstar** (অফিসের ভেতরে **http://103.222.22.107/cstar**)। ফোনে parent portal পরীক্ষার জন্য একই ঠিকানা।
2. নিচের demo login ব্যবহার করুন — সবার password `Cstar@1234`। **এখানে সব তথ্যই নমুনা (demo)**; আসল শিশুর তথ্য দেবেন না।
3. প্রতিটি লাইনের কাজটি করুন, তারপর "ফল" ঘরে লিখুন: **✅ ঠিক আছে** বা **❌ সমস্যা**।
4. ❌ হলে নিচের "সমস্যার তালিকা"-য় লিখুন: কোন লাইন, কী করেছিলেন, কী দেখার কথা ছিল, কী দেখলেন — সম্ভব হলে screenshot দিন।
5. প্রতিটি কাজের পর **Activity Logs → Audit Logs**-এ আপনার কাজটি লেখা হয়েছে কিনা মাঝে মাঝে দেখে নিন।

| ভূমিকা | Login | কোথায় যাবে |
|---|---|---|
| Super Admin | admin@cstar.test | /app |
| Branch Admin | branchadmin@cstar.test | /app |
| Receptionist | reception@cstar.test | /app |
| Accountant | accounts@cstar.test | /app |
| Trainer | trainer@cstar.test | /trainer (ফোনে) |
| Therapist | therapist@cstar.test | /therapist (ফোন/ট্যাবে) |
| Parent (Ayan-এর মা) | 01700000007 | /portal (ফোনে) |

**সমস্যার মাত্রা:** **গুরুতর** = কাজ করাই যায় না বা ভুল টাকা/ভুল শিশুর তথ্য · **মাঝারি** = ঘুরিয়ে করা যায় · **ছোট** = লেখা, রং, সাজানো।

---

## ২. রিসেপশন (Receptionist)

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| R1 | Patients → New Patient: একটি নতুন শিশু নিবন্ধন করুন (অভিভাবকসহ, treatment consent দিয়ে) | নতুন Patient ID (যেমন CSTAR-2026-0000X) তৈরি হয়, profile খোলে | | |
| R2 | উপরের search box-এ শিশুর নাম/ID/মোবাইলের কয়েকটি অক্ষর লিখুন | শিশুটিকে খুঁজে পাওয়া যায় | | |
| R3 | Profile → Enrollments: শিশুকে Speech Therapy-তে ভর্তি করুন, তারপর Regular Training-এও | দুটো আলাদা enrollment দেখায়; শিশুর ধরন "Both" হয় | | |
| R4 | একই শিশুকে আবার একই therapy-তে ভর্তির চেষ্টা করুন | ভর্তি হয় না, কারণ লেখা আসে | | |
| R5 | Appointments → Today's Appointments → New appointment: শিশুটির জন্য একটি খালি সময় বুক করুন | শুধু খালি slot দেখায়; বুক হলে board-এ আসে | | |
| R6 | একই therapist-এর একই সময়ে আরেকটি appointment বুক করার চেষ্টা করুন | সম্ভব হয় না | | |
| R7 | Appointments → Check-in / Queue: আজকের একজনকে "Check in" করুন | "Waiting" কলামে যায়, অপেক্ষার মিনিট দেখায় | | |
| R8 | Appointments → Calendar: মাস ও সপ্তাহ দেখুন, একটি দিনে click করুন | ওই দিনের board খোলে | | |
| R9 | একটি appointment ২৪ ঘণ্টার কম আগে cancel করুন (কারণসহ) | "late cancellation" চিহ্নিত হয়; অভিভাবক বাংলায় বার্তা পান | | |
| R10 | Profile → Billing: ৳১,০০০-এর invoice বানিয়ে ৳১৫০ ছাড় দিন | ১০%-এর বেশি ছাড় receptionist দিতে পারেন না — বার্তা আসে | | |
| R11 | কারণ ছাড়া ছাড় দেওয়ার চেষ্টা করুন | কারণ বাধ্যতামূলক | | |
| R12 | bKash-এ পেমেন্ট নিন, transaction ID ছাড়া | transaction ID বাধ্যতামূলক | | |
| R13 | ঠিকভাবে পেমেন্ট নিন, রসিদের PDF খুলুন | রসিদ নম্বর RCP-…, PDF-এ center-এর নাম ও ঠিকানা | | |
| R14 | Invoice-এর চেয়ে বেশি টাকা নিন | বাড়তি টাকা "অগ্রিম" হয়, পরের invoice-এ নিজে থেকে কাটে | | |
| R15 | Appointments → Appointment Requests: website-এর একটি অনুরোধ থেকে appointment বানান | অনুরোধটি "converted" হয় | | |
| R16 | Assessments → Recommendations: একটি সুপারিশ থেকে "Enroll" | ফর্ম আগে থেকে ভরা থাকে | | |
| R17 | Accounts → Cash Closing: দিনের শেষে নোট গুনে cash closing করুন (৳৫০ কম লিখে) | কম হলে কারণ বাধ্যতামূলক | | |
| R18 | একজন শিশুর clinical তথ্য (diagnosis, therapist-এর internal note) দেখার চেষ্টা করুন | রিসেপশন clinical note দেখতে পান না | | |

## ৩. থেরাপিস্ট (ফোন/ট্যাবে /therapist)

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| T1 | Today খুলুন | আজকের appointment ও বাকি note দেখায় | | |
| T2 | check-in করা একটি appointment-এর session note লিখে **Finalize** করুন (parent summary সহ) | note locked; package থাকলে ১টি session কাটে | | |
| T3 | Finalize-এর পর note বদলানোর চেষ্টা করুন | বদলানো যায় না | | |
| T4 | Assessments → New: draft রাখুন, পরে summary দিয়ে final করুন, অভিভাবকের সাথে share করুন | ASM-… নম্বর; share-এর পর অভিভাবক বাংলায় বার্তা পান | | |
| T5 | অন্য থেরাপিস্টের assessment বদলানোর চেষ্টা | সম্ভব নয় | | |
| T6 | Patients → একজন শিশুর plan-এ লক্ষ্যের অগ্রগতি % বদলান | Plans & Goals ও progress report-এ নতুন % আসে | | |
| T7 | Payslips খুলুন | শুধু নিজের payslip দেখায় | | |

## ৪. ট্রেইনার (ফোনে /trainer)

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| G1 | Today → আজকের class-এর হাজিরা দিন (একজন late, একজন absent) | Training → Attendance (admin)-এ "Marked" দেখায় | | |
| G2 | উপস্থিত শিশুদের training record লিখুন | Training Records-এ আসে; অভিভাবক portal-এ note দেখেন | | |
| G3 | নিজের class ছাড়া অন্য class-এর শিশু খোঁজার চেষ্টা | দেখা যায় না | | |

## ৫. অভিভাবক (ফোনে /portal — বাংলায়)

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| P1 | লগইন করে হোম দেখুন | সন্তানের প্রোগ্রাম, পরবর্তী appointment, বকেয়া — বাংলা সংখ্যায় | | |
| P2 | সময়সূচি → অনুরোধ পাঠান | রিসেপশনের Appointment Requests-এ আসে | | |
| P3 | অগ্রগতি → উপস্থিতি, লক্ষ্য, therapist-এর note | শুধু অভিভাবকের জন্য লেখা অংশ দেখায়, internal note দেখায় না | | |
| P4 | বিল → invoice ও রসিদের PDF | খোলে | | |
| P5 | ঠিকানার শেষে অন্য শিশুর নম্বর বসিয়ে দেখার চেষ্টা | দেখা যায় না | | |

## ৬. হিসাবরক্ষক (Accountant)

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| A1 | Expenses: ৳৩,০০০-এর খরচ লিখুন | সাথে সাথে post হয় (PV-…) | | |
| A2 | ৳৮,০০০-এর খরচ লিখুন | "অনুমোদনের অপেক্ষায়" — নিজে approve করা যায় না | | |
| A3 | Accounts → Vendors: একটি bill লিখুন ও আংশিক পরিশোধ করুন | Bills / Payables-এ বাকি অংশ দেখায় | | |
| A4 | Accounts → Bank Reconciliation: statement CSV import → Auto-match | মিল হওয়া লাইন চিহ্নিত হয়, পার্থক্য দেখায় | | |
| A5 | Payroll: এ মাসের বেতন তৈরি করুন | Branch admin-এর অনুমোদনের পর তবেই post হয় | | |
| A6 | Accounts Reports → Balance Sheet | "Balanced" দেখায় (সম্পদ = দায় + মূলধন) | | |
| A7 | Profit & Loss, Cash Flow, Trial Balance-এর PDF | খোলে, সংখ্যা মেলে | | |
| A8 | Accounts → Bank Accounts | প্রতিটি cash/bank/bKash-এর balance দেখায় | | |
| A9 | Billing & Payments → Billing Dashboard | আজকের ও মাসের collection, বকেয়া, অগ্রিম | | |

## ৭. ব্রাঞ্চ অ্যাডমিন

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| B1 | A2-এর খরচটি approve করুন | post হয়, Accounts Dashboard-এ আসে | | |
| B2 | A5-এর payroll approve করুন | Salary payable post হয় | | |
| B3 | Staff → Leave / Absence: একজন থেরাপিস্টের ছুটি দিন | ওই দিনগুলোতে তাঁর appointment বুক করা যায় না; আগে থেকে বুক থাকলে সংখ্যা জানায় | | |
| B4 | Users → Branch Access: একজন staff-কে আরেকটি branch দিন | শুধু নিজের branch দিতে পারেন | | |
| B5 | Activity Logs → Login History | নিজের branch-এর staff-এর লগইন, ব্যর্থ চেষ্টা দেখায় | | |
| B6 | Reports & Analytics → যেকোনো ৩টি report → PDF ও Excel | ডাউনলোড হয়, বাংলা নাম ঠিক থাকে | | |

## ৮. সুপার অ্যাডমিন

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| S1 | Settings → Patient ID: prefix বদলান | "Next ID will look like …"-এ নতুন রূপ; নতুন শিশুর ID সেভাবে হয় | | |
| S2 | Settings → Center Information ও PDF: নাম, ঠিকানা, রং বদলান | পরের যেকোনো PDF-এ নতুন তথ্য | | |
| S3 | Settings → Security: idle timeout ১০ মিনিট দিন, ১১ মিনিট কিছু না করে থাকুন | নিজে থেকে লগইন পেজে যায়, বার্তাসহ | | |
| S4 | Settings → Backup → Back up now → Download | .sql.gz ফাইল নামে; Audit Logs-এ "backup downloaded" | | |
| S5 | Settings → System | Go-live checklist দেখায় (demo account, HTTPS ইত্যাদি) | | |
| S6 | Activity Logs → Clinical Access Logs | কে কোন শিশুর clinical তথ্য/assessment/document খুলেছেন | | |
| S7 | Website / CMS → Page Sections: একটি অংশ লুকান, ক্রম বদলান | website-এর home page-এ সাথে সাথে বদলায় | | |
| S8 | Website / CMS → SEO: "Hide from search engines" চালু | site-এর robots.txt-এ "Disallow: /" | | |
| S9 | Notifications → Templates: "Payment received" বার্তা বদলান | পরের পেমেন্টে অভিভাবক নতুন লেখা পান; ভুল {placeholder} দিলে save হয় না | | |
| S10 | Users → Permissions | প্রতিটি role কী করতে পারে তার ছক | | |

## ৯. নিরাপত্তা পরীক্ষা

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| X1 | ভুল password দিয়ে ৫ বার লগইন | "Too many attempts" — এক মিনিট অপেক্ষা | | |
| X2 | Login History-তে X1 দেখুন | ব্যর্থ চেষ্টাগুলো দেখায় | | |
| X3 | লগআউট করে browser-এর Back চাপুন | কোনো শিশুর তথ্য দেখায় না | | |
| X4 | Receptionist হিসেবে /app/settings ঠিকানায় যান | "Access denied" | | |
| X5 | Patient-এর document download করুন | Clinical Access / Audit Logs-এ লেখা থাকে | | |

---

## ১০. সমস্যার তালিকা

| # | লাইন (যেমন R10) | কে পেয়েছেন | মাত্রা | কী হয়েছে | অবস্থা |
|---|---|---|---|---|---|
| 1 | | | | | |
| 2 | | | | | |

## ১১. অনুমোদন (Sign-off)

| ভূমিকা | নাম | তারিখ | স্বাক্ষর |
|---|---|---|---|
| রিসেপশন | | | |
| থেরাপিস্ট | | | |
| ট্রেইনার | | | |
| হিসাবরক্ষক | | | |
| ব্রাঞ্চ অ্যাডমিন | | | |
| পরিচালক / কর্তৃপক্ষ | | | |

সব "গুরুতর" সমস্যা ঠিক হলে এবং উপরের সবাই স্বাক্ষর করলে Sprint ১৭ (cPanel deployment, staff training, go-live) শুরু হবে।
