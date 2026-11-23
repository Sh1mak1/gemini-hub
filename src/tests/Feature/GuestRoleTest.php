<?php

namespace Tests\Feature;

use App\Models\ContactGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_role_can_open_tourism_and_the_game_only(): void
    {
        $guest = User::factory()->create([
            'role' => User::ROLE_GUEST,
        ]);
        $game = ContactGame::query()->create([
            'user_id' => $guest->id,
            'status' => 'ongoing',
            'sentence' => '雨の駅で、終電を逃した猫が弁護士をしている。',
            'situation' => '雨の駅。',
            'character_name' => '猫の弁護士',
            'character_role' => '猫の弁護士',
            'channel' => 'スマートフォン',
            'clear_condition' => '駅を離れる。',
            'resource_name' => '始発までの時間',
            'resource_remaining' => 7,
            'resource_max' => 7,
            'resource_rule' => '連絡のたびに減る。',
            'hidden_state' => ['progress' => 0],
            'messages' => [],
        ]);

        $this->actingAs($guest)
            ->get(route('debug.tourism'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Debug/Tourism')
                ->where('recentSearches', [])
            );
        $this->actingAs($guest)
            ->get(route('debug.contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('llmProvider', null));
        $this->actingAs($guest)
            ->patchJson(route('debug.contact.provider'), ['provider' => 'openrouter'])
            ->assertForbidden();
        $this->actingAs($guest)->get(route('dashboard'))->assertRedirect(route('debug.contact'));

        $this->actingAs($guest)->get(route('profile.edit'))->assertRedirect(route('debug.contact'));
        $this->actingAs($guest)->patch(route('profile.update'), [
            'name' => '改名',
            'email' => 'changed@example.com',
        ])->assertRedirect(route('debug.contact'));
        $this->actingAs($guest)->get(route('tasks.index'))->assertRedirect(route('debug.contact'));
        $this->actingAs($guest)->get(route('debug.logs'))->assertRedirect(route('debug.contact'));
        $this->actingAs($guest)->get(route('debug.database'))->assertRedirect(route('debug.contact'));

        $this->actingAs($guest)->getJson(route('debug.contact.debug', $game))->assertForbidden();
        $this->actingAs($guest)
            ->patchJson(route('debug.contact.resource', $game), ['resource_remaining' => 3])
            ->assertForbidden();
        $this->actingAs($guest)->delete(route('profile.destroy'), [
            'password' => 'password',
        ])->assertRedirect(route('debug.contact'));
    }

    public function test_guest_login_reaches_the_game_without_the_task_list(): void
    {
        $guest = User::factory()->create([
            'email' => 'guest@example.com',
            'role' => User::ROLE_GUEST,
        ]);

        $this->post('/login', [
            'email' => $guest->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard'))->assertRedirect(route('debug.contact'));
    }

    public function test_owner_still_opens_tasks(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('tasks.index'))->assertOk();
    }
}
