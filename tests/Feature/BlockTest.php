<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_block(): void
    {
        $owner = User::factory()->onboarded()->create();

        $this->actingAs($owner)
            ->post(route('blocks.store'), [
                'name' => 'Callout',
                'markdown' => "## Note\n\nThis is reusable content.",
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blocks', [
            'workspace_id' => $owner->current_workspace_id,
            'name' => 'Callout',
        ]);
    }

    public function test_block_create_rejects_empty_markdown(): void
    {
        $owner = User::factory()->onboarded()->create();

        $this->actingAs($owner)
            ->post(route('blocks.store'), [
                'name' => 'Empty block',
                'markdown' => '',
            ])
            ->assertSessionHasErrors('markdown');
    }

    public function test_member_of_another_workspace_cannot_update_a_block(): void
    {
        $ownerA = User::factory()->onboarded()->create();
        $block = Block::factory()->create([
            'workspace_id' => $ownerA->current_workspace_id,
        ]);

        $ownerB = User::factory()->onboarded()->create();

        $this->actingAs($ownerB)
            ->patch(route('blocks.update', $block), [
                'name' => 'Hacked',
                'markdown' => 'Should not work',
            ])
            ->assertNotFound();
    }
}
