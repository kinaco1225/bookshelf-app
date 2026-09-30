<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanCreateTest extends TestCase
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

    public function test_ゲストは作成フォームにアクセスできない(): void
    {
        $this->get('/reading-plans/create')->assertRedirect('/login');
    }

    public function test_ゲストは読書計画を作成できない(): void
    {
        $book = $this->createBook();

        $this->post('/reading-plans', [
            'book_id' => $book->id,
            'target_date' => now()->addDays(3)->toDateString(),
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_認証済みユーザーは作成フォームに書籍の選択肢が表示される(): void
    {
        $this->createBook('選択肢に出る本');

        $this->actingAs(User::factory()->create())
            ->get('/reading-plans/create')
            ->assertOk()
            ->assertSee('選択肢に出る本');
    }

    public function test_有効なデータで読書計画を作成できる(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $response = $this->actingAs($user)->post('/reading-plans', [
            'book_id' => $book->id,
            'target_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を作成しました');

        $plan = ReadingPlan::sole();
        $this->assertSame($user->id, $plan->user_id);
        $this->assertSame($book->id, $plan->book_id);
        $this->assertTrue($plan->status === ReadingPlanStatus::InProgress);
        $this->assertNull($plan->completed_at);
    }

    public function test_書籍は必須(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/reading-plans', ['target_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['book_id' => '書籍を選択してください']);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_存在しない書籍idはエラー(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/reading-plans', [
                'book_id' => 999999,
                'target_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('book_id');
    }

    public function test_期日は必須(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $this->actingAs($user)
            ->post('/reading-plans', ['book_id' => $book->id])
            ->assertSessionHasErrors(['target_date' => '期日を入力してください']);
    }

    public function test_期日は今日以降でなければならない(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $this->actingAs($user)
            ->post('/reading-plans', [
                'book_id' => $book->id,
                'target_date' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を指定してください']);
    }

    public function test_同じ書籍に進行中の計画がすでにあると重複エラー(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->actingAs($user)
            ->post('/reading-plans', [
                'book_id' => $book->id,
                'target_date' => now()->addDays(10)->toDateString(),
            ])
            ->assertSessionHasErrors(['book_id' => 'この書籍にはすでに進行中の読書計画があります']);

        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_完了済みの計画がある書籍には新たに計画を作れる(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->subDays(10)->toDateString(),
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($user)
            ->post('/reading-plans', [
                'book_id' => $book->id,
                'target_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reading_plans', 2);
    }

    public function test_別ユーザーが進行中でも自分は同じ書籍に計画を作れる(): void
    {
        $book = $this->createBook();
        ReadingPlan::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/reading-plans', [
                'book_id' => $book->id,
                'target_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertSessionHasNoErrors();
    }
}
