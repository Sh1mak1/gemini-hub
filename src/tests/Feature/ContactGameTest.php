<?php

namespace Tests\Feature;

use App\Exceptions\GeminiApiException;
use App\Models\ContactGame;
use App\Models\User;
use App\Services\Gemini\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContactGameTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_guest_cannot_use_contact_game(): void
    {
        $this->get(route('debug.contact'))->assertRedirect(route('login'));
        $this->postJson(route('debug.contact.start'), [
            'sentence' => '雨の駅で、終電を逃した猫が弁護士をしている。',
        ])->assertRedirect(route('login'));

        $owner = User::factory()->create();
        $game = $this->ongoingGame($owner, resourceRemaining: 4, progress: 10);
        $this->patchJson(route('debug.contact.resource', $game), [
            'resource_remaining' => 3,
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_contact_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('debug.contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Debug/Contact')
                ->where('game', null)
            );
    }

    public function test_start_returns_public_state_and_keeps_hidden_state_on_the_server(): void
    {
        $user = User::factory()->create();
        $this->mockGemini($this->startPayload());

        $response = $this->actingAs($user)
            ->postJson(route('debug.contact.start'), [
                'sentence' => '漂流した宇宙飛行士が目を覚ますと、ここは異星だった。',
            ]);

        $response->assertOk()
            ->assertJsonPath('game.status', 'ongoing')
            ->assertJsonPath('game.character_role', '漂流した宇宙飛行士')
            ->assertJsonPath('game.character_name', '漂流した宇宙飛行士')
            ->assertJsonPath('game.resource_remaining', 8)
            ->assertJsonPath('game.messages.0.role', 'character')
            ->assertJsonPath('error', null);

        $this->assertStringNotContainsString('酸素はまだ半分ある', $response->getContent());

        $game = ContactGame::query()->first();
        $this->assertNotNull($game);
        $this->assertSame('酸素はまだ半分ある', $game->hidden_state['misconception']);
        $this->assertSame(0, $game->hidden_state['progress']);
        $this->assertSame($user->id, $game->user_id);
    }

    public function test_message_spends_resource_and_caps_progress(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 8, progress: 0);

        $this->mockGemini([
            'reply' => '了解した。尾根の影を使う。',
            'resource_remaining' => 0,
            'change_note' => '直射を避けて歩き始めた。',
            'status' => 'cleared',
            'loss_reason' => '',
            'hidden_progress' => 100,
            'hidden_misconception' => '',
            'hidden_intervention_1' => '影を伝う',
            'hidden_intervention_2' => '',
            'hidden_intervention_3' => '',
            'hidden_doom' => '日が昇りきると動けなくなる。',
        ]);

        $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => '日向は歩くな。岩の影を伝って尾根へ行け。',
            ])
            ->assertOk()
            ->assertJsonPath('game.status', 'ongoing')
            ->assertJsonPath('game.resource_remaining', 5)
            ->assertJsonPath('game.last_change_note', '直射を避けて歩き始めた。')
            ->assertJsonPath('game.messages.1.role', 'player')
            ->assertJsonPath('game.messages.2.text', '了解した。尾根の影を使う。');

        $game->refresh();
        $this->assertSame(25, $game->hidden_state['progress']);
        $this->assertSame('ongoing', $game->status);
    }

    public function test_clear_requires_real_progress_and_can_happen_on_the_last_point_of_resource(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 1, progress: 80);

        $this->mockGemini([
            'reply' => '航路が確定した。これで帰れる。',
            'resource_remaining' => 0,
            'change_note' => '帰還手順が揃った。',
            'status' => 'cleared',
            'loss_reason' => '',
            'ending' => '航路が決まり、宇宙飛行士は母船の灯に向かって歩き出した。',
            'hidden_progress' => 100,
            'hidden_misconception' => '',
            'hidden_intervention_1' => '',
            'hidden_intervention_2' => '',
            'hidden_intervention_3' => '',
            'hidden_doom' => '',
        ]);

        $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => 'その周波数で母船を呼べ。',
            ])
            ->assertOk()
            ->assertJsonPath('game.status', 'cleared')
            ->assertJsonPath('game.resource_remaining', 0)
            ->assertJsonPath('game.loss_reason', null)
            ->assertJsonPath('game.ending', '航路が決まり、宇宙飛行士は母船の灯に向かって歩き出した。');

        $this->assertSame(100, $game->fresh()->hidden_state['progress']);
    }

    public function test_meta_change_note_is_rewritten_into_the_scene(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 6, progress: 10);

        $this->mockGemini([
            'reply' => 'それは今はできない。',
            'resource_remaining' => 5,
            'change_note' => 'プレイヤーはキャラクター名を間違えた。',
            'status' => 'ongoing',
            'loss_reason' => '',
            'hidden_progress' => 10,
            'hidden_misconception' => 'まだ大丈夫だと思っている。',
            'hidden_intervention_1' => '電源を切れ',
            'hidden_intervention_2' => '',
            'hidden_intervention_3' => '',
            'hidden_doom' => '酸欠で倒れる。',
        ]);

        $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => '名前を呼ぶ。',
            ])
            ->assertOk()
            ->assertJsonPath('game.last_change_note', '状況は進んだ。');
    }

    public function test_running_out_of_resource_ends_the_game(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 1, progress: 20);

        $this->mockGemini([
            'reply' => 'まだ大丈夫だ。',
            'resource_remaining' => 1,
            'change_note' => '酸素が尽きた。',
            'status' => 'ongoing',
            'loss_reason' => '',
            'hidden_progress' => 20,
            'hidden_misconception' => 'まだ大丈夫だと思っている。',
            'hidden_intervention_1' => '電源を切れ',
            'hidden_intervention_2' => '',
            'hidden_intervention_3' => '',
            'hidden_doom' => '酸欠で倒れる。',
        ]);

        $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => 'そのまま走れ。',
            ])
            ->assertOk()
            ->assertJsonPath('game.status', 'lost')
            ->assertJsonPath('game.resource_remaining', 0)
            ->assertJsonPath('game.loss_reason', 'もう手の打ちようがなくなった。');
    }

    public function test_finished_game_rejects_another_message(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 0, progress: 100);
        $game->update(['status' => 'cleared']);

        $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => 'まだいるか。',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'この連絡はもう終わっています。');

        $this->assertCount(1, $game->fresh()->messages);
    }

    public function test_user_cannot_message_another_users_game(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $game = $this->ongoingGame($owner, resourceRemaining: 8, progress: 0);

        $this->actingAs($intruder)
            ->postJson(route('debug.contact.message', $game), [
                'message' => '応答せよ。',
            ])
            ->assertNotFound();
    }

    public function test_owner_can_reset_the_current_signal(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $older = $this->ongoingGame($owner, resourceRemaining: 4, progress: 0);
        $game = $this->ongoingGame($owner, resourceRemaining: 6, progress: 10);

        $this->actingAs($intruder)
            ->deleteJson(route('debug.contact.reset', $game))
            ->assertNotFound();
        $this->assertNotNull($game->fresh());

        $this->actingAs($owner)
            ->deleteJson(route('debug.contact.reset', $game))
            ->assertOk()
            ->assertJsonPath('game', null);

        $this->assertNull($game->fresh());
        $this->assertNotNull($older->fresh());

        $this->actingAs($owner)
            ->get(route('debug.contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Debug/Contact')
                ->where('game.id', $older->id)
            );
    }

    public function test_debug_endpoint_shows_progress_only_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $game = $this->ongoingGame($owner, resourceRemaining: 6, progress: 40);

        $this->actingAs($owner)
            ->getJson(route('debug.contact.debug', $game))
            ->assertOk()
            ->assertJsonPath('debug.progress', 40)
            ->assertJsonPath('debug.useful_interventions.0', '影を伝う')
            ->assertJsonPath('debug.misconception', '酸素はまだ半分ある');

        $this->actingAs($intruder)
            ->getJson(route('debug.contact.debug', $game))
            ->assertNotFound();
    }

    public function test_debug_resource_can_be_changed_and_reopens_a_lost_game(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 0, progress: 30);
        $game->update([
            'status' => 'lost',
            'loss_reason' => '酸素が尽きた。',
        ]);

        $this->actingAs($user)
            ->patchJson(route('debug.contact.resource', $game), [
                'resource_remaining' => 12,
            ], [
                'X-Contact-Debug' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('game.status', 'ongoing')
            ->assertJsonPath('game.resource_remaining', 12)
            ->assertJsonPath('game.resource_max', 12)
            ->assertJsonPath('game.loss_reason', null)
            ->assertJsonPath('debug.progress', 30);

        $this->actingAs($user)
            ->patchJson(route('debug.contact.resource', $game), [
                'resource_remaining' => 120,
            ])
            ->assertStatus(422)
            ->assertJsonPath('game.resource_remaining', 12);

        $this->assertSame(12, $game->fresh()->resource_remaining);
    }

    public function test_message_includes_progress_only_when_debug_header_is_sent(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 8, progress: 0);

        $this->mockGemini([
            'reply' => '了解した。',
            'resource_remaining' => 7,
            'change_note' => '岩陰に入った。',
            'status' => 'ongoing',
            'loss_reason' => '',
            'hidden_progress' => 20,
            'hidden_misconception' => 'まだ大丈夫だと思っている。',
            'hidden_intervention_1' => '影を伝う',
            'hidden_intervention_2' => '',
            'hidden_intervention_3' => '',
            'hidden_doom' => '酸欠で倒れる。',
        ]);

        $withoutDebug = $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => '岩陰に入れ。',
            ]);

        $withoutDebug->assertOk();
        $this->assertArrayNotHasKey('debug', $withoutDebug->json());

        $this->mockGemini([
            'reply' => 'もう一段進む。',
            'resource_remaining' => 6,
            'change_note' => '尾根が見えた。',
            'status' => 'ongoing',
            'loss_reason' => '',
            'hidden_progress' => 45,
            'hidden_misconception' => '',
            'hidden_intervention_1' => '影を伝う',
            'hidden_intervention_2' => '',
            'hidden_intervention_3' => '',
            'hidden_doom' => '酸欠で倒れる。',
        ]);

        $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => 'そのまま尾根へ行け。',
            ], [
                'X-Contact-Debug' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('debug.progress', 45);
    }

    public function test_gemini_failure_while_sending_is_retryable_and_keeps_the_game(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 5, progress: 10);

        $gemini = Mockery::mock(GeminiClient::class);
        $gemini->shouldReceive('generateStructured')
            ->once()
            ->andThrow(new GeminiApiException('Gemini API request failed.', 503, true));
        $this->app->instance(GeminiClient::class, $gemini);

        $this->actingAs($user)
            ->postJson(route('debug.contact.message', $game), [
                'message' => '試してくれ。',
            ])
            ->assertStatus(422)
            ->assertJsonPath('retryable', true)
            ->assertJsonPath('game.resource_remaining', 5)
            ->assertJsonPath('game.messages.0.text', 'こちらレナ。');

        $this->assertCount(1, $game->fresh()->messages);
    }

    public function test_page_reloads_the_latest_game_without_hidden_state(): void
    {
        $user = User::factory()->create();
        $game = $this->ongoingGame($user, resourceRemaining: 7, progress: 10);

        $this->actingAs($user)
            ->get(route('debug.contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Debug/Contact')
                ->where('game.id', $game->id)
                ->where('game.situation', '異星の昼だった。')
                ->missing('game.hidden_state')
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mockGemini(array $payload): void
    {
        $gemini = Mockery::mock(GeminiClient::class);
        $gemini->shouldReceive('generateStructured')
            ->once()
            ->andReturn($payload);

        $this->app->instance(GeminiClient::class, $gemini);
    }

    /**
     * @return array<string, mixed>
     */
    private function startPayload(): array
    {
        return [
            'situation' => 'レナは異星の砂の上で目を覚ました。母船は地平線の向こうで応答しない。',
            'character_role' => '漂流した宇宙飛行士',
            'channel' => 'ヘルメットの非常通信が、あなたの受信機にだけ届いている。',
            'clear_condition' => '母船へ戻る航路と、そこまで生きて歩く手順が確定する。',
            'resource_name' => '酸素',
            'resource_remaining' => 8,
            'resource_rule' => '連絡を読み、歩くたびに酸素が減る。',
            'opening_message' => 'こちらレナ。砂の上にいる。母船が見えない。',
            'hidden_progress' => 40,
            'hidden_misconception' => '酸素はまだ半分ある',
            'hidden_intervention_1' => '日向を避けて尾根へ誘導する',
            'hidden_intervention_2' => '遭難ビーコンの周波数を変える',
            'hidden_intervention_3' => '歩行速度を落として酸素を温存する',
            'hidden_doom' => '正午の直射でスーツが停止し、酸欠になる。',
        ];
    }

    private function ongoingGame(User $user, int $resourceRemaining, int $progress): ContactGame
    {
        return ContactGame::query()->create([
            'user_id' => $user->id,
            'status' => 'ongoing',
            'sentence' => '漂流した宇宙飛行士が目を覚ますと、ここは異星だった。',
            'situation' => '異星の昼だった。',
            'character_name' => 'レナ',
            'channel' => '非常通信',
            'clear_condition' => '帰還の手順が確定する。',
            'resource_name' => '酸素',
            'resource_remaining' => $resourceRemaining,
            'resource_max' => 8,
            'resource_rule' => '連絡のたびに減る。',
            'hidden_state' => [
                'progress' => $progress,
                'misconception' => '酸素はまだ半分ある',
                'useful_interventions' => ['影を伝う'],
                'doom_if_ignored' => '酸欠で倒れる。',
            ],
            'messages' => [
                [
                    'role' => 'character',
                    'text' => 'こちらレナ。',
                ],
            ],
        ]);
    }
}
