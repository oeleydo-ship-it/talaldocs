<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->string('app_domain')->nullable()->after('tagline');
        });

        if (Schema::hasColumn('platform_settings', 'app_name')) {
            DB::table('platform_settings')
                ->where('app_name', 'Anytdocs')
                ->update(['app_name' => 'Docs']);
        }
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn('app_domain');
        });
    }
};
