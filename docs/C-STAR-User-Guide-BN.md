# C-STAR — ব্যবহার নির্দেশিকা (Staff User Guide)

> সংস্করণ ১.০ · ০৭ অক্টোবর ২০২৬
> কার জন্য: C-STAR-এর রিসেপশন, থেরাপিস্ট, ট্রেইনার, হিসাবরক্ষক ও ব্রাঞ্চ অ্যাডমিন। প্রতিদিনের কাজ কোথায় কীভাবে করবেন। Menu-র নাম ও button screen-এ যেমন ইংরেজিতে আছে, এখানেও তেমনই লেখা।

---

## ০. সবার জন্য

- **লগইন:** ঠিকানা `https://<center-এর ঠিকানা>/login` — email বা মোবাইল নম্বর + password। প্রথমবার লগইনে নতুন password দিতে হবে (অক্ষর + সংখ্যা, অন্তত ৮টি)।
- **কে কোথায় যান:** অফিসের staff → `/app` (কম্পিউটারে), ট্রেইনার → `/trainer`, থেরাপিস্ট → `/therapist` (ফোন/ট্যাবে), অভিভাবক → `/portal` (ফোনে, বাংলায়)।
- **Menu:** বাম পাশে। অংশের নামে click করলে খোলে। উপরের **Find a page…** বক্সে কাজের নাম লিখলেও পাওয়া যায় (যেমন "refund", "leave")।
- **শিশু খোঁজা:** উপরের **Find a child** বক্সে নাম, Patient ID বা মোবাইলের কয়েকটি অক্ষর।
- **ঘণ্টা (🔔):** আপনার জন্য অপেক্ষমাণ কাজ (অনুমোদন, নবায়ন ইত্যাদি)। সব দেখতে: *Notifications → Notification Center*।
- **অনেকক্ষণ কিছু না করলে** নিজে থেকে লগআউট হয় — আবার লগইন করুন। কাজ শেষে সবসময় নিচে বাম কোণের ⇥ চিহ্নে **Sign out** করুন, বিশেষ করে শেয়ার করা কম্পিউটারে।
- **হাজিরা:** অফিসে এসে dashboard-এর (ট্রেইনার/থেরাপিস্ট হলে app-এর) উপরে **Check in**, যাওয়ার সময় **Check out**। অফিস শুরুর সময়ের পরে (Settings-এ যত মিনিট ঠিক করা) এলে "late" হয়। ভুল হলে HR/হিসাবরক্ষককে বলুন — তাঁরা ঠিক করবেন।
- **পাসওয়ার্ড ভুলে গেলে:** login পাতায় **Forgot password?** → নিজের মোবাইল নম্বর → SMS-এ আসা ৬ সংখ্যার কোড ও নতুন password (কোড ১০ মিনিট চলে)। মোবাইল নম্বর ভুল থাকলে IT/অ্যাডমিন password বদলে দেবেন।
- **রিপোর্ট Excel-এ:** যেকোনো রিপোর্টে **PDF**-এর পাশে **Excel** বোতাম — সংখ্যাগুলো Excel-এ যোগ-বিয়োগ করা যায়।
- **নিয়ম:** password কাউকে বলবেন না; একজনের login দিয়ে অন্যজন কাজ করবেন না — system-এ প্রতিটি কাজ কে করেছেন তা লেখা থাকে।

---

## ১. রিসেপশন

### নতুন শিশু নিবন্ধন
*Patients → New Patient* → শিশুর তথ্য → অভিভাবক (একই অভিভাবকের আরেক সন্তান থাকলে মোবাইল নম্বর লিখলেই আগের অভিভাবককে দেখাবে — সেটা বেছে নিন) → consent → **Save**। নতুন Patient ID তৈরি হয়।

### প্রোগ্রামে ভর্তি (Enrollment)
শিশুর profile → **Enrollments** tab → **New enrollment**:
- **Regular Training:** class বেছে নিন (জায়গা খালি থাকলে)।
- **Therapy:** সেবা (Speech, OT …) → থেরাপিস্ট → সপ্তাহে কতবার। তারপর **Therapy** tab → **Weekly slots**-এ সাপ্তাহিক সময় দিন — প্রতি রাতে system পরের কয়েক সপ্তাহের appointment নিজে বানায়।
- assessment-এর পরে থেরাপিস্টের সুপারিশ থাকলে *Assessments → Recommendations* → **Enroll** — ফর্ম আগে থেকে ভরা থাকে।
- একই শিশু একাধিক প্রোগ্রামে থাকতে পারে (Training + Speech + OT)।
- **জায়গা নেই?** *Enrollments → Waiting List* → **Add to waiting list** → শিশু → class বা therapy সেবা → পছন্দের সময় (জরুরি হলে "High priority")। জায়গা খালি হলে (কারো enrollment শেষ বা class বদল) আপনার কাছে notification আসে — তালিকার প্রথম জনকে **Offer place** দিন (অভিভাবক বার্তা পান), ফোন করে নিশ্চিত হলে **Enroll**। Enroll হলে শিশু তালিকা থেকে নিজে থেকেই সরে যায়।

### Appointment
- *Appointments → Today's Appointments* — আজকের board, থেরাপিস্ট অনুযায়ী। **New appointment** → শিশু → সেবা → থেরাপিস্ট → তারিখ → খালি সময়।
- শিশু এলে: *Appointments → Check-in / Queue* → **Check in**। দেরি হলে লাল লেখায় দেখায়। সময় বদলাতে **Reschedule**।
- বাতিল: **Cancel** → কারণ লিখুন → **Cancel appointment**। শুরু হওয়ার ২৪ ঘণ্টার কম আগে বাতিল হলে "late cancellation" — package থেকে session কাটতে পারে।
- না এলে: দিনের শেষে **No show**।
- **অভিভাবকের অনলাইন বুকিং:** therapy-র অভিভাবক portal থেকে নিজের থেরাপিস্টের খালি সময়ে বুক করলে appointment "pending" হয়ে আসে ও আপনার কাছে notification যায় — board-এ **Confirm** করুন। অভিভাবক নিজে বাতিল করলেও notification আসে।
- *Appointments → Calendar* — মাস/সপ্তাহের ছবি; *Appointment Requests* — website ও অভিভাবকের অনুরোধ → ফোন করে নিশ্চিত করে **Book appointment** (নতুন শিশু হলে আগে **Register child**)।

### টাকা নেওয়া
- শিশুর profile → **Billing** tab → **Receive payment** → টাকা, মাধ্যম (Cash / bKash / Nagad / Bank / Card)। bKash/Nagad/Bank হলে transaction ID বাধ্যতামূলক। টাকা আগে পুরনো বিলে যায়, বাড়তি থাকলে "অগ্রিম" — পরের বিলে নিজে থেকে কাটে।
- **রসিদ:** টাকা নেওয়ার পর **Print receipt**, বা পরে পেমেন্টের পাশে PDF চিহ্ন।
- **বিল (Invoice):** Billing tab → **Invoice** (অথবা *Billing & Payments → Invoices* → **New invoice**) → **Issue**। ছাড় দিলে কারণ লিখতে হয়; রিসেপশন সর্বোচ্চ ১০% ছাড় দিতে পারেন, বেশি হলে branch admin / হিসাবরক্ষক।
- **Package বিক্রি:** Billing tab → **Sell package**। টাকা ফেরত (শুধু অগ্রিম থেকে): **Refund**। Session হলে তবেই package থেকে কাটে।
- কার কাছে কত বাকি: *Billing & Payments → Due / Outstanding*।
- অভিভাবক portal থেকে অনলাইনে দিলে রসিদ নিজে থেকেই হয় — *Billing & Payments → Online Payments*-এ দেখুন। "Needs checking" থাকলে হিসাবরক্ষককে জানান।

### দিনের শেষে
*Accounts → Cash Closing* → system দেখায় আজ কত নিয়েছেন → হাতের নোট গুনে লিখুন → কম/বেশি হলে কারণ → close করুন। তারপর cash branch admin / হিসাবরক্ষককে বুঝিয়ে দিন — তাঁরা system-এ **Receive cash** চাপবেন।

### স্টোর / সরঞ্জাম (Inventory)
*Inventory → Stock Items* — প্রতিটি জিনিসের পাশে **Stock in / out**: **Used / issued** (কে নিল/কোন রুমে), **Received** (নতুন কেনা, দামসহ), **Physical count** (মাস শেষে গুনে যত আছে)। নতুন জিনিস: **Add item** (reorder level দিলে stock সেখানে নামলে notification আসে)। *Inventory → Low Stock* — যা কিনতে হবে। কেনার টাকা আগের মতোই *Expenses*-এ লিখুন।

### ছোট খরচ
*Accounts → Expenses* → **Add expense** → কী বাবদ → কত → কোথা থেকে (cash box) → bill-এর ছবি। ৳৫,০০০-এর বেশি হলে অনুমোদন লাগে।

---

## ২. থেরাপিস্ট (ফোন / ট্যাব — `/therapist`)

- **Today:** আজকের appointment ও যেসব note এখনো finalize হয়নি।
- **Session note:** check-in হওয়া appointment → **Start session** (পরে **Continue note**) → কী করলেন, লক্ষ্যের অগ্রগতি, চ্যালেঞ্জ, **বাসায় অনুশীলন**, **অভিভাবকের জন্য সংক্ষেপ** (বাধ্যতামূলক — অভিভাবক portal-এ এটাই দেখেন), internal note (শুধু staff দেখেন) → **Finalize**। Finalize করলে আর বদলানো যায় না, package থেকে ১ session কাটে।
- **Assessment:** *Assessments → New* (বা assessment appointment থেকে **Write assessment**) → ধরন → প্রতিটি অংশের findings → summary → সুপারিশ (কোন therapy, সপ্তাহে কতবার) → **Save draft** বা **Finalize** → **Share with family**। সুপারিশ রিসেপশনের কাছে enroll-এর জন্য যায়।
- **Plan ও লক্ষ্য:** *Patients* → শিশু → plan → লক্ষ্য যোগ / অগ্রগতি % বদলান।
- **বাসায় অনুশীলন:** অভিভাবক portal-এ "করেছি / কিছুটা / পারিনি" ও মন্তব্য দেন — পরের session note লেখার সময় ডান পাশে "Last session"-এর নিচে "Family reported"-এ দেখবেন; সব শিশুর জন্য একসাথে: *Therapy → Home Programs*।
- **অগ্রগতির chart:** শিশুর profile → **Progress** tab — গত ৬ মাসের উপস্থিতি, class performance, therapy session ও প্রতিটি লক্ষ্যের score।
- **Finalize করা note-এ ভুল:** note খুলে ডান পাশে **Amend note** → কোন অংশ → ঠিক লেখা → কারণ → **Save amendment**। আগের লেখা, আপনার নাম, সময় ও কারণ সবসময় থেকে যায় (মুছে ফেলা যায় না); অভিভাবকের সংক্ষেপ বদলালে portal-এও ঠিক হয়। Assessment-এও একইভাবে।
- **Plan review:** plan-এর review তারিখ ৭ দিনের মধ্যে এলে বা পেরিয়ে গেলে সকালে ঘণ্টায় মনে করিয়ে দেয় — লক্ষ্য দেখে নতুন review তারিখ দিন।
- **Clinical supervisor হলে** (অ্যাডমিন Therapists → Edit-এ চালু করেন): app-এ **Review** tab — সহকর্মীদের finalize করা note ও assessment পড়ে **OK** বা **Needs changes** (মন্তব্যসহ); Needs changes হলে লেখক notification পান ও amend করেন।
- **Schedule** — সপ্তাহের কাজ; **Payslips** — নিজের বেতনের রসিদ।
- রিসেপশন clinical note দেখতে পান না; শুধু আপনি নিজের লেখা assessment বদলাতে পারেন।

## ৩. ট্রেইনার (ফোন — `/trainer`)

- **Today:** আজকের class → **Attendance** → প্রত্যেকের Present / Absent / Late / Leave → **Save attendance**।
- **Records:** উপস্থিত শিশুদের training record — কী activity, কেমন করল, অভিভাবকের জন্য একটি লাইন।
- **Students:** নিজের শিশুদের তালিকা, ITP (training plan) ও লক্ষ্যের অগ্রগতি।
- **Payslips:** নিজের বেতনের রসিদ।
- অন্য ট্রেইনারের class-এ **substitute** দেওয়া হলে সেই দিনগুলোতে class-টি Today-তে আসে — হাজিরা ও record আগের মতোই।
- Finalize করা record-এ ভুল: record খুলে **Amend note** (কারণসহ)।

## ৪. হিসাবরক্ষক

| কাজ | কোথায় |
|---|---|
| খরচ লেখা | *Accounts → Expenses* → **Add expense** (৳৫,০০০ পর্যন্ত সাথে সাথে post; বেশি হলে অন্য কেউ approve) |
| Voucher (Payment / Receipt / Journal / Contra) | *Accounts → Payment Vouchers* ইত্যাদি → **New voucher**; অনুমোদন: **Approve & post** / **Reject**; posted voucher মোছা যায় না — **Reverse** |
| সরবরাহকারীর bill ও পরিশোধ | *Accounts → Vendors* → vendor → **Bill** → **Save bill** / **Pay**; সব bill: *Bills / Payables* |
| ব্যাংক মেলানো | *Accounts → Bank Reconciliation* → **Start** → **Import CSV** (ব্যাংকের statement) → **Auto-match** → বাকিগুলো **Match** / ব্যাংক charge হলে **Book as …** → পার্থক্য ০ হলে **Complete** |
| Cash গ্রহণ | *Accounts → Cash Closing* → রিসেপশনের closing → **Receive cash** |
| বেতন | *Accounts → Payroll* → **New payroll** → **Calculate** → প্রত্যেকের সমন্বয় (bonus, অনুপস্থিতি, tax) → branch admin **Approve** করলে → **Pay** → payslip / **Salary sheet** PDF; ভুল হলে পরিশোধের আগে **Reopen** |
| অগ্রিম (staff advance) | *Staff → All Staff* → employee → **Give advance**; বাকি: *Accounts → Employee Advances* |
| সম্পদ ও অবচয় | *Accounts → Fixed Assets* → নতুন সম্পদ **Register**; মাসে একবার **Charge depreciation up to** (মাস) → **Run** |
| রিপোর্ট | *Reports & Analytics → Accounts Reports*: Profit & Loss, Balance Sheet, Trial Balance, Ledger, Day Book, Cash Flow (PDF) |
| মাস বন্ধ | মাস শেষে সব মিলিয়ে *Accounts → Accounting Periods* → **Close month**; বছর শেষে **Close the year** |
| Budget | *Accounts → Budgets* — বছরের budget ও Budget বনাম আসল |

**Excel:** *Accounts → Financial reports*-এর প্রতিটি রিপোর্টে **Excel** — PDF-এর মতোই টেবিল, সংখ্যা Excel-এ হিসাবযোগ্য।

**Staff-এর হাজিরা:** *Staff → Staff Attendance* — মাসের sheet (P = উপস্থিত, L = দেরি, A = অনুপস্থিত, Lv = ছুটি, Hol = holiday, – = সাপ্তাহিক ছুটি, ? = কিছু লেখা নেই)। কোনো দিনে click করে উপস্থিতি/সময় লিখুন বা ঠিক করুন; ছুটি *Leave / Absence*-এ লিখলে sheet-এ নিজে থেকে বসে। বেতন কাটার হিসাব (অনুপস্থিতি) payroll-এ আগের মতো হাতে দিন।

## ৫. ব্রাঞ্চ অ্যাডমিন

- **থেরাপিস্ট ছুটিতে:** *Therapy → Therapists* → **Leave** → ছুটির তারিখ দিন; বুক করা appointment থাকলে নিচে **Substitute** — একই সেবা দেন এমন সহকর্মী বেছে **Preview** → **Move**। যিনি সেই সময়ে ফাঁকা নন বা সেবাটি দেন না, তাঁর ক্ষেত্রে কারণ দেখায়; অভিভাবক বাংলায় বার্তা পান।
- **ট্রেইনার ছুটিতে:** *Training → Classes* → class → **Substitute trainer** → ট্রেইনার ও তারিখ → **Add substitute** — শুধু সেই দিনগুলোতে তিনি class-এর হাজিরা ও record দেখতে/লিখতে পারেন।
- **Clinical supervisor:** *Therapists* → Edit → "Clinical supervisor" টিক — সেই থেরাপিস্ট branch-এর সব therapy note review করতে পারবেন।

- **অনুমোদন (🔔 দেখুন):** বড় খরচ/voucher (*Payment Vouchers* → Waiting approval), payroll (*Payroll* → run → **Approve**), cash গ্রহণ।
- **Staff:** নতুন login *Users → Add User*; কে কোন শাখায় *Users → Branch Access*; ছুটি *Staff → Leave / Absence* (থেরাপিস্টের ছুটিতে booking বন্ধ হয়, আগের appointment থাকলে সংখ্যা জানায় — সেগুলো reschedule করুন)।
- **নজরদারি:** *Dashboard* (আজকের অবস্থা, যা মনোযোগ চায়), *Training Dashboard*, *Therapy Dashboard*, *Billing Dashboard*, *Reports & Analytics* (PDF / Excel)।
- **নিরাপত্তা:** *Activity Logs → Login History* (ব্যর্থ লগইন), *Clinical Access Logs* (কে কোন শিশুর clinical তথ্য খুলেছেন)।

## ৬. সুপার অ্যাডমিন (কেন্দ্র প্রধান / IT)

- *Settings* — কেন্দ্রের তথ্য, Patient ID, appointment-এর নিয়ম (অভিভাবকের অনলাইন বুকিং/বাতিল চালু-বন্ধ, কতদিন আগে পর্যন্ত), **Staff Attendance** (অফিস শুরুর সময়, কত মিনিট পরে late, সাপ্তাহিক ছুটি, নিজে check-in চালু/বন্ধ), PDF, নিরাপত্তা (idle timeout, password), **Backup** (সপ্তাহে একবার download করে বাইরে রাখুন), **System** (go-live checklist), **Go-live** (opening balance, শিশুদের import)।
- *Users → Roles / Permissions* — কোন ভূমিকা কী করতে পারে।
- *Website / CMS* — website-এর লেখা, ছবি, সেবা, FAQ, notice, home page-এর অংশ, SEO।
- *Notifications → Templates* — অভিভাবকের কাছে যাওয়া বাংলা বার্তার লেখা (portal, email ও SMS-এ একই লেখা যায়)।
- *Settings → SMS & WhatsApp* — GreenWeb-এর মাধ্যমে SMS চালু/বন্ধ, কোন বার্তা SMS-এ যাবে, balance, পরীক্ষার SMS; *Notifications → SMS / WhatsApp Log* — কোন SMS গেছে, কোনটি ব্যর্থ (**Send again**)।

---

## ৭. অভিভাবকদের যা বলবেন (portal)

1. ফোনে `https://<center-এর ঠিকানা>/login` খুলুন → মোবাইল নম্বর ও রিসেপশন থেকে দেওয়া password → নতুন password দিন।
2. **হোম:** সন্তানের প্রোগ্রাম, পরের appointment, উপস্থিতি, বকেয়া। **সময়সূচি:** appointment ও অনুরোধ পাঠানো। **অগ্রগতি:** উপস্থিতি, লক্ষ্য, থেরাপিস্ট/ট্রেইনারের নোট, রিপোর্ট PDF। **বিল:** বিল ও রসিদ; **অনলাইনে পরিশোধ করুন** — বিকাশ বা কার্ড/নগদ/রকেট দিয়ে বকেয়া পরিশোধ, রসিদ সাথে সাথে।
3. **সময়সূচি → "খালি সময়ে সেশন বুক করুন"** — নিজের থেরাপিস্টের খালি সময় দেখে বুক করুন; রিসেপশন নিশ্চিত করলে জানানো হবে। আসন্ন appointment-এর পাশে **বাতিল** (২৪ ঘণ্টার কম আগে হলে package থেকে session কাটতে পারে)।
4. **হোম → বাসায় অনুশীলন:** প্রতিদিন "করেছি / কিছুটা / পারিনি" চাপুন, চাইলে মন্তব্য — থেরাপিস্ট দেখেন। **অগ্রগতি** পাতার উপরে গত ৬ মাসের chart।
5. Portal চালু করতে: শিশুর profile → **Guardians** tab → অভিভাবক → **Create portal login** (password দিন ও অভিভাবককে জানান); "May see this child in the parent portal" চালু আছে কিনা দেখুন।

## ৮. সাহায্য

কোনো সমস্যা হলে screenshot নিয়ে [IT যোগাযোগ] কে জানান: কোন page, কী করছিলেন, কী লেখা এসেছে। ভুল করে কিছু post/issue করলে মুছবেন না — void / reverse করুন (কারণসহ), তাহলে হিসাব ঠিক থাকে।
