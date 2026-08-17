<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->boolean('billing_enforced')->default(true)->after('stripe_webhook_secret');
            $table->unsignedSmallInteger('trial_days')->default(14)->after('billing_enforced');
            $table->boolean('trial_requires_card')->default(true)->after('trial_days');
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('subscription_status')->nullable()->after('stripe_id');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn(['billing_enforced', 'trial_days', 'trial_requires_card']);
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('subscription_status');
        });
    }
};
