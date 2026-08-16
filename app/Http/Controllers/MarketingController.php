<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\Plan;
use App\Models\Project;
use App\Support\PlatformConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MarketingController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('marketing/home', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('price_cents')->get(['id', 'name', 'slug', 'price_cents']),
        ]);
    }

    public function features(): Response
    {
        return Inertia::render('marketing/features');
    }

    public function pricing(): Response
    {
        return Inertia::render('marketing/pricing', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('price_cents')->get(),
        ]);
    }

    public function examples(): Response
    {
        $demos = Project::query()
            ->withoutGlobalScopes()
            ->whereHas('pages', fn ($query) => $query->whereNotNull('published_at'))
            ->latest('id')
            ->limit(6)
            ->get(['id', 'name', 'subdomain', 'docs_template'])
            ->map(fn (Project $project): array => [
                'name' => $project->name,
                'subdomain' => $project->subdomain,
                'layout' => $project->docs_template?->value ?? 'classic',
                'url' => $project->docsBasePath(),
            ]);

        return Inertia::render('marketing/examples', [
            'demos' => $demos,
        ]);
    }

    public function faq(): Response
    {
        return Inertia::render('marketing/faq');
    }

    public function privacy(): Response
    {
        return Inertia::render('marketing/privacy');
    }

    public function terms(): Response
    {
        return Inertia::render('marketing/terms');
    }

    public function contact(): Response
    {
        return Inertia::render('marketing/contact', [
            'supportEmail' => PlatformConfig::supportEmail(),
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = PlatformConfig::supportEmail() ?? config('mail.from.address');

        if (filled($recipient)) {
            try {
                Mail::to($recipient)->send(new ContactMessageMail($data));
            } catch (Throwable $exception) {
                Log::warning('Contact form mail failed', [
                    'error' => $exception->getMessage(),
                    'email' => $data['email'],
                ]);

                return back()
                    ->withInput()
                    ->withErrors(['message' => 'We could not send your message right now. Please try again later or email us directly.']);
            }
        } else {
            Log::info('Contact form submission (mail not configured)', $data);
        }

        return back()->with('status', 'Thanks for reaching out. We will reply within one business day.');
    }
}
