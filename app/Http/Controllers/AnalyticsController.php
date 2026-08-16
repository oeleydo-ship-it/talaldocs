<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Models\Feedback;
use App\Models\Page;
use App\Models\PageView;
use App\Support\PlanGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(private PlanGate $plans) {}

    public function __invoke(Request $request): Response
    {
        $workspace = $this->workspace($request);
        abort_unless($this->plans->hasFeature($workspace, 'analytics'), 403);

        return Inertia::render('analytics/show', $this->payload($workspace->id));
    }

    public function export(Request $request): StreamedResponse
    {
        $workspace = $this->workspace($request);
        abort_unless($this->plans->hasFeature($workspace, 'analytics'), 403);
        $filename = 'docs-analytics-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($workspace): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'views']);

            foreach ($this->viewsByDay($workspace->id) as $row) {
                fputcsv($handle, [$row['date'], $row['views']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['page_id', 'page_title', 'views']);

            foreach ($this->topPages($workspace->id) as $row) {
                fputcsv($handle, [$row['page_id'], $row['title'], $row['views']]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $workspaceId): array
    {
        $views = PageView::query()
            ->where('workspace_id', $workspaceId)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->count();

        $feedback = Feedback::query()
            ->where('workspace_id', $workspaceId)
            ->latest('id')
            ->limit(25)
            ->get();

        return [
            'views30d' => $views,
            'viewsByDay' => $this->viewsByDay($workspaceId),
            'topPages' => $this->topPages($workspaceId),
            'helpful' => Feedback::query()->where('workspace_id', $workspaceId)->where('helpful', true)->count(),
            'notHelpful' => Feedback::query()->where('workspace_id', $workspaceId)->where('helpful', false)->count(),
            'feedback' => $feedback,
        ];
    }

    /**
     * @return list<array{date: string, views: int}>
     */
    private function viewsByDay(int $workspaceId): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', viewed_at)"
            : "to_char(viewed_at, 'YYYY-MM-DD')";

        $rows = DB::table('page_views')
            ->selectRaw("{$dateExpression} as day, count(*) as views")
            ->where('workspace_id', $workspaceId)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn (object $row): array => [
            'date' => (string) $row->day,
            'views' => (int) $row->views,
        ])->all();
    }

    /**
     * @return list<array{page_id: int, title: string, views: int}>
     */
    private function topPages(int $workspaceId): array
    {
        return collect(DB::table('page_views')
            ->select('page_id', DB::raw('count(*) as views'))
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('page_id')
            ->where('viewed_at', '>=', now()->subDays(30))
            ->groupBy('page_id')
            ->orderByDesc('views')
            ->limit(10)
            ->get())
            ->map(function (object $row): array {
                $page = Page::query()->find($row->page_id);

                return [
                    'page_id' => (int) $row->page_id,
                    'title' => $page?->title ?? 'Deleted page',
                    'views' => (int) $row->views,
                ];
            })
            ->all();
    }
}
