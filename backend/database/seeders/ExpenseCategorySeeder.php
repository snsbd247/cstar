<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/** Everyday expense headings mapped to expense accounts (Accounts §৫). Reference data — safe in production. */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Electricity bill', 'বিদ্যুৎ বিল', '5410'],
            ['Water & gas bill', 'পানি ও গ্যাস বিল', '5420'],
            ['Internet & mobile', 'ইন্টারনেট ও মোবাইল', '5430'],
            ['Rent', 'ভাড়া', '5300'],
            ['Therapy & training materials', 'থেরাপি ও ট্রেনিং উপকরণ', '5500'],
            ['Office & stationery', 'অফিস ও স্টেশনারি', '5600'],
            ['Repair & maintenance', 'মেরামত', '5700'],
            ['Marketing & advertising', 'মার্কেটিং ও বিজ্ঞাপন', '5800'],
            ['Transport & conveyance', 'যাতায়াত', '5900'],
            ['Bank & bKash/Nagad charges', 'ব্যাংক ও বিকাশ/নগদ চার্জ', '5950'],
            ['Tea, snacks & guests', 'চা-নাস্তা ও আপ্যায়ন', '5990'],
            ['Cleaning & supplies', 'পরিষ্কার-পরিচ্ছন্নতা', '5990'],
            ['Other expense', 'অন্যান্য খরচ', '5990'],
        ];

        foreach ($categories as $i => [$name, $nameBn, $code]) {
            $accountId = Account::where('code', $code)->value('id');
            if ($accountId) {
                ExpenseCategory::firstOrCreate(['name' => $name], ['name_bn' => $nameBn, 'account_id' => $accountId, 'sort_order' => $i]);
            }
        }
    }
}
