<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CriticalPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_onboard_create_page_publish_and_public_docs(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Taylor Docs',
            'email' => 'taylor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('verification.notice', absolute: false));

        $user = User::query()->where('email', 'taylor@example.com')->firstOrFail();
        $user->markEmailAsVerified();

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'name' => 'Taylor Docs',
                'company_name' => 'Docs Co',
                'workspace_name' => 'Docs Co',
                'subdomain' => 'docs-co',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $project = Project::query()->withoutGlobalScopes()->where('subdomain', 'docs-co')->firstOrFail();

        $versionId = $project->versions()->value('id');
        $languageId = $project->projectLanguages()->value('language_id');

        $this->actingAs($user)
            ->post(route('pages.store', $project->id), [
                'title' => 'Quick start',
                'documentation_version_id' => $versionId,
                'language_id' => $languageId,
            ])
            ->assertRedirect();

        $page = Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('slug', 'quick-start')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('pages.update', [$project->id, $page->id]), [
                'title' => 'Quick start',
                'slug' => 'quick-start',
                'markdown' => "# Quick start\n\nShip docs in minutes.",
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('pages.publish', [$project->id, $page->id]))
            ->assertRedirect();

        $page->refresh();
        $this->assertSame(PageStatus::Published, $page->status);

        $this->get($project->docsBasePath().'/latest/en/quick-start')
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('docs/show')
                ->where('page.title', 'Quick start')
            )
            ->assertSee('Ship docs in minutes');
    }
}
