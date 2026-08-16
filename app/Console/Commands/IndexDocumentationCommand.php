<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Scopes\WorkspaceScope;
use App\Services\DocsIndexService;
use Illuminate\Console\Command;

class IndexDocumentationCommand extends Command
{
    protected $signature = 'docs:index {project? : Project ID or subdomain to index} {--all : Index every project}';

    protected $description = 'Build the AI knowledge index from published documentation pages';

    public function handle(DocsIndexService $index): int
    {
        if ($this->option('all')) {
            $projects = Project::query()->withoutGlobalScope(WorkspaceScope::class)->get();
        } elseif ($this->argument('project') !== null) {
            $key = (string) $this->argument('project');
            $project = Project::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->when(
                    is_numeric($key),
                    fn ($query) => $query->whereKey((int) $key),
                    fn ($query) => $query->where(function ($inner) use ($key): void {
                        $inner->where('subdomain', $key)->orWhere('slug', $key);
                    }),
                )
                ->first();

            if (! $project instanceof Project) {
                $this->error('Project not found.');

                return self::FAILURE;
            }

            $projects = collect([$project]);
        } else {
            $this->error('Provide a project ID/subdomain or use --all.');

            return self::FAILURE;
        }

        foreach ($projects as $project) {
            $result = $index->indexProject($project);
            $this->info(sprintf(
                'Indexed %s: %d pages, %d chunks',
                $project->name,
                $result['pages'],
                $result['chunks'],
            ));
        }

        return self::SUCCESS;
    }
}
