<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessReadingPlanRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function createPlan(User $owner, array $overrides = []): ReadingPlan
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);

        return ReadingPlan::create(array_merge([
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(5)->toDateString(),
            'status' => ReadingPlanStatus::InProgress,
        ], $overrides));
    }

    public function test_期日を過ぎた進行中の計画は期限切れになる(): void
    {
        $plan = $this->createPlan(User::factory()->create(), [
            'target_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertTrue($plan->fresh()->status === ReadingPlanStatus::Expired);
    }

    public function test_期限切れになった直後にリマインダー通知が届く(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan($user, [
            'target_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $notification = $user->fresh()->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame(ReminderTiming::Expired->value, $notification->data['timing']);
        $this->assertSame($plan->id, $notification->data['reading_plan_id']);
    }

    public function test_当日や未来の計画は自動失効されない(): void
    {
        $today = $this->createPlan(User::factory()->create(), [
            'target_date' => now()->toDateString(),
        ]);
        $future = $this->createPlan(User::factory()->create(), [
            'target_date' => now()->addDay()->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $this->assertTrue($today->fresh()->status === ReadingPlanStatus::InProgress);
        $this->assertTrue($future->fresh()->status === ReadingPlanStatus::InProgress);
    }

    public function test_完了済みの計画は自動失効の対象外(): void
    {
        $plan = $this->createPlan(User::factory()->create(), [
            'status' => ReadingPlanStatus::Completed,
            'target_date' => now()->subDays(10)->toDateString(),
            'completed_at' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $this->assertTrue($plan->fresh()->status === ReadingPlanStatus::Completed);
    }

    public function test_3日前の計画にリマインダー通知が届く(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan($user, [
            'target_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $notification = $user->fresh()->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame(ReminderTiming::ThreeDaysBefore->value, $notification->data['timing']);
        $this->assertSame($plan->id, $notification->data['reading_plan_id']);
    }

    public function test_当日の計画にリマインダー通知が届く(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, [
            'target_date' => now()->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $notification = $user->fresh()->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame(ReminderTiming::OnDueDate->value, $notification->data['timing']);
    }

    public function test_3日後自動失効と同時に期限切れと3日経過の2通の通知が届く(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan($user, [
            'target_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $this->assertTrue($plan->fresh()->status === ReadingPlanStatus::Expired);

        $timings = $user->fresh()->notifications()->get()->pluck('data.timing')->all();
        $this->assertEqualsCanonicalizing(
            [ReminderTiming::Expired->value, ReminderTiming::ThreeDaysAfter->value],
            $timings
        );
    }

    public function test_対象外の期日の計画には通知が届かない(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, [
            'target_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_完了済みの計画には通知が届かない(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, [
            'status' => ReadingPlanStatus::Completed,
            'target_date' => now()->toDateString(),
            'completed_at' => now()->toDateString(),
        ]);

        $this->artisan('reading-plans:process');

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_同じ計画同じタイミングには重複通知しない(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, [
            'target_date' => now()->toDateString(),
        ]);

        $this->artisan('reading-plans:process');
        $this->artisan('reading-plans:process');

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_期限切れ通知も再実行で重複しない(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, [
            'target_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('reading-plans:process');
        $this->artisan('reading-plans:process');

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }
}
