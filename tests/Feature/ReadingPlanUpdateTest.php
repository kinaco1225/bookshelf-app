<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanUpdateTest extends TestCase
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

    // --- 編集画面 ---

    public function test_ゲストは編集画面にアクセスできない(): void
    {
        $plan = $this->createPlan(User::factory()->create());

        $this->get("/reading-plans/{$plan->id}/edit")->assertRedirect('/login');
    }

    public function test_他人は編集画面にアクセスできず403(): void
    {
        $plan = $this->createPlan(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get("/reading-plans/{$plan->id}/edit")
            ->assertForbidden();
    }

    public function test_本人は編集画面を表示できる(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner);

        $this->actingAs($owner)
            ->get("/reading-plans/{$plan->id}/edit")
            ->assertOk()
            ->assertSee('テスト書籍');
    }

    public function test_完了済みの計画は本人でも編集画面にアクセスできず403(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner, ['status' => ReadingPlanStatus::Completed]);

        $this->actingAs($owner)
            ->get("/reading-plans/{$plan->id}/edit")
            ->assertForbidden();
    }

    // --- 更新 ---

    public function test_本人は期日を更新できる(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner);
        $newDate = now()->addDays(20)->toDateString();

        $response = $this->actingAs($owner)
            ->put("/reading-plans/{$plan->id}", ['target_date' => $newDate]);

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を更新しました');

        $this->assertSame($newDate, $plan->fresh()->target_date->toDateString());
    }

    public function test_他人は更新できず403(): void
    {
        $plan = $this->createPlan(User::factory()->create());
        $originalDate = $plan->target_date->toDateString();

        $this->actingAs(User::factory()->create())
            ->put("/reading-plans/{$plan->id}", ['target_date' => now()->addDays(20)->toDateString()])
            ->assertForbidden();

        $this->assertSame($originalDate, $plan->fresh()->target_date->toDateString());
    }

    public function test_完了済みの計画は更新できず403(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner, ['status' => ReadingPlanStatus::Completed]);

        $this->actingAs($owner)
            ->put("/reading-plans/{$plan->id}", ['target_date' => now()->addDays(20)->toDateString()])
            ->assertForbidden();
    }

    public function test_期日は必須(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner);

        $this->actingAs($owner)
            ->put("/reading-plans/{$plan->id}", [])
            ->assertSessionHasErrors(['target_date' => '期日を入力してください']);
    }

    public function test_期日は今日以降でなければならない(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner);

        $this->actingAs($owner)
            ->put("/reading-plans/{$plan->id}", ['target_date' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を指定してください']);
    }

    public function test_存在しない計画の編集は404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/reading-plans/999999/edit')
            ->assertNotFound();
    }

    // --- 削除 ---

    public function test_本人は計画を削除できる(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner);

        $response = $this->actingAs($owner)->delete("/reading-plans/{$plan->id}");

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を削除しました');
        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_他人は削除できず403(): void
    {
        $plan = $this->createPlan(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->delete("/reading-plans/{$plan->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }

    public function test_完了済みの計画でも本人は削除できる(): void
    {
        $owner = User::factory()->create();
        $plan = $this->createPlan($owner, ['status' => ReadingPlanStatus::Completed]);

        $this->actingAs($owner)
            ->delete("/reading-plans/{$plan->id}")
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_存在しない計画の削除は404(): void
    {
        $this->actingAs(User::factory()->create())
            ->delete('/reading-plans/999999')
            ->assertNotFound();
    }
}
