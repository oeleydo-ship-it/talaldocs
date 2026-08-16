<?php

namespace App\Jobs;

use App\Enums\AiGenerationJobStatus;
use App\Models\AiGenerationJob;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\ProjectLanguage;
use App\Models\Scopes\WorkspaceScope;
use App\Models\User;
use App\Services\AiDocumentationGenerator;
use App\Services\WebsiteAnalyzer;
use App\Support\Audit;
use App\Support\Markdown;
use App\Support\PagePublisher;
use App\Support\PlatformAiConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateDocumentationFromWebsiteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $aiGenerationJobId) {}

    public function handle(
        WebsiteAnalyzer $websiteAnalyzer,
        AiDocumentationGenerator $generator,
        Markdown $markdown,
        PagePublisher $publisher,
        Audit $audit,
    ): void {
        $job = AiGenerationJob::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->with('project')
            ->find($this->aiGenerationJobId);

        if ($job === null) {
            return;
        }

        PlatformAiConfig::apply();

        $job->forceFill(['status' => AiGenerationJobStatus::Processing])->save();

        try {
            $website = $websiteAnalyzer->analyze($job->source_url);
            $generated = $generator->generate($website, $job->product_description);
            $createdPages = $this->createPages($job, $generated['pages'], $markdown, $publisher);

            $user = $job->user_id ? User::query()->find($job->user_id) : null;

            foreach ($createdPages as $page) {
                $audit->record($job->workspace_id, 'page.ai_generated', $user, $page, [
                    'source_url' => $job->source_url,
                    'job_id' => $job->id,
                ]);
            }

            $job->forceFill([
                'status' => AiGenerationJobStatus::Completed,
                'result_summary' => [
                    'project_summary' => $generated['project_summary'],
                    'pages_created' => count($createdPages),
                    'page_ids' => collect($createdPages)->pluck('id')->all(),
                    'pages' => collect($createdPages)->map(fn (Page $page): array => [
                        'id' => $page->id,
                        'title' => $page->title,
                        'slug' => $page->slug,
                    ])->all(),
                ],
                'error' => null,
            ])->save();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Could not validate website content for documentation generation.';

            $job->forceFill([
                'status' => AiGenerationJobStatus::Failed,
                'error' => Str::limit($message, 1000),
            ])->save();
        } catch (Throwable $exception) {
            $job->forceFill([
                'status' => AiGenerationJobStatus::Failed,
                'error' => Str::limit($exception->getMessage(), 1000),
            ])->save();
        }
    }

    /**
     * @param  list<array{title: string, slug: string, markdown: string}>  $pages
     * @return list<Page>
     */
    private function createPages(
        AiGenerationJob $job,
        array $pages,
        Markdown $markdown,
        PagePublisher $publisher,
    ): array {
        $project = $job->project;

        if ($project === null) {
            return [];
        }

        $version = DocumentationVersion::query()
            ->where('project_id', $project->id)
            ->orderByDesc('is_default')
            ->first();

        $language = ProjectLanguage::query()
            ->where('project_id', $project->id)
            ->orderByDesc('is_default')
            ->first();

        if ($language === null) {
            $english = Language::query()->firstOrCreate(
                ['code' => 'en'],
                ['name' => 'English', 'is_default' => true],
            );

            $language = ProjectLanguage::query()->create([
                'workspace_id' => $job->workspace_id,
                'project_id' => $project->id,
                'language_id' => $english->id,
                'is_default' => true,
            ]);
        }

        if ($version === null || $language === null) {
            throw new \RuntimeException('Project is missing a default version or language.');
        }

        $position = (int) Page::query()
            ->where('project_id', $project->id)
            ->where('documentation_version_id', $version->id)
            ->where('language_id', $language->language_id)
            ->max('position');

        $created = [];

        foreach ($pages as $pageData) {
            $position++;
            $slug = $this->uniqueSlug(
                $pageData['slug'],
                $version->id,
                $language->language_id,
            );

            $html = $markdown->render($pageData['markdown'], $job->workspace_id);

            $page = Page::query()->create([
                'workspace_id' => $job->workspace_id,
                'project_id' => $project->id,
                'documentation_version_id' => $version->id,
                'language_id' => $language->language_id,
                'title' => $pageData['title'],
                'slug' => $slug,
                'markdown' => $pageData['markdown'],
                'html' => $html,
                'position' => $position,
            ]);

            if ($job->publish_immediately) {
                $publisher->publish($page);
            }

            $created[] = $page->refresh();
        }

        return $created;
    }

    private function uniqueSlug(string $slug, int $versionId, int $languageId): string
    {
        $base = Str::slug($slug) ?: 'page';
        $candidate = $base;
        $i = 1;

        while (Page::query()
            ->where('documentation_version_id', $versionId)
            ->where('language_id', $languageId)
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
