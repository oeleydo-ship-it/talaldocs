<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_branding_fields(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $this->actingAs($owner)
            ->from(route('projects.settings', $project))
            ->post(route('projects.branding', $project), [
                'primary_color' => '#112233',
                'accent_color' => '#445566',
                'font_family' => 'Roboto',
                'heading_font' => 'Merriweather',
            ])
            ->assertRedirect(route('projects.settings', $project));

        $project->refresh();

        $this->assertSame('#112233', $project->primary_color);
        $this->assertSame('#445566', $project->accent_color);
        $this->assertSame('Roboto', $project->font_family);
        $this->assertSame('Merriweather', $project->heading_font);
    }

    public function test_owner_can_save_template_and_layout_fields(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $this->actingAs($owner)
            ->from(route('projects.settings', $project))
            ->post(route('projects.branding', $project), [
                'primary_color' => $project->primary_color,
                'accent_color' => $project->accent_color,
                'font_family' => $project->font_family,
                'heading_font' => $project->heading_font,
                'docs_template' => 'gitbook',
                'docs_layout' => 'wide',
                'github_edit_url' => 'https://github.com/org/repo/edit/main/docs/{slug}.md',
            ])
            ->assertRedirect(route('projects.settings', $project));

        $project->refresh();

        $this->assertSame('gitbook', $project->docs_template->value);
        $this->assertSame('wide', $project->docs_layout->value);
        $this->assertSame('https://github.com/org/repo/edit/main/docs/{slug}.md', $project->github_edit_url);
    }

    public function test_branding_uploads_store_files_and_replace_old_assets(): void
    {
        Storage::fake('public');

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('projects.branding', $project), [
                'primary_color' => '#112233',
                'accent_color' => '#445566',
                'font_family' => 'Inter',
                'heading_font' => 'Inter',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect();

        $project->refresh();
        $firstLogoPath = $project->logo_path;

        $this->assertNotNull($firstLogoPath);
        Storage::disk('public')->assertExists($firstLogoPath);

        $this->actingAs($owner)
            ->post(route('projects.branding', $project), [
                'primary_color' => '#112233',
                'accent_color' => '#445566',
                'font_family' => 'Inter',
                'heading_font' => 'Inter',
                'logo' => UploadedFile::fake()->image('logo-new.png'),
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertNotSame($firstLogoPath, $project->logo_path);
        Storage::disk('public')->assertMissing($firstLogoPath);
        Storage::disk('public')->assertExists((string) $project->logo_path);
    }

    public function test_settings_page_includes_branding_asset_urls(): void
    {
        Storage::fake('public');

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $project->forceFill([
            'logo_path' => 'branding/'.$project->id.'/logo.png',
            'favicon_path' => 'branding/'.$project->id.'/favicon.png',
            'og_image_path' => 'branding/'.$project->id.'/og.png',
        ])->save();

        Storage::disk('public')->put($project->logo_path, 'logo');
        Storage::disk('public')->put($project->favicon_path, 'favicon');
        Storage::disk('public')->put($project->og_image_path, 'og');

        $this->actingAs($owner)
            ->get(route('projects.settings', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/settings')
                ->where('project.logo_url', '/storage/'.$project->logo_path)
                ->where('project.favicon_url', '/storage/'.$project->favicon_path)
                ->where('project.og_image_url', '/storage/'.$project->og_image_path)
            );
    }

    public function test_public_docs_render_with_branding(): void
    {
        Storage::fake('public');

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $project->forceFill([
            'primary_color' => '#ff0000',
            'accent_color' => '#00ff00',
            'font_family' => 'Roboto',
            'heading_font' => 'Merriweather',
            'logo_path' => 'branding/'.$project->id.'/logo.png',
            'favicon_path' => 'branding/'.$project->id.'/favicon.png',
            'og_image_path' => 'branding/'.$project->id.'/og.png',
        ])->save();

        Storage::disk('public')->put($project->logo_path, 'logo');
        Storage::disk('public')->put($project->favicon_path, 'favicon');
        Storage::disk('public')->put($project->og_image_path, 'og');

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Branded page',
            'slug' => 'branded-page',
            'published_html' => '<p>Branded content</p>',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('docs/show')
                ->where('project.primary_color', '#ff0000')
                ->where('project.accent_color', '#00ff00')
                ->where('project.font_family', 'Roboto')
                ->where('project.heading_font', 'Merriweather')
                ->where('project.logo_url', '/storage/'.$project->logo_path)
                ->where('project.favicon_url', '/storage/'.$project->favicon_path)
                ->where('project.og_image_url', url('/storage/'.$project->og_image_path))
                ->has('canonicalUrl')
            );
    }
}
