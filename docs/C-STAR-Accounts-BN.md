# C-STAR — সম্পূর্ণ Accounts Module (বাংলা)

> সংস্করণ: v1.0 · তারিখ: ০৬ অক্টোবর ২০২৬ · মূল plan: [README.md](../README.md)

---

## ০. Billing আর Accounts-এর পার্থক্য

| | Billing (আগের plan-এ আছে) | Accounts (নতুন) |
|---|---|---|
| বিষয় | রোগীর কাছ থেকে টাকা: invoice, payment, due | পুরো center-এর আর্থিক হিসাব: আয়, **খরচ**, ব্যাংক, cash, বেতন, সম্পদ, দায় |
| কে ব্যবহার করবে | Receptionist, Accountant | Accountant, Admin, মালিক/Management |
| ফলাফল | Receipt, Due list | Cash Book, Ledger, Trial Balance, Profit & Loss, Balance Sheet |

**মূল নীতি:** Billing আর Accounts আলাদা কাজ, কিন্তু **একই data**। Receptionist invoice বা payment নিলে Accounts-এ নিজে থেকেই journal entry তৈরি হবে (auto-posting)। Accountant-কে একই টাকা দুবার লিখতে হবে না।

```
Billing (Invoice/Payment/Refund) ─┐
Package session consumed ─────────┤
Expense / Vendor Bill ────────────┼──► Journal Entry (Double-entry) ──► Ledger ──► Reports
Payroll ──────────────────────────┤                                    (Cash Book, Trial Balance,
Manual Voucher ───────────────────┘                                     P&L, Balance Sheet)
```

ভেতরে পূর্ণ **double-entry accounting** থাকবে, প্রতিটি লেনদেনে Debit = Credit। তবে UI এমনভাবে বানানো হবে যাতে accounting না জানা staff-ও খরচ লিখতে পারে, যেমন শুধু "বিদ্যুৎ বিল ৳৫,০০০, Cash থেকে"। Debit/Credit system নিজেই ঠিক করবে।

---

## ১. Accounts Module-এর অংশসমূহ

| # | অংশ | কাজ |
|---|---|---|
| ১ | **Chart of Accounts** | সব হিসাবের খাতের তালিকা (Asset, Liability, Equity, Income, Expense), গাছের মতো স্তরে সাজানো |
| ২ | **Fund Accounts** | প্রতিটি branch-এর Cash Box, Bank account, bKash, Nagad wallet ও তাদের balance |
| ৩ | **Voucher** | Receipt, Payment, Journal, Contra voucher |
| ৪ | **Auto-posting** | Billing, package, payroll থেকে স্বয়ংক্রিয় journal entry |
| ৫ | **Expense Management** | খরচের category, bill-এর ছবি, recurring খরচ (ভাড়া, ইন্টারনেট) |
| ৬ | **Vendor ও Payable** | Supplier, তাদের bill, বাকি পরিশোধ |
| ৭ | **Payroll** | সব staff-এর বেতন, allowance, কর্তন, অগ্রিম, therapist-এর per-session পারিশ্রমিক |
| ৮ | **Cash Closing** | দিন শেষে receptionist ও branch-ভিত্তিক cash মিলানো |
| ৯ | **Bank Reconciliation** | Bank statement-এর সাথে system মিলানো |
| ১০ | **Fixed Assets** | Furniture, therapy equipment, computer; depreciation |
| ১১ | **Fiscal Year ও Period Lock** | বছর ও মাস বন্ধ করা, যাতে পুরনো হিসাব বদলানো না যায় |
| ১২ | **Budget** | খাতভিত্তিক বাজেট বনাম প্রকৃত খরচ |
| ১৩ | **Financial Reports** | Cash/Bank Book, Ledger, Trial Balance, P&L, Balance Sheet, Cash Flow ইত্যাদি |

---

## ২. Chart of Accounts (C-STAR-এর জন্য প্রস্তাবিত default)

Code-এর প্রথম অঙ্ক থেকে ধরন বোঝা যাবে। Admin নতুন খাত যোগ করতে পারবে। তবে **System account** (যেগুলো auto-posting-এ ব্যবহার হয়) মুছতে পারবে না।

```
1000 ASSETS (সম্পদ)
  1100 Cash & Cash Equivalents
    1110 Cash in Hand – Branch [প্রতি branch-এর জন্য আলাদা]   (system)
    1120 Petty Cash – Branch
    1130 Bank Accounts → 1131 [Bank-এর নাম] ...
    1140 Mobile Financial Services → 1141 bKash Merchant, 1142 Nagad Merchant
  1200 Accounts Receivable – Patients (রোগীর কাছে পাওনা)        (system)
  1300 Staff Advances (staff-কে দেওয়া অগ্রিম)                    (system)
  1400 Prepaid Expenses / Security Deposit (ভাড়ার অগ্রিম)
  1500 Inventory – Therapy Materials (ঐচ্ছিক)
  1600 Fixed Assets
    1610 Furniture & Fixtures   1620 Therapy Equipment   1630 Computer & IT
    1690 Accumulated Depreciation (contra)

2000 LIABILITIES (দায়)
  2100 Accounts Payable – Vendors (supplier-এর কাছে দেনা)        (system)
  2200 Patient Advances (রোগীর অগ্রিম জমা)                       (system)
  2300 Unearned Package Revenue (package-এর টাকা, session এখনো হয়নি) (system)
  2400 Salary Payable                                            (system)
  2500 Tax/VAT Payable (TDS ইত্যাদি)
  2600 Loans

3000 EQUITY (মূলধন)
  3100 Owner's Capital   3200 Owner's Drawings   3300 Retained Earnings (system)

4000 INCOME (আয়)
  4100 Registration & Admission Fee
  4200 Assessment Income
  4300 Training Fee Income
  4400 Therapy Income → 4410 Speech, 4420 OT, 4430 ABA, 4440 OPT, 4450 Special Education ...
  4500 Parent Guidance / Consultation
  4800 Donation & Grant Income
  4900 Other Income
  4950 Discount Allowed (contra-income — আয় থেকে বাদ)          (system)

5000 EXPENSES (খরচ)
  5100 Salary & Benefits → 5110 Therapist, 5120 Trainer, 5130 Admin & Reception, 5140 Support Staff, 5150 Festival Bonus
  5200 Therapist Session Payout (per-session/part-time)
  5300 Rent
  5400 Utilities → Electricity, Water, Gas, Internet, Mobile
  5500 Therapy & Training Materials (খেলনা, শিক্ষা উপকরণ)
  5600 Office & Stationery
  5700 Repair & Maintenance
  5800 Marketing & Advertising
  5900 Transport & Conveyance
  5950 Bank & MFS Charges (bKash/Nagad ফি)                        (system)
  5960 Depreciation Expense                                      (system)
  5990 Miscellaneous Expense
```

**Branch মাত্রা:** প্রতিটি Chart of Accounts সব branch-এর জন্য একই। প্রতিটি journal line-এ `branch_id` থাকবে। এতে একটি report-এ "সব branch একসাথে" বা "শুধু Dhaka branch-এর P&L" দুটোই দেখা যাবে। Cash in Hand আলাদা কারণ প্রতিটি branch-এর আলাদা cash box।

**Service মাত্রা:** Income line-এ `service_id` রাখা হবে, যাতে Speech, OT, Training কোনটা থেকে কত আয় হলো তা report-এ দেখা যায়।

---

## ৩. Auto-posting নিয়ম (Billing → Accounts)

| ঘটনা | Debit | Credit |
|---|---|---|
| Invoice issue (training fee, assessment, admission, per-session therapy) | Accounts Receivable | সংশ্লিষ্ট Income (gross amount) |
| Invoice-এ discount | Discount Allowed | Accounts Receivable |
| **Package invoice issue** | Accounts Receivable | **Unearned Package Revenue** |
| **Package-এর একটি session সম্পন্ন** | Unearned Package Revenue | Therapy Income (মূল্য ÷ মোট session) |
| Package-এর মেয়াদ শেষ, session বাকি | Unearned Package Revenue | Therapy Income (বাকি অংশ, "expired package" নোট সহ) |
| Payment (Cash) | Cash in Hand – সেই Branch | Accounts Receivable |
| Payment (bKash/Nagad) | bKash/Nagad Wallet | Accounts Receivable |
| bKash/Nagad-এর ফি | Bank & MFS Charges | bKash/Nagad Wallet |
| Payment (Bank/Card) | Bank Account | Accounts Receivable |
| Invoice-এর বাইরে অতিরিক্ত টাকা (অগ্রিম) | Cash/Bank | Patient Advances |
| অগ্রিম পরে invoice-এ সমন্বয় | Patient Advances | Accounts Receivable |
| Refund | Patient Advances / Accounts Receivable | Cash/Bank |
| Invoice void | আগের entry-এর **উল্টো entry** (reversal) | — |

**নিয়ম:**
- Auto-posted entry হাতে edit করা যাবে না। ভুল হলে মূল billing record ঠিক করতে হবে, তাতে system নিজে reversal ও নতুন entry দেবে।
- যদি ১ মাস বা অন্য কোনো period বন্ধ (lock) থাকে, পরে কোনো সংশোধন হলে তা **চলতি period-এ** post হবে।
- **গুরুত্বপূর্ণ:** Billing module চালু হওয়ার প্রথম দিন থেকেই auto-posting চলবে, Accounts-এর UI পরে এলেও। নাহলে পুরনো লেনদেন accounts-এ থাকবে না।

---

## ৪. Voucher System

| Voucher | ব্যবহার | উদাহরণ |
|---|---|---|
| **Payment Voucher (PV)** | টাকা বের হলো | ভাড়া ৳৫০,০০০ Bank থেকে, খেলনা কেনা Cash থেকে |
| **Receipt Voucher (RV)** | Billing-এর বাইরে টাকা এলো | Donation, পুরনো ফার্নিচার বিক্রি |
| **Journal Voucher (JV)** | Cash নড়াচড়া নেই এমন সমন্বয় | Depreciation, ভুল সংশোধন, opening balance |
| **Contra Voucher (CV)** | নিজের এক account থেকে আরেক account-এ | Cash ব্যাংকে জমা, bKash থেকে ব্যাংকে transfer |

**Voucher-এর তথ্য:** voucher_no (যেমন `PV-2026-00045`), তারিখ, branch, ধরন, narration (বিবরণ), lines (account, debit, credit, party, memo), সংযুক্তি (bill/রসিদের ছবি), প্রস্তুতকারী, অনুমোদনকারী।

**অনুমোদন প্রবাহ (Maker-Checker):**
```
Draft (Accountant লেখে) → Submitted → Approved (Branch Admin/Super Admin) → Posted (ledger-এ যায়)
                                    ↘ Rejected (কারণ সহ)
Posted voucher বাতিল = Reversal voucher (মুছে ফেলা নয়)
```
- নির্দিষ্ট সীমার (যেমন ৳৫,০০০) নিচের খরচ accountant নিজে post করতে পারবে। এর উপরে অনুমোদন লাগবে। সীমা settings থেকে বদলানো যাবে।
- Posted voucher কখনো delete হবে না।

---

## ৫. Expense Management

- **সহজ খরচ form:** তারিখ → Branch → Category (যেমন "বিদ্যুৎ বিল") → পরিমাণ → কোথা থেকে দেওয়া হলো (Cash/Bank/bKash) → Vendor (ঐচ্ছিক) → বিবরণ → bill-এর ছবি। এটি save করলে system নিজেই Payment Voucher বানাবে।
- **Expense Category:** প্রতিটি category একটি expense account-এর সাথে যুক্ত (map করা)। ফলে staff account code না জেনেও খরচ লিখতে পারবে।
- **Petty Cash:** প্রতিটি branch-এর ছোট খরচের জন্য নির্দিষ্ট পরিমাণ (imprest)। Receptionist ছোট খরচ লিখবে, accountant মাসে একবার পূরণ (replenish) করবে।
- **Recurring খরচ:** ভাড়া, ইন্টারনেট, নিরাপত্তা ইত্যাদি প্রতি মাসে নির্দিষ্ট তারিখে draft হিসেবে নিজে তৈরি হবে। Accountant শুধু যাচাই করে post করবে।

---

## ৬. Vendor ও Accounts Payable

- **Vendor:** নাম, ফোন, ঠিকানা, ধরন (বাড়িওয়ালা, উপকরণ সরবরাহকারী, utility), opening balance।
- **Vendor Bill (বাকিতে কেনা):** Bill পাওয়ার সময় Dr Expense / Cr Accounts Payable। পরে পরিশোধ করলে Dr Accounts Payable / Cr Cash/Bank।
- আংশিক পরিশোধ, vendor ledger, কার কাছে কত দেনা (payables aging) দেখা যাবে।

---

## ৭. Payroll (বেতন)

### ৭.১ নতুন ধারণা: Employee
এখন পর্যন্ত plan-এ শুধু Trainer আর Therapist ছিল। কিন্তু বেতন পায় **সব staff**: receptionist, accountant, admin, আয়া, পরিচ্ছন্নতাকর্মী, নিরাপত্তারক্ষী। তাই একটি **`employees`** table লাগবে:

```
employees (সব staff — HR ও বেতনের জন্য)
   ├── trainers.employee_id    (trainer হলে)
   ├── therapists.employee_id  (therapist হলে)
   └── users.employee_id       (login থাকলে)
```
Trainer ≠ Therapist নীতি ঠিকই থাকে। Employee শুধু বেতন/HR-এর জন্য একটি সাধারণ স্তর।

### ৭.২ বেতনের ধরন (প্রতি employee-তে আলাদা)
| ধরন | কার জন্য | হিসাব |
|---|---|---|
| **মাসিক নির্দিষ্ট** | Full-time staff | Basic + House rent + Medical + Conveyance ইত্যাদি |
| **Per-session** | Part-time/visiting therapist | মাসে সম্পন্ন (finalized) therapy session সংখ্যা × প্রতি session-এর হার (service অনুযায়ী আলাদা হতে পারে) |
| **মিশ্র** | Full-time + অতিরিক্ত session | নির্দিষ্ট বেতন + নির্দিষ্ট সংখ্যার বেশি session-এর জন্য অতিরিক্ত |
| **Revenue share** (ঐচ্ছিক) | কিছু therapist | তার session থেকে আসা আয়ের নির্দিষ্ট % |

Per-session ও revenue share-এর হিসাব **সরাসরি `therapy_sessions` থেকে** আসবে। এতে therapist আর accountant-এর হিসাবে অমিল হবে না।

### ৭.৩ মাসিক Payroll প্রবাহ
```
Payroll Run তৈরি (মাস, branch)
  → system প্রতি employee-র বেতন হিসাব করে
     (নির্দিষ্ট অংশ + session payout + bonus − অগ্রিম কর্তন − অনুপস্থিতি কর্তন − tax)
  → Accountant যাচাই ও সমন্বয় → Admin অনুমোদন
  → Post:   Dr Salary Expense (বিভাগ অনুযায়ী) / Cr Salary Payable, Cr Staff Advance, Cr Tax Payable
  → পরিশোধ: Dr Salary Payable / Cr Bank বা Cash (একসাথে বা আলাদাভাবে)
  → Payslip (PDF), Salary Sheet
```
- **Staff Advance:** অগ্রিম দিলে Dr Staff Advance / Cr Cash। পরে কয়েক মাসের বেতন থেকে কিস্তিতে কাটা যাবে।
- **উৎসব bonus:** দুই ঈদের bonus আলাদা run হিসেবে।
- **Staff attendance:** অনুপস্থিতির জন্য বেতন কাটতে হলে staff attendance লাগবে। এটা MVP-তে হাতে লেখা হবে, পরে biometric যুক্ত করা যাবে।

---

## ৮. Cash Closing (দিন শেষের হিসাব)

```
দিন শেষে Receptionist → "Close My Cash"
  → system দেখায়: আজ আপনার নেওয়া Cash ৳X, bKash ৳Y, Nagad ৳Z, Card ৳W
  → Receptionist হাতে গোনা cash লেখে (চাইলে নোট অনুযায়ী: ১০০০×৫, ৫০০×৩ ...)
  → পার্থক্য (কম/বেশি) কারণ সহ
  → Branch Admin/Accountant গ্রহণ করে (handover) → cash vault বা bank-এ জমা (Contra voucher)
```
- পার্থক্য থাকলে তা "Cash Short/Over" খাতে যাবে এবং report-এ দেখাবে।
- Close করার পর ওই দিনের payment আর বদলানো যাবে না, শুধু Accountant বিশেষ অনুমতিতে পারবে।

---

## ৯. Bank Reconciliation

- Bank statement (CSV/Excel) upload করা যাবে, অথবা হাতে লেখা যাবে।
- System নিজে মিলিয়ে দেখাবে: কোন লেনদেন মিলেছে, কোনগুলো শুধু bank-এ (যেমন bank charge, interest), আর কোনগুলো শুধু system-এ (যেমন চেক এখনো জমা হয়নি)।
- Reconciliation report: Bank balance বনাম Book balance।
- একইভাবে bKash/Nagad merchant statement মেলানো যাবে।

---

## ১০. Fixed Assets

- Asset register: নাম, category, কেনার তারিখ, মূল্য, branch, অবস্থান, আয়ু (বছর), depreciation পদ্ধতি (Straight-line)।
- মাসিক বা বার্ষিক depreciation স্বয়ংক্রিয়ভাবে JV হিসেবে তৈরি হবে।
- Asset বিক্রি বা বাতিল করলে লাভ/ক্ষতি হিসাব হবে।

---

## ১১. Fiscal Year ও Period Lock

- **Fiscal Year:** জুলাই–জুন (বাংলাদেশের আয়কর বছর)। Settings থেকে বদলানো যাবে।
- প্রতিটি মাস একটি **period**, যার অবস্থা Open অথবা Closed। মাস বন্ধ হলে ওই মাসের তারিখে কোনো entry বা edit করা যাবে না।
- **বছর শেষে:** Income ও Expense-এর balance Retained Earnings-এ স্থানান্তর হবে (closing entry) এবং পরের বছরের opening balance তৈরি হবে।
- **Go-live:** system চালুর তারিখে opening balance (cash, bank, পাওনা, দেনা, সম্পদ) একটি Opening JV দিয়ে লেখা হবে।

---

## ১২. Financial Reports

| Report | বিবরণ |
|---|---|
| **Cash Book** | Branch অনুযায়ী দৈনিক cash আয়-ব্যয় ও running balance |
| **Bank Book / MFS Book** | প্রতিটি bank, bKash ও Nagad-এর লেনদেন |
| **General Ledger** | যেকোনো account-এর সব লেনদেন, তারিখ পরিসর অনুযায়ী |
| **Day Book** | একদিনের সব voucher |
| **Trial Balance** | সব account-এর debit/credit balance (Debit = Credit যাচাই) |
| **Income Statement (P&L)** | আয় − খরচ = লাভ/ক্ষতি; মাসিক বা বার্ষিক; branch অনুযায়ী তুলনা |
| **Balance Sheet** | সম্পদ = দায় + মূলধন, নির্দিষ্ট তারিখে |
| **Cash Flow Statement** | Operating/Investing/Financing |
| **Receipts & Payments** | NGO ধাঁচের প্রতিষ্ঠানের জন্য প্রচলিত format |
| **Service-wise Income** | Speech/OT/ABA/Training কোনটা থেকে কত আয় |
| **Branch Profitability** | প্রতি branch-এর আয়, খরচ, লাভ |
| **Expense Analysis** | খাতভিত্তিক খরচ, মাসের তুলনা, budget বনাম প্রকৃত |
| **Receivables Aging** | রোগীর বাকি: ০–৩০, ৩১–৬০, ৬১–৯০, ৯০+ দিন |
| **Payables Aging** | Vendor-এর দেনা |
| **Payroll Reports** | Salary sheet, payslip, therapist payout, অগ্রিমের balance |
| **Cash Closing Report** | Receptionist ও দিন অনুযায়ী পার্থক্য |
| **Collection Report** | Payment method অনুযায়ী (Cash/bKash/Nagad/Bank/Card) |

সব report PDF ও Excel-এ export হবে। Report-এ বাংলা ঠিকমতো দেখাতে mPDF ব্যবহার হবে। প্রতিটি export audit log-এ লেখা হবে।

---

## ১৩. Database Table (Accounts)

| Table | মূল field |
|---|---|
| `fiscal_years` | name (২০২৬–২৭), start_date, end_date, status (open/closed) |
| `accounting_periods` | fiscal_year_id, month, start_date, end_date, status (open/closed), closed_by, closed_at |
| `accounts` | code, name, name_bn, type (asset/liability/equity/income/expense), parent_id, is_group, normal_balance (debit/credit), subtype (cash/bank/mfs/receivable/payable/…), branch_id (cash box-এর জন্য), is_system, is_active, opening_balance |
| `bank_accounts` | account_id, bank_name, branch_name, account_no, routing_no |
| `journal_entries` | voucher_no, voucher_type (receipt/payment/journal/contra/system), date, branch_id, fiscal_year_id, period_id, narration, source_type/source_id (invoice/payment/payroll/expense…), status (draft/submitted/approved/posted/reversed), reversal_of_id, prepared_by, approved_by, posted_at |
| `journal_lines` | journal_entry_id, account_id, debit, credit, branch_id, service_id (income বিশ্লেষণ), party_type/party_id (patient/vendor/employee), memo |
| `posting_rules` | event (invoice_issued, payment_cash…), debit_account_id, credit_account_id — settings থেকে map বদলানো যায় |
| `expense_categories` | name, name_bn, account_id, is_active |
| `expenses` | expense_no, date, branch_id, expense_category_id, amount, paid_from_account_id, vendor_id, description, attachment, journal_entry_id, status |
| `recurring_expenses` | expense_category_id, amount, day_of_month, paid_from_account_id, vendor_id, is_active |
| `vendors` | name, phone, address, type, opening_balance, is_active |
| `vendor_bills` / `vendor_bill_items` / `vendor_payments` | bill_no, vendor_id, date, due_date, total, paid, status / items / payment |
| `employees` | employee_code, name, designation, department, branch_id, joining_date, employment_type (full/part/visiting), pay_type (fixed/per_session/mixed/revenue_share), bank/MFS info, status |
| `salary_structures` | employee_id, basic, house_rent, medical, conveyance, other_allowances (json), effective_from |
| `session_pay_rates` | employee_id, service_id, rate_per_session অথবা revenue_share_percent, effective_from |
| `employee_advances` | employee_id, date, amount, installment_amount, balance, journal_entry_id |
| `payroll_runs` | month, branch_id, type (salary/bonus), status (draft/approved/posted/paid), journal_entry_id |
| `payroll_items` | payroll_run_id, employee_id, fixed_amount, session_count, session_pay, bonus, advance_deduction, absence_deduction, tax, net_pay, paid_at |
| `cash_closings` | branch_id, user_id, date, expected (json, method অনুযায়ী), counted_cash, denominations (json), difference, reason, received_by, status |
| `bank_reconciliations` / `bank_statement_lines` | account_id, statement_date, statement_balance, status / তারিখ, বিবরণ, পরিমাণ, matched_journal_line_id |
| `fixed_assets` | asset_code, name, category, branch_id, purchase_date, cost, useful_life_months, salvage_value, accumulated_depreciation, status |
| `budgets` / `budget_lines` | fiscal_year_id, branch_id / account_id, month, amount |

**Database নিয়ম:**
- `journal_lines`-এ debit ও credit দুটিই `DECIMAL(14,2) >= 0`, এবং একটি line-এ শুধু একটির মান থাকবে।
- একটি entry post করার সময় Service layer একটি transaction-এর মধ্যে যাচাই করবে Σdebit = Σcredit। না মিললে post হবে না।
- Account balance হিসাব হবে `journal_lines` থেকে। দ্রুত report-এর জন্য মাসিক balance cache (`account_period_balances`) রাখা যাবে।

---

## ১৪. Role ও Permission (Accounts)

| কাজ | Super Admin | Branch Admin | Accountant | Receptionist | অন্যরা |
|---|---|---|---|---|---|
| Chart of Accounts setup | F | — | F | — | — |
| Expense লেখা | F | B | F | Petty cash (সীমা পর্যন্ত) | — |
| Voucher তৈরি | F | B | F | — | — |
| Voucher অনুমোদন | F | B (সীমা পর্যন্ত) | সীমার নিচে নিজে | — | — |
| Payroll তৈরি | F | — | F | — | — |
| Payroll অনুমোদন | F | B | — | — | — |
| নিজের payslip দেখা | ✓ | ✓ | ✓ | ✓ | ✓ (Trainer/Therapist নিজের) |
| Cash Closing | F | B (গ্রহণ) | F (গ্রহণ) | নিজের | — |
| Bank Reconciliation | F | — | F | — | — |
| Period/Year Close | F | — | প্রস্তাব | — | — |
| Financial Reports | F | B | F | নিজের collection | — |
| Balance Sheet / পুরো P&L | F | B (নিজ branch) | F | — | — |

নতুন permission উদাহরণ: `accounts.coa.manage`, `accounts.voucher.create`, `accounts.voucher.approve`, `accounts.expense.create`, `accounts.payroll.manage`, `accounts.payroll.approve`, `accounts.period.close`, `accounts.reports.view`, `accounts.reports.financial_statements`।

**প্রয়োজন হলে নতুন role:** "Finance Manager", যার কাজ approval ও period close। ছোট center-এ এই কাজ Super Admin-ই করবে।

---

## ১৫. UI পরিকল্পনা (Accounts)

**Admin sidebar-এ নতুন "Accounts" group:**
```
Accounts
├── Accounts Dashboard
├── Expenses
├── Vouchers (PV / RV / JV / CV)
├── Vendors & Bills
├── Payroll → Employees · Salary Setup · Payroll Runs · Advances · Therapist Payout
├── Cash Closing
├── Bank Reconciliation
├── Fixed Assets
├── Budget
├── Financial Reports
└── Accounts Settings → Chart of Accounts · Fiscal Year · Periods · Posting Rules · Approval Limits
```

**Accounts Dashboard:**
- Card: Cash in Hand (branch অনুযায়ী), Bank balance, bKash/Nagad balance, আজকের আয় বনাম খরচ, এই মাসের লাভ/ক্ষতি, মোট রোগী-বাকি, মোট vendor-দেনা, বেতন বকেয়া
- Chart: মাসিক আয় বনাম খরচ (১২ মাস), খরচের খাতভিত্তিক ভাগ, service অনুযায়ী আয়, branch তুলনা
- কাজের তালিকা: অনুমোদনের অপেক্ষায় voucher, close হয়নি এমন cash, এই মাসের recurring খরচ, মেলানো হয়নি এমন bank লেনদেন

**Voucher form:** উপরে তারিখ, branch ও ধরন। নিচে line-এর table (account খুঁজে বসানো, debit, credit, party, memo)। নিচে Debit ও Credit-এর মোট দেখাবে, না মিললে লাল হবে এবং save হবে না। সংযুক্তি upload করা যাবে।

**Mobile:** Expense entry আর Cash Closing mobile-বান্ধব হবে, কারণ receptionist ও branch admin ফোন থেকেও করবে। Voucher ও report মূলত desktop-এর জন্য।

---

## ১৬. API (Accounts)

```
CRUD   /accounts/chart                 GET /accounts/chart/tree
CRUD   /accounts/fiscal-years          POST /accounts/periods/{id}/close | reopen
GET|POST /accounts/vouchers            GET|PUT /accounts/vouchers/{id}
POST   /accounts/vouchers/{id}/submit | approve | reject | post | reverse
GET|POST /accounts/expenses            CRUD /accounts/expense-categories   CRUD /accounts/recurring-expenses
CRUD   /accounts/vendors               GET|POST /accounts/vendor-bills    POST /accounts/vendor-payments
CRUD   /hr/employees                   CRUD /hr/salary-structures         CRUD /hr/session-pay-rates
GET|POST /hr/advances
GET|POST /payroll/runs                 POST /payroll/runs/{id}/calculate | approve | post | pay
GET    /payroll/runs/{id}/sheet        GET /payroll/payslips/{id}/pdf     GET /me/payslips
GET|POST /accounts/cash-closings       POST /accounts/cash-closings/{id}/receive
GET    /accounts/cash-closings/expected?date=
CRUD   /accounts/bank-reconciliations  POST /accounts/bank-reconciliations/{id}/import | match
CRUD   /accounts/fixed-assets          POST /accounts/fixed-assets/depreciate
CRUD   /accounts/budgets
GET    /accounts/reports/cash-book | bank-book | ledger | day-book | trial-balance
       | income-statement | balance-sheet | cash-flow | receipts-payments
       | service-income | branch-profitability | expense-analysis
       | receivables-aging | payables-aging | collection        (?format=pdf|xlsx)
```

---

## ১৭. Laravel-এ যা যোগ হবে

```
app/
├── Services/Accounting/
│   ├── LedgerService.php          # post(), reverse(), balance যাচাই — সব posting এর মধ্য দিয়ে যায়
│   ├── PostingRuleResolver.php    # কোন ঘটনায় কোন account
│   ├── Posters/InvoicePoster.php, PaymentPoster.php, PackageUsagePoster.php,
│   │          ExpensePoster.php, PayrollPoster.php, DepreciationPoster.php
│   ├── PeriodService.php          # period lock, year-end closing
│   ├── PayrollCalculator.php      # fixed + session + advance deduction
│   ├── CashClosingService.php
│   ├── BankReconciliationService.php
│   └── FinancialReportService.php # TB, P&L, BS, Cash Flow
├── Listeners/Accounting/          # InvoiceIssued, PaymentReceived, PackageSessionUsed → Poster
└── Models/Accounting/ ...
```
Billing-এর event (`InvoiceIssued`, `PaymentReceived` ইত্যাদি) থেকে listener journal post করবে। Billing code accounting-এর ভেতরের কিছু জানবে না। ফলে দুটো module আলাদা থাকবে, কিন্তু data সবসময় মিলবে।

**Test-এর বাধ্যবাধকতা:** প্রতিটি auto-posting নিয়মের জন্য feature test লিখতে হবে, যেখানে যাচাই হবে Σdebit = Σcredit এবং সঠিক account ব্যবহার হয়েছে। Trial Balance সবসময় মিলতে হবে, এটা CI-তে পরীক্ষা হবে।

---

## ১৮. Roadmap-এ পরিবর্তন

| ধাপ | কাজ | সময় |
|---|---|---|
| Billing sprint-এর সাথে | Chart of Accounts, journal table, LedgerService, **Billing auto-posting** | +১ সপ্তাহ |
| **Accounts A** | Expense, Voucher (approval সহ), Cash Closing, Petty Cash, Cash/Bank Book, Ledger, Trial Balance, P&L, Balance Sheet, Period Lock | ২ সপ্তাহ |
| **Accounts B** | Employee, Salary setup, Payroll, Therapist session payout, Advance, Payslip | ১.৫ সপ্তাহ |
| **Accounts C** | Vendor ও Payables, Bank Reconciliation, Fixed Assets ও Depreciation, Budget, Cash Flow, Year-end closing | ১.৫ সপ্তাহ |

মোট প্রায় **৬ সপ্তাহ** বাড়বে। সম্পূর্ণ project প্রায় ১৮ সপ্তাহ থেকে **২৪ সপ্তাহে** যাবে।

**MVP-তে যা থাকবে:** Billing auto-posting, Accounts A, এবং Accounts B (বেতন ছাড়া center চলে না)। Accounts C go-live-এর পরপরই আসবে।

---

## ১৯. চূড়ান্ত সিদ্ধান্ত ✅ (০৬ অক্টোবর ২০২৬ — সব সুপারিশ অনুমোদিত)

> A3: চারটি ধরনই থাকবে; default আপাতত "মাসিক নির্দিষ্ট", center-এর বর্তমান পদ্ধতি জানার পর প্রয়োজনে বদলানো হবে।

| # | প্রশ্ন | চূড়ান্ত সিদ্ধান্ত |
|---|---|---|
| A1 | Fiscal year কোনটা? | জুলাই–জুন (বাংলাদেশের আয়কর বছর) |
| A2 | Package-এর টাকা কখন আয় ধরা হবে? | Session হলে তবেই আয় (Unearned Revenue পদ্ধতি)। এতে মাসিক লাভ/ক্ষতি সঠিক হয়, আর package বাতিল হলে refund হিসাব সহজ হয়। |
| A3 | Therapist-রা কীভাবে বেতন পান: নির্দিষ্ট, per-session, নাকি মিশ্র? | System চারটি ধরনই সমর্থন করবে। আপনার center-এ এখন কোনটা চলে, সেটা জানালে default ঠিক করব। |
| A4 | সব staff (আয়া, পরিচ্ছন্নতাকর্মী, নিরাপত্তারক্ষী সহ) কি payroll-এ থাকবে? | হ্যাঁ, সবাই `employees`-এ থাকবে। Login শুধু যাদের দরকার তাদের থাকবে। |
| A5 | অনুমোদন ছাড়া কত টাকা পর্যন্ত খরচ post করা যাবে? | ৳৫,০০০ (settings থেকে বদলানো যাবে) |
| A6 | VAT/Tax (TDS) হিসাব লাগবে? | Setting থাকবে, default বন্ধ। আপনার accountant বা auditor-এর পরামর্শে চালু হবে। |
| A7 | Donation বা grant আসে? এলে দাতা অনুযায়ী আলাদা হিসাব লাগবে? | Donation income account থাকবে। দাতাভিত্তিক fund accounting পরে যোগ করা যাবে। |
| A8 | Staff attendance (হাজিরা) দিয়ে বেতন কাটা হবে? | MVP-তে হাতে কর্তন লেখা হবে। Staff attendance module পরে। |
| A9 | এখন হিসাব কোথায় রাখা হয় (Excel/Tally/খাতা)? | Go-live তারিখের opening balance নিয়ে শুরু করা হবে। পুরনো লেনদেন import করা হবে না, শুধু balance নেওয়া হবে। |
