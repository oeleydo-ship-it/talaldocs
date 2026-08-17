<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_domains', function (Blueprint $table) {
            $table->string('ssl_txt_name')->nullable()->after('ownership_txt_value');
            $table->string('ssl_txt_value')->nullable()->after('ssl_txt_name');
        });
    }

    public function down(): void
    {
        Schema::table('custom_domains', function (Blueprint $table) {
            $table->dropColumn(['ssl_txt_name', 'ssl_txt_value']);
        });
    }
};
