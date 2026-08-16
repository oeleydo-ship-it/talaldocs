<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_index_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('documentation_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('page_title');
            $table->string('page_slug');
            $table->string('heading')->nullable();
            $table->text('content');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('indexed_at');
            $table->timestamps();

            $table->index(['project_id', 'documentation_version_id', 'language_id'], 'doc_index_scope');
            $table->index(['page_id']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('ai_indexed_at')->nullable()->after('github_edit_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('ai_indexed_at');
        });

        Schema::dropIfExists('doc_index_chunks');
    }
};
