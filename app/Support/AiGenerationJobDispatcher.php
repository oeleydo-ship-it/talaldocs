<?php

namespace App\Support;

use App\Jobs\GenerateDocumentationFromWebsiteJob;

class AiGenerationJobDispatcher
{
    public static function dispatch(int $jobId): void
    {
        if (config('queue.default') === 'sync') {
            GenerateDocumentationFromWebsiteJob::dispatchSync($jobId);

            return;
        }

        GenerateDocumentationFromWebsiteJob::dispatch($jobId);
    }

    public static function requiresQueueWorker(): bool
    {
        return config('queue.default') !== 'sync';
    }
}
