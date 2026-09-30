<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanCompleteTest extends TestCase
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

    public function test_ゲストは完了処理できずログインへリダイレクトされる(): void
    {
        $plan = $this->createPlan(User::factory()->create());

        $this->post("/reading-plans/{$plan->id}/complete")->assertRedirect('/login');
        $this->assertTrue($plan->fresh()->status === ReadingPlanStatus::InProgress);
    }

    public function test_他人は完了処理できず403(): void
    {
        $plan = $this->createPlan(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post("/reading-plans/{$plan->id}/complete")
            ->assertForbidden();

        $this->assertTrue($plan->fresh()->status === ReadingPlanStatus::InProgress);
    }

    public function test_本人は進行中の計画を完了にできる(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner);

        $response = $this->actingAs($owner)->post("/reading-plans/{$plan->id}/complete");

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を「完了」にしました');

        $plan->refresh();
        $this->assertTrue($plan->status === ReadingPlanStatus::Completed);
        $this->assertSame(now()->toDateString(), $plan->completed_at->toDateString());
    }

    public function test_期限切れの計画も完了にできる(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner, [
            'status' => ReadingPlanStatus::Expired,
            'target_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->post("/reading-plans/{$plan->id}/complete")
            ->assertRedirect(route('reading-plans.index'));

        $this->assertTrue($plan->fresh()->status === ReadingPlanStatus::Completed);
    }

    public function test_すでに完了済みの計画は再度完了処理できず403(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner, [
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now()->subDays(2)->toDateString(),
        ]);
        $originalCompletedAt = $plan->completed_at->toDateString();

        $this->actingAs($owner)
            ->post("/reading-plans/{$plan->id}/complete")
            ->assertForbidden();

        $this->assertSame($originalCompletedAt, $plan->fresh()->completed_at->toDateString());
    }

    public function test_存在しない計画の完了処理は404(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/reading-plans/999999/complete')
            ->assertNotFound();
    }
}
