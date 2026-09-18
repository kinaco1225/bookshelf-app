<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, array $overrides = []): DatabaseNotification
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert(array_merge([
            'id' => $id,
            'type' => 'App\\Notifications\\ReadingPlanReminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'テスト通知',
                'body' => '本文です。',
                'timing' => 'on_due_date',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return DatabaseNotification::findOrFail($id);
    }

    public function test_ゲストは通知一覧にアクセスできずログインへリダイレクトされる(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_通知が無い場合は一覧が空で表示される(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/notifications');

        $response->assertOk();
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->isEmpty());
    }

    public function test_自分の通知のみ新しい順に一覧表示される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $old = $this->createNotification($user, ['created_at' => now()->subDays(2)]);
        $new = $this->createNotification($user, ['created_at' => now()]);
        $this->createNotification($other);

        $response = $this->actingAs($user)->get('/notifications');

        $response->assertOk();
        $response->assertViewHas('notifications', function ($notifications) use ($old, $new) {
            return $notifications->count() === 2
                && $notifications->first()->id === $new->id
                && $notifications->last()->id === $old->id;
        });
    }

    public function test_未読通知には既読にするボタンが表示される(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('未読')
            ->assertSee('既読にする');
    }

    public function test_既読通知には未読バッジとボタンが表示されない(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user, ['read_at' => now()]);

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertDontSee('未読')
            ->assertDontSee('既読にする');
    }

    public function test_ゲストは既読処理できずログインへリダイレクトされる(): void
    {
        $notification = $this->createNotification(User::factory()->create());

        $this->post("/notifications/{$notification->id}/read")->assertRedirect('/login');
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_本人は通知を既読にできる(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->post("/notifications/{$notification->id}/read");

        $response->assertRedirect(route('notifications.index'));
        $response->assertSessionHas('success', '通知を既読にしました。');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_他人の通知は既読にできず403(): void
    {
        $notification = $this->createNotification(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post("/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_存在しない通知の既読処理は404(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/notifications/'.Str::uuid().'/read')
            ->assertNotFound();
    }
}
