<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Http\Controllers\Controller;
use App\Services\MarkdownImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarkdownImportController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(private MarkdownImporter $importer) {}

    public function store(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $data = $request->validate([
            'files' => ['required', 'array', 'max:'.MarkdownImporter::MAX_FILES],
            'files.*' => ['file', 'max:10240', function (string $attribute, mixed $value, callable $fail): void {
                $extension = $value instanceof UploadedFile
                    ? Str::lower((string) pathinfo((string) $value->getClientOriginalName(), PATHINFO_EXTENSION))
                    : '';

                if (! in_array($extension, [...MarkdownImporter::MARKDOWN_EXTENSIONS, 'zip'], true)) {
                    $fail('Only Markdown files (.md, .markdown, .mdx) or a .zip archive can be imported.');
                }
            }],
            'documentation_version_id' => [
                'required',
                'integer',
                Rule::exists('documentation_versions', 'id')->where('project_id', $model->id),
            ],
            'language_id' => [
                'required',
                'integer',
                Rule::exists('project_languages', 'language_id')->where('project_id', $model->id),
            ],
            'conflict' => ['sometimes', Rule::in(MarkdownImporter::CONFLICT_STRATEGIES)],
            'publish_immediately' => ['sometimes', 'boolean'],
        ]);

        $summary = $this->importer->import(
            $model,
            array_values($request->file('files', [])),
            (int) $data['documentation_version_id'],
            (int) $data['language_id'],
            (string) ($data['conflict'] ?? 'unique'),
            $request->boolean('publish_immediately'),
            $request->user(),
        );

        return back()->with('import', $summary);
    }
}
