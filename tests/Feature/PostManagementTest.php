<?php

namespace Tests\Feature;

use App\Enums\DocsTemplate;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_publish_announcement(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('projects.posts.store', $project), [
                'type' => PostType::Announcement->value,
                'title' => 'Launch day',
                'slug' => 'launch-day',
                'excerpt' => 'We are live.',
                'markdown' => "## Hello\n\nWelcome to the product.",
                'publish' => true,
            ])
            ->assertRedirect(route('projects.posts.index', $project));

        $post = Post::query()->withoutGlobalScopes()->where('project_id', $project->id)->first();

        $this->assertNotNull($post);
        $this->assertSame(PostType::Announcement, $post->type);
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame('launch-day', $post->slug);
        $this->assertNotNull($post->published_at);
        $this->assertStringContainsString('Welcome to the product', (string) $post->html);
    }

    public function test_owner_can_unpublish_and_delete_post(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $post = Post::factory()->announcement()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'slug' => 'notes',
        ]);

        $this->actingAs($owner)
            ->post(route('projects.posts.publish', [$project, $post]), [
                'publish' => false,
            ])
            ->assertRedirect();

        $post->refresh();
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->published_at);

        $this->actingAs($owner)
            ->delete(route('projects.posts.destroy', [$project, $post]))
            ->assertRedirect(route('projects.posts.index', $project));

        $this->assertSoftDeleted($post);
    }

    public function test_public_directory_announcements_and_changelog_routes(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Getting started',
            'slug' => 'getting-started',
        ]);

        $announcement = Post::factory()->announcement()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Welcome announcement',
            'slug' => 'welcome-announcement',
            'excerpt' => 'Big news',
        ]);

        $changelog = Post::factory()->changelog()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'v1.0 release',
            'slug' => 'v1-0-release',
        ]);

        Post::factory()->announcement()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Draft only',
            'slug' => 'draft-only',
        ]);

        $this->get($project->docsBasePath().'/directory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/directory')
                ->has('sections', 3)
                ->where('template', 'classic')
                ->where('directoryUrl', $project->docsBasePath().'/directory')
                ->where('announcementsUrl', $project->docsBasePath().'/announcements')
                ->where('changelogUrl', $project->docsBasePath().'/changelog')
            );

        $this->get($project->docsBasePath().'/announcements')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/announcements/index')
                ->has('posts', 1)
                ->where('posts.0.slug', $announcement->slug)
            );

        $this->get($project->docsBasePath().'/announcements/'.$announcement->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/announcements/show')
                ->where('post.title', 'Welcome announcement')
            );

        $this->get($project->docsBasePath().'/changelog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/changelog/index')
                ->has('posts', 1)
                ->where('posts.0.slug', $changelog->slug)
            );

        $this->get($project->docsBasePath().'/changelog/'.$changelog->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/changelog/show')
                ->where('post.title', 'v1.0 release')
            );

        $this->get($project->docsBasePath().'/announcements/draft-only')->assertNotFound();
    }

    public function test_docs_show_includes_footer_urls(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'slug' => 'home',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('template', 'classic')
                ->where('directoryUrl', $project->docsBasePath().'/directory')
                ->where('announcementsUrl', $project->docsBasePath().'/announcements')
                ->where('changelogUrl', $project->docsBasePath().'/changelog')
                ->has('docsHomeUrl')
            );
    }

    public function test_gitbook_hub_pages_receive_template_and_footer_urls(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $project->forceFill(['docs_template' => DocsTemplate::Gitbook])->save();

        $this->get($project->docsBasePath().'/directory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/directory')
                ->where('template', 'gitbook')
                ->where('directoryUrl', $project->docsBasePath().'/directory')
                ->where('announcementsUrl', $project->docsBasePath().'/announcements')
                ->where('changelogUrl', $project->docsBasePath().'/changelog')
                ->has('docsHomeUrl')
            );

        $this->get($project->docsBasePath().'/announcements')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/announcements/index')
                ->where('template', 'gitbook')
            );
    }
}
