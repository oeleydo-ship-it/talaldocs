<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'en', 'name' => 'English', 'is_default' => true],
            ['code' => 'es', 'name' => 'Spanish', 'is_default' => false],
            ['code' => 'fr', 'name' => 'French', 'is_default' => false],
            ['code' => 'de', 'name' => 'German', 'is_default' => false],
            ['code' => 'ja', 'name' => 'Japanese', 'is_default' => false],
        ] as $language) {
            Language::query()->updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
