<?php

use App\Jobs\VerifyCustomDomainJob;
use App\Models\CustomDomain;
use App\Models\Scopes\WorkspaceScope;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('domains:verify', function () {
    CustomDomain::query()
        ->withoutGlobalScope(WorkspaceScope::class)
        ->whereIn('status', ['pending', 'verifying', 'failed'])
        ->each(fn (CustomDomain $domain) => VerifyCustomDomainJob::dispatch($domain->id));

    $this->info('Queued domain verification jobs.');
})->purpose('Re-check pending custom domains');

Schedule::command('domains:verify')->hourly();
