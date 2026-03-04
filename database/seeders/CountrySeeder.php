<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $countries = [
            ['code' => 'SA', 'name' => 'السعودية', 'flag_emoji' => '🇸🇦', 'dial_code' => '966'],
            ['code' => 'EG', 'name' => 'مصر', 'flag_emoji' => '🇪🇬', 'dial_code' => '20'],
            ['code' => 'AE', 'name' => 'الإمارات', 'flag_emoji' => '🇦🇪', 'dial_code' => '971'],
            ['code' => 'JO', 'name' => 'الأردن', 'flag_emoji' => '🇯🇴', 'dial_code' => '962'],
            ['code' => 'KW', 'name' => 'الكويت', 'flag_emoji' => '🇰🇼', 'dial_code' => '965'],
            ['code' => 'BH', 'name' => 'البحرين', 'flag_emoji' => '🇧🇭', 'dial_code' => '973'],
            ['code' => 'QA', 'name' => 'قطر', 'flag_emoji' => '🇶🇦', 'dial_code' => '974'],
            ['code' => 'OM', 'name' => 'عُمان', 'flag_emoji' => '🇴🇲', 'dial_code' => '968'],
            ['code' => 'IQ', 'name' => 'العراق', 'flag_emoji' => '🇮🇶', 'dial_code' => '964'],
            ['code' => 'LB', 'name' => 'لبنان', 'flag_emoji' => '🇱🇧', 'dial_code' => '961'],
            ['code' => 'SY', 'name' => 'سوريا', 'flag_emoji' => '🇸🇾', 'dial_code' => '963'],
            ['code' => 'YE', 'name' => 'اليمن', 'flag_emoji' => '🇾🇪', 'dial_code' => '967'],
            ['code' => 'PS', 'name' => 'فلسطين', 'flag_emoji' => '🇵🇸', 'dial_code' => '970'],
            ['code' => 'MA', 'name' => 'المغرب', 'flag_emoji' => '🇲🇦', 'dial_code' => '212'],
            ['code' => 'DZ', 'name' => 'الجزائر', 'flag_emoji' => '🇩🇿', 'dial_code' => '213'],
            ['code' => 'TN', 'name' => 'تونس', 'flag_emoji' => '🇹🇳', 'dial_code' => '216'],
            ['code' => 'LY', 'name' => 'ليبيا', 'flag_emoji' => '🇱🇾', 'dial_code' => '218'],
            ['code' => 'SD', 'name' => 'السودان', 'flag_emoji' => '🇸🇩', 'dial_code' => '249'],
            ['code' => 'TR', 'name' => 'تركيا', 'flag_emoji' => '🇹🇷', 'dial_code' => '90'],
            ['code' => 'US', 'name' => 'الولايات المتحدة', 'flag_emoji' => '🇺🇸', 'dial_code' => '1'],
            ['code' => 'GB', 'name' => 'بريطانيا', 'flag_emoji' => '🇬🇧', 'dial_code' => '44'],
            ['code' => 'IN', 'name' => 'الهند', 'flag_emoji' => '🇮🇳', 'dial_code' => '91'],
            ['code' => 'PK', 'name' => 'باكستان', 'flag_emoji' => '🇵🇰', 'dial_code' => '92'],
            ['code' => 'BD', 'name' => 'بنغلاديش', 'flag_emoji' => '🇧🇩', 'dial_code' => '880'],
            ['code' => 'DE', 'name' => 'ألمانيا', 'flag_emoji' => '🇩🇪', 'dial_code' => '49'],
            ['code' => 'FR', 'name' => 'فرنسا', 'flag_emoji' => '🇫🇷', 'dial_code' => '33'],
            ['code' => 'CA', 'name' => 'كندا', 'flag_emoji' => '🇨🇦', 'dial_code' => '1'],
            ['code' => 'AU', 'name' => 'أستراليا', 'flag_emoji' => '🇦🇺', 'dial_code' => '61'],
            ['code' => 'MY', 'name' => 'ماليزيا', 'flag_emoji' => '🇲🇾', 'dial_code' => '60'],
            ['code' => 'ID', 'name' => 'إندونيسيا', 'flag_emoji' => '🇮🇩', 'dial_code' => '62'],
        ];

        foreach ($countries as $c) {
            $data = [
                'name' => $c['name'],
                'flag_emoji' => $c['flag_emoji'],
                'dial_code' => $c['dial_code'],
                'updated_at' => $now,
            ];
            $exists = DB::table('countries')->where('code', $c['code'])->exists();
            if ($exists) {
                DB::table('countries')->where('code', $c['code'])->update($data);
            } else {
                DB::table('countries')->insert(array_merge($data, [
                    'code' => $c['code'],
                    'created_at' => $now,
                ]));
            }
        }
    }
}
