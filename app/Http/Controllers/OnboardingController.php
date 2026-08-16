<?php

namespace App\Http\Controllers;

use App\Actions\CompleteOnboarding;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Services\AiDocumentationGenerator;
use App\Support\Subdomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('onboarding/show', [
            'name' => $user?->name ?? '',
            'companyName' => $user?->company_name,
            'appDomain' => config('anytdocs.domain'),
            'aiConfigured' => AiDocumentationGenerator::isConfigured(),
        ]);
    }

    public function store(CompleteOnboardingRequest $request, CompleteOnboarding $completeOnboarding): RedirectResponse
    {
        $completeOnboarding->handle($request->user(), $request->validated());

        app()->instance('current.workspace_id', $request->user()->fresh()?->current_workspace_id);

        return redirect()->route('dashboard');
    }

    public function subdomainAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'subdomain' => ['required', 'string', 'max:63'],
        ]);

        return response()->json(Subdomain::availability($request->string('subdomain')->toString()));
    }
}
