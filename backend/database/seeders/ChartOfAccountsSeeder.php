<?php

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Service;
use App\Services\AccountMap;
use Illuminate\Database\Seeder;

/**
 * Default chart of accounts (Accounts §২). Reference data — safe in production.
 * Rows with a system key are used by auto-posting and cannot be deleted.
 * Format: [code, name, name_bn, parent code, is_group, system_key, subtype]
 */
class ChartOfAccountsSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['1000', 'Assets', 'সম্পদ', null, true, null, null],
        ['1100', 'Cash & Cash Equivalents', 'নগদ ও সমতুল্য', '1000', true, null, null],
        ['1110', 'Cash in Hand', 'হাতে নগদ', '1100', true, 'cash_group', null],
        ['1120', 'Petty Cash', 'পেটি ক্যাশ', '1100', false, null, 'cash'],
        ['1130', 'Bank Accounts', 'ব্যাংক হিসাব', '1100', true, null, null],
        ['1131', 'Bank — Main Account', 'ব্যাংক — প্রধান হিসাব', '1130', false, 'bank_main', 'bank'],
        ['1140', 'Mobile Financial Services', 'মোবাইল ব্যাংকিং', '1100', true, null, null],
        ['1141', 'bKash Merchant', 'বিকাশ মার্চেন্ট', '1140', false, 'mfs_bkash', 'mfs'],
        ['1142', 'Nagad Merchant', 'নগদ মার্চেন্ট', '1140', false, 'mfs_nagad', 'mfs'],
        ['1150', 'Online Payments in Transit (SSLCommerz)', 'অনলাইন পেমেন্ট (ব্যাংকে আসার পথে)', '1100', false, 'online_clearing', 'bank'],
        ['1200', 'Accounts Receivable — Patients', 'রোগীর কাছে পাওনা', '1000', false, 'receivable', 'receivable'],
        ['1300', 'Staff Advances', 'কর্মীদের অগ্রিম', '1000', false, 'staff_advances', null],
        ['1400', 'Prepaid Expenses & Deposits', 'অগ্রিম খরচ ও জামানত', '1000', false, null, null],
        ['1500', 'Inventory — Therapy Materials', 'থেরাপি উপকরণ মজুদ', '1000', false, null, null],
        ['1600', 'Fixed Assets', 'স্থায়ী সম্পদ', '1000', true, null, null],
        ['1610', 'Furniture & Fixtures', 'আসবাবপত্র', '1600', false, null, 'fixed_asset'],
        ['1620', 'Therapy Equipment', 'থেরাপি সরঞ্জাম', '1600', false, null, 'fixed_asset'],
        ['1630', 'Computer & IT', 'কম্পিউটার ও আইটি', '1600', false, null, 'fixed_asset'],
        ['1690', 'Accumulated Depreciation', 'সঞ্চিত অবচয়', '1600', false, 'accumulated_depreciation', null],

        ['2000', 'Liabilities', 'দায়', null, true, null, null],
        ['2100', 'Accounts Payable — Vendors', 'সরবরাহকারীর কাছে দেনা', '2000', false, 'payable', 'payable'],
        ['2200', 'Patient Advances', 'রোগীর অগ্রিম জমা', '2000', false, 'patient_advances', null],
        ['2300', 'Unearned Package Revenue', 'অনার্জিত প্যাকেজ আয়', '2000', false, 'unearned_package', null],
        ['2400', 'Salary Payable', 'প্রদেয় বেতন', '2000', false, 'salary_payable', null],
        ['2500', 'Tax / VAT Payable', 'প্রদেয় কর/ভ্যাট', '2000', false, null, null],
        ['2600', 'Loans', 'ঋণ', '2000', false, null, null],

        ['3000', 'Equity', 'মূলধন', null, true, null, null],
        ['3100', "Owner's Capital", 'মালিকের মূলধন', '3000', false, null, null],
        ['3200', "Owner's Drawings", 'মালিকের উত্তোলন', '3000', false, null, null],
        ['3300', 'Retained Earnings', 'সংরক্ষিত আয়', '3000', false, 'retained_earnings', null],

        ['4000', 'Income', 'আয়', null, true, null, null],
        ['4100', 'Registration & Admission Fee', 'রেজিস্ট্রেশন ও ভর্তি ফি', '4000', false, 'income_admission', null],
        ['4200', 'Assessment Income', 'অ্যাসেসমেন্ট আয়', '4000', false, 'income_assessment', null],
        ['4300', 'Training Fee Income', 'ট্রেনিং ফি আয়', '4000', false, 'income_training', null],
        ['4400', 'Therapy Income', 'থেরাপি আয়', '4000', true, null, null],
        ['4490', 'Therapy Income — Other', 'থেরাপি আয় — অন্যান্য', '4400', false, 'income_therapy_other', null],
        ['4500', 'Parent Guidance / Consultation', 'অভিভাবক পরামর্শ', '4000', false, 'income_consultation', null],
        ['4800', 'Donation & Grant Income', 'অনুদান আয়', '4000', false, null, null],
        ['4900', 'Other Income', 'অন্যান্য আয়', '4000', false, 'income_other', null],
        ['4950', 'Discount Allowed', 'প্রদত্ত ছাড়', '4000', false, 'discount_allowed', null],

        ['5000', 'Expenses', 'খরচ', null, true, null, null],
        ['5100', 'Salary & Benefits', 'বেতন ও সুবিধা', '5000', true, null, null],
        ['5110', 'Salary — Therapists', 'বেতন — থেরাপিস্ট', '5100', false, null, null],
        ['5120', 'Salary — Trainers', 'বেতন — ট্রেইনার', '5100', false, null, null],
        ['5130', 'Salary — Admin & Reception', 'বেতন — প্রশাসন ও রিসেপশন', '5100', false, null, null],
        ['5140', 'Salary — Support Staff', 'বেতন — সহায়ক কর্মী', '5100', false, null, null],
        ['5150', 'Festival Bonus', 'উৎসব বোনাস', '5100', false, null, null],
        ['5200', 'Therapist Session Payout', 'থেরাপিস্ট সেশন পারিশ্রমিক', '5000', false, 'session_payout', null],
        ['5300', 'Rent', 'ভাড়া', '5000', false, null, null],
        ['5400', 'Utilities', 'ইউটিলিটি', '5000', true, null, null],
        ['5410', 'Electricity', 'বিদ্যুৎ', '5400', false, null, null],
        ['5420', 'Water & Gas', 'পানি ও গ্যাস', '5400', false, null, null],
        ['5430', 'Internet & Mobile', 'ইন্টারনেট ও মোবাইল', '5400', false, null, null],
        ['5500', 'Therapy & Training Materials', 'থেরাপি ও ট্রেনিং উপকরণ', '5000', false, null, null],
        ['5600', 'Office & Stationery', 'অফিস ও স্টেশনারি', '5000', false, null, null],
        ['5700', 'Repair & Maintenance', 'মেরামত ও রক্ষণাবেক্ষণ', '5000', false, null, null],
        ['5800', 'Marketing & Advertising', 'মার্কেটিং ও বিজ্ঞাপন', '5000', false, null, null],
        ['5900', 'Transport & Conveyance', 'যাতায়াত', '5000', false, null, null],
        ['5950', 'Bank & MFS Charges', 'ব্যাংক ও মোবাইল ব্যাংকিং চার্জ', '5000', false, 'bank_charges', null],
        ['5960', 'Depreciation Expense', 'অবচয় খরচ', '5000', false, 'depreciation', null],
        ['5970', 'Cash Short / Over', 'নগদ কম/বেশি', '5000', false, 'cash_short_over', null],
        ['5990', 'Miscellaneous Expense', 'বিবিধ খরচ', '5000', false, null, null],
    ];

    /** Therapy income sub-accounts per service (Accounts §২ "Service মাত্রা"). */
    private const THERAPY_INCOME = [
        'speech-language-therapy' => ['4410', 'Therapy Income — Speech & Language'],
        'occupational-therapy' => ['4420', 'Therapy Income — Occupational Therapy'],
        'aba-therapy' => ['4430', 'Therapy Income — ABA'],
        'oral-placement-therapy' => ['4440', 'Therapy Income — Oral Placement'],
        'special-education' => ['4450', 'Therapy Income — Special Education'],
    ];

    public function run(AccountMap $map): void
    {
        $types = ['1' => ['asset', 'debit'], '2' => ['liability', 'credit'], '3' => ['equity', 'credit'], '4' => ['income', 'credit'], '5' => ['expense', 'debit']];

        foreach (self::ACCOUNTS as [$code, $name, $nameBn, $parent, $isGroup, $key, $subtype]) {
            [$type, $normal] = $types[$code[0]];
            Account::firstOrCreate(['code' => $code], [
                'name' => $name, 'name_bn' => $nameBn, 'type' => $type, 'is_group' => $isGroup,
                // Contra accounts carry the opposite normal balance.
                'normal_balance' => in_array($code, ['4950', '1690', '3200'], true) ? ($normal === 'debit' ? 'credit' : 'debit') : $normal,
                'parent_id' => $parent ? Account::where('code', $parent)->value('id') : null,
                'system_key' => $key, 'subtype' => $subtype, 'is_system' => $key !== null,
            ]);
        }

        $parent = Account::where('code', '4400')->value('id');
        foreach (Service::where('category', ServiceCategory::Therapy)->get() as $service) {
            if (! isset(self::THERAPY_INCOME[$service->slug])) {
                continue;
            }
            [$code, $name] = self::THERAPY_INCOME[$service->slug];
            Account::firstOrCreate(['code' => $code], [
                'name' => $name, 'name_bn' => $service->name_bn ? "থেরাপি আয় — {$service->name_bn}" : null,
                'type' => 'income', 'normal_balance' => 'credit', 'parent_id' => $parent, 'service_id' => $service->id, 'is_system' => true,
            ]);
        }

        // One cash box per branch.
        Branch::all()->each(fn (Branch $branch) => $map->cashFor($branch->id));
    }
}
