<?php

namespace Database\Seeders;

use App\Models\Nationality;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NationalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nationalities = [
            ['name_en' => 'Afghan', 'name_fa' => 'افغان', 'name_ps' => 'افغان'],
            ['name_en' => 'Iranian', 'name_fa' => 'ایرانی', 'name_ps' => 'ایرانی'],
            ['name_en' => 'Pakistani', 'name_fa' => 'پاکستانی', 'name_ps' => 'پاکستانی'],
            ['name_en' => 'Indian', 'name_fa' => 'هندی', 'name_ps' => 'هندي'],
            ['name_en' => 'Turkish', 'name_fa' => 'ترکی', 'name_ps' => 'ترکی'],
            ['name_en' => 'Emirati', 'name_fa' => 'اماراتی', 'name_ps' => 'اماراتی'],
            ['name_en' => 'Saudi', 'name_fa' => 'سعودی', 'name_ps' => 'سعودي'],
            ['name_en' => 'Qatari', 'name_fa' => 'قطری', 'name_ps' => 'قطري'],
            ['name_en' => 'American', 'name_fa' => 'آمریکایی', 'name_ps' => 'امریکايي'],
            ['name_en' => 'British', 'name_fa' => 'انگلیسی', 'name_ps' => 'انګلیسي'],
            ['name_en' => 'German', 'name_fa' => 'آلمانی', 'name_ps' => 'الماني'],
            ['name_en' => 'French', 'name_fa' => 'فرانسوی', 'name_ps' => 'فرانسوی'],
            ['name_en' => 'Canadian', 'name_fa' => 'کانادایی', 'name_ps' => 'کانادایی'],
            ['name_en' => 'Chinese', 'name_fa' => 'چینی', 'name_ps' => 'چینایي'],
            ['name_en' => 'Russian', 'name_fa' => 'روسی', 'name_ps' => 'روسي'],
            ['name_en' => 'Uzbek', 'name_fa' => 'ازبک', 'name_ps' => 'ازبک'],
            ['name_en' => 'Tajik', 'name_fa' => 'تاجیک', 'name_ps' => 'تاجک'],
            ['name_en' => 'Kazakh', 'name_fa' => 'قزاق', 'name_ps' => 'قزاق'],
            ['name_en' => 'Bangladeshi', 'name_fa' => 'بنگله‌دیشی', 'name_ps' => 'بنګله‌دیشی'],
            ['name_en' => 'Other', 'name_fa' => 'دیگر', 'name_ps' => 'نور'],
        ];

        foreach ($nationalities as $nationality) {
            Nationality::create($nationality);
        }

    }
}
