<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('cloudflare_enabled')->default(false)->after('stripe_webhook_secret');
            $table->text('cloudflare_api_token')->nullable()->after('cloudflare_enabled');
            $table->string('cloudflare_zone_id')->nullable()->after('cloudflare_api_token');
            $table->string('cloudflare_account_id')->nullable()->after('cloudflare_zone_id');
            $table->string('cloudflare_fallback_origin')->nullable()->after('cloudflare_account_id');
            $table->boolean('cloudflare_auto_subdomains')->default(false)->after('cloudflare_fallback_origin');
        });

        Schema::table('custom_domains', function (Blueprint $table) {
            $table->string('cloudflare_hostname_id')->nullable()->after('error_message');
            $table->string('ssl_status')->nullable()->after('cloudflare_hostname_id');
            $table->string('ownership_txt_name')->nullable()->after('ssl_status');
            $table->string('ownership_txt_value')->nullable()->after('ownership_txt_name');
            $table->string('cloudflare_dns_record_id')->nullable()->after('ownership_txt_value');
        });
    }

    public function down(): void
    {
        Schema::table('custom_domains', function (Blueprint $table) {
            $table->dropColumn([
                'cloudflare_hostname_id',
                'ssl_status',
                'ownership_txt_name',
                'ownership_txt_value',
                'cloudflare_dns_record_id',
            ]);
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cloudflare_enabled',
                'cloudflare_api_token',
                'cloudflare_zone_id',
                'cloudflare_account_id',
                'cloudflare_fallback_origin',
                'cloudflare_auto_subdomains',
            ]);
        });
    }
};
