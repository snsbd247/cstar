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
| R19 | Enrollments → Waiting List → Add to waiting list (একজন শিশু, Speech Therapy, High priority) → **Offer place** | তালিকায় ক্রম নম্বর দেখায়; Offer করলে অভিভাবক বাংলায় বার্তা পান; পরে enroll করলে তালিকা থেকে সরে যায় | | |
| R20 | অভিভাবক portal থেকে বুক করা appointment (P6) Today's Appointments-এ খুঁজে **Confirm** করুন | ঘণ্টায় notification এসেছিল; confirm-এর পর অভিভাবক বার্তা পান | | |
| R21 | Inventory → Stock Items → একটি জিনিসে **Stock in / out** → Used / issued | stock কমে; stock card-এ লাইন আসে; stock-এর বেশি দেওয়া যায় না | | |
| R22 | Dashboard-এর উপরে **Check in**, দিনের শেষে **Check out** | সময় দেখায়; অফিস শুরুর পরে এলে "late" | | |

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
| T8 | যে শিশুর অভিভাবক P8 করেছেন, তার পরের session note খুলুন | ডান পাশে "Last session"-এর নিচে "Family reported" — করেছি/কিছুটা ও মন্তব্য | | |
| T9 | Admin থেকে শিশুর profile → **Progress** tab | গত ৬ মাসের উপস্থিতি, session ও লক্ষ্যের score-এর chart | | |
| T10 | App-এর উপরে Check in | হাজিরা HR sheet-এ আসে | | |
| T11 | Finalize করা note-এ **Amend note** → "Summary for parents" ঠিক করুন, কারণ লিখুন | note-এ নতুন লেখা; Amendments-এ পুরনো লেখা ও কারণ; portal-এও নতুন সংক্ষেপ | | |
| T12 | (Supervisor) Review tab → একটি note-এ "Needs changes" + মন্তব্য | লেখক থেরাপিস্ট notification পান; note-এর নিচে "Supervisor review" | | |

## ৪. ট্রেইনার (ফোনে /trainer)

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| G1 | Today → আজকের class-এর হাজিরা দিন (একজন late, একজন absent) | Training → Attendance (admin)-এ "Marked" দেখায় | | |
| G2 | উপস্থিত শিশুদের training record লিখুন | Training Records-এ আসে; অভিভাবক portal-এ note দেখেন | | |
| G3 | নিজের class ছাড়া অন্য class-এর শিশু খোঁজার চেষ্টা | দেখা যায় না | | |
| G4 | App-এর উপরে Check in / Check out | সময় দেখায়; HR sheet-এ আসে | | |
| G5 | অন্য class-এর substitute হলে (B10) Today খুলুন | সেই class দেখায়, হাজিরা দেওয়া যায়; তারিখ শেষে আর দেখায় না | | |

## ৫. অভিভাবক (ফোনে /portal — বাংলায়)

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| P1 | লগইন করে হোম দেখুন | সন্তানের প্রোগ্রাম, পরবর্তী appointment, বকেয়া — বাংলা সংখ্যায় | | |
| P2 | সময়সূচি → অনুরোধ পাঠান | রিসেপশনের Appointment Requests-এ আসে | | |
| P3 | অগ্রগতি → উপস্থিতি, লক্ষ্য, therapist-এর note | শুধু অভিভাবকের জন্য লেখা অংশ দেখায়, internal note দেখায় না | | |
| P4 | বিল → invoice ও রসিদের PDF | খোলে | | |
| P5 | ঠিকানার শেষে অন্য শিশুর নম্বর বসিয়ে দেখার চেষ্টা | দেখা যায় না | | |
| P6 | সময়সূচি → "খালি সময়ে সেশন বুক করুন" → দিন ও সময় বেছে বুক | "বুকিং পাঠানো হয়েছে"; রিসেপশন confirm করলে বার্তা আসে (R20) | | |
| P7 | আসন্ন একটি appointment-এর পাশে **বাতিল** (২৪ ঘণ্টার কম আগে) | সতর্কবার্তা দেখায়; বাতিল হয়, রিসেপশন জানতে পারে | | |
| P8 | হোম → বাসায় অনুশীলন → **করেছি** ও একটি মন্তব্য | বোতাম রঙিন হয়, গত ৭ দিনের চিহ্নে আজকের দাগ | | |
| P9 | অগ্রগতি পাতার উপরের chart | উপস্থিতি/সেশন/লক্ষ্যের chart বাংলা মাসের নামে | | |
| P10 | বিল → **অনলাইনে পরিশোধ করুন** (sandbox/পরীক্ষার account দিয়ে) | gateway থেকে ফিরে "… পরিশোধ সফল হয়েছে" ও রসিদ; বকেয়া কমে | | |
| P12 | Login পাতায় "পাসওয়ার্ড ভুলে গেছেন?" → মোবাইল → SMS কোড → নতুন password | নতুন password দিয়ে লগইন হয়; একই কোড দ্বিতীয়বার চলে না | | |
| P11 | SMS চালু থাকলে: পেমেন্ট বা appointment-এর পর অভিভাবকের ফোন | "C-STAR:" দিয়ে শুরু বাংলা SMS আসে | | |

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
| A10 | Billing & Payments → Online Payments: P10-এর পেমেন্ট | "Paid", রসিদ নম্বর; Accounts-এ "Online Payments in Transit" খাতে ওঠে (SSLCommerz) বা bKash খাতে | | |
| A11 | Staff → Staff Attendance: একজনের একটি দিন click করে "Absent" করুন | sheet-এ A দেখায়, মাসের গণনা বদলায়; ছুটির দিনগুলো Lv | | |
| A12 | Inventory → Stock Items | stock-এর মোট মূল্য দেখায়; Low Stock তালিকা | | |

## ৭. ব্রাঞ্চ অ্যাডমিন

| # | কাজ | যা হওয়ার কথা | ফল | মন্তব্য |
|---|---|---|---|---|
| B1 | A2-এর খরচটি approve করুন | post হয়, Accounts Dashboard-এ আসে | | |
| B2 | A5-এর payroll approve করুন | Salary payable post হয় | | |
| B3 | Staff → Leave / Absence: একজন থেরাপিস্টের ছুটি দিন | ওই দিনগুলোতে তাঁর appointment বুক করা যায় না; আগে থেকে বুক থাকলে সংখ্যা জানায় | | |
| B4 | Users → Branch Access: একজন staff-কে আরেকটি branch দিন | শুধু নিজের branch দিতে পারেন | | |
| B5 | Activity Logs → Login History | নিজের branch-এর staff-এর লগইন, ব্যর্থ চেষ্টা দেখায় | | |
| B6 | Reports & Analytics → যেকোনো ৩টি report → PDF ও Excel | ডাউনলোড হয়, বাংলা নাম ঠিক থাকে | | |
| B7 | Inventory: একটি জিনিস reorder level পর্যন্ত ব্যবহার করুন | ঘণ্টায় "Low stock" notification, Low Stock তালিকায় আসে | | |
| B8 | অন্য branch-এর থেরাপিস্টের তথ্য/ছুটি বদলানোর চেষ্টা | সম্ভব নয় (শুধু নিজের branch) | | |
| B9 | থেরাপিস্টের ছুটি দিন (বুক করা appointment-সহ) → Substitute → Preview → Move | ফাঁকা সহকর্মীর কাছে যায়, বাকিগুলোর কারণ দেখায়; অভিভাবক বার্তা পান | | |
| B10 | Classes → class → Substitute trainer (আজ থেকে ২ দিন) | substitute ট্রেইনার notification পান, G5 | | |
| B11 | Reports → একটি রিপোর্ট → **Excel**; Accounts → Balance Sheet → **Excel** | .xlsx খোলে, সংখ্যা যোগ করা যায়, বাংলা নাম ঠিক | | |

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
| S11 | Settings → SMS & WhatsApp: GreenWeb token → Check balance → নিজের নম্বরে Send test | balance দেখায়, SMS আসে; Notifications → SMS / WhatsApp Log-এ "Sent" | | |
| S12 | Settings → Online Payment: bKash / SSLCommerz-এর তথ্য, Sandbox চালু রেখে Save | portal-এ gateway দেখায়; go-live-এর আগে Sandbox বন্ধ না করলে Settings → System-এ সতর্কবার্তা | | |
| S13 | Settings → Staff Attendance: অফিস শুরুর সময় ও সাপ্তাহিক ছুটি | HR sheet-এ সেই দিন "–" দেখায়; দেরিতে check-in "late" | | |
| S14 | Settings → Appointment: অনলাইন বুকিং বন্ধ করুন | portal-এ বুকিং বোতাম আর দেখায় না | | |

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
