<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(string $title = 'テスト書籍'): Book
    {
        return Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);
    }

    private function createPlan(User $user, array $overrides = []): ReadingPlan
    {
        return ReadingPlan::create(array_merge([
            'user_id' => $user->id,
            'book_id' => $this->createBook()->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlanStatus::InProgress,
        ], $overrides));
    }

    public function test_ゲストは一覧にアクセスできずログインへリダイレクトされる(): void
    {
        $this->get('/reading-plans')->assertRedirect('/login');
    }

    public function test_読書計画が無いときはメッセージを表示する(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/reading-plans')
            ->assertOk()
            ->assertSee('該当する読書計画はありません。');
    }

    public function test_自分の読書計画だけが表示され他人の計画は表示されない(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook('自分の計画の本');
        $this->createPlan($user, ['book_id' => $book->id]);

        $otherBook = $this->createBook('他人の計画の本');
        $this->createPlan(User::factory()->create(), ['book_id' => $otherBook->id]);

        $response = $this->actingAs($user)->get('/reading-plans');

        $response->assertOk()
            ->assertSee('自分の計画の本')
            ->assertDontSee('他人の計画の本');
    }

    public function test_書籍名_期日_完了日_状態が表示される(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook('表示確認用の本');
        $this->createPlan($user, [
            'book_id' => $book->id,
            'target_date' => '2026-12-01',
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => '2026-11-20',
        ]);

        $this->actingAs($user)
            ->get('/reading-plans')
            ->assertOk()
            ->assertSee('表示確認用の本')
            ->assertSee('2026-12-01')
            ->assertSee('2026-11-20')
            ->assertSee('完了');
    }

    public function test_statusで絞り込める(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, [
            'book_id' => $this->createBook('進行中の本')->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);
        $this->createPlan($user, [
            'book_id' => $this->createBook('完了済みの本')->id,
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get('/reading-plans?status=completed');

        $response->assertOk()
            ->assertSee('完了済みの本')
            ->assertDontSee('進行中の本');
    }

    public function test_不正なstatusは無視されて全件表示される(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, ['book_id' => $this->createBook('対象の本')->id]);

        $this->actingAs($user)
            ->get('/reading-plans?status=invalid-value')
            ->assertOk()
            ->assertSee('対象の本');
    }

    public function test_期日が近い順に並ぶ(): void
    {
        $user = User::factory()->create();
        $this->createPlan($user, [
            'book_id' => $this->createBook('遠い期日の本')->id,
            'target_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->createPlan($user, [
            'book_id' => $this->createBook('近い期日の本')->id,
            'target_date' => now()->addDays(1)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get('/reading-plans')
            ->assertSeeInOrder(['近い期日の本', '遠い期日の本']);
    }
}
