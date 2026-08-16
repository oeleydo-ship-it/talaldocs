<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('custom_domains')
            ->where('status', 'verified')
            ->update(['status' => 'active']);

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 12);
            $table->string('key_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'key_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');

        DB::table('custom_domains')
            ->where('status', 'active')
            ->update(['status' => 'verified']);
    }
};
