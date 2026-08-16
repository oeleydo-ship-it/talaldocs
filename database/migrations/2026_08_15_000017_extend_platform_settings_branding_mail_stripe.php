<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->string('app_name')->default('Docs')->after('id');
            $table->string('logo_path')->nullable()->after('app_name');
            $table->string('favicon_path')->nullable()->after('logo_path');
            $table->string('support_email')->nullable()->after('favicon_path');
            $table->string('tagline')->nullable()->after('support_email');

            $table->string('mail_mailer')->default('smtp')->after('ai_model');
            $table->string('mail_host')->nullable()->after('mail_mailer');
            $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username')->nullable()->after('mail_port');
            $table->text('mail_password')->nullable()->after('mail_username');
            $table->string('mail_encryption')->nullable()->after('mail_password');
            $table->string('mail_from_address')->nullable()->after('mail_encryption');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');

            $table->boolean('stripe_enabled')->default(false)->after('mail_from_name');
            $table->string('stripe_key')->nullable()->after('stripe_enabled');
            $table->text('stripe_secret')->nullable()->after('stripe_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'app_name',
                'logo_path',
                'favicon_path',
                'support_email',
                'tagline',
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
                'stripe_enabled',
                'stripe_key',
                'stripe_secret',
                'stripe_webhook_secret',
            ]);
        });
    }
};
