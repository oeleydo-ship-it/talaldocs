<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('docs_template')->default('classic')->after('heading_font');
            $table->string('github_edit_url')->nullable()->after('docs_template');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['docs_template', 'github_edit_url']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('subtitle');
        });
    }
};
