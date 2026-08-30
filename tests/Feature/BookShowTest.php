<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookShowTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $attributes = []): Book
    {
        return Book::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2019-07-01',
            'description' => 'これは説明文です。',
        ], $attributes));
    }

    public function test_ゲストも書籍詳細を閲覧できる(): void
    {
        $book = $this->createBook();

        $this->get("/books/{$book->id}")->assertOk();
    }

    public function test_書籍情報とジャンルが表示される(): void
    {
        $book = $this->createBook([
            'title' => '詳細に出る本',
            'author' => '山田著者',
            'isbn' => '9784111111119',
        ]);
        $book->genres()->sync([
            Genre::create(['name' => '技術書'])->id,
            Genre::create(['name' => '歴史'])->id,
        ]);

        $this->get("/books/{$book->id}")
            ->assertOk()
            ->assertSee('詳細に出る本')
            ->assertSee('山田著者')
            ->assertSee('9784111111119')
            ->assertSee('2019-07-01')
            ->assertSee('これは説明文です。')
            ->assertSee('技術書')
            ->assertSee('歴史');
    }

    public function test_レビューが投稿者名とともに表示される(): void
    {
        $book = $this->createBook();
        $reviewer = User::factory()->create(['name' => 'レビュアー花子']);
        Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '学びが多い一冊。',
        ]);

        $this->get("/books/{$book->id}")
            ->assertSee('レビュアー花子')
            ->assertSee('学びが多い一冊。');
    }

    public function test_レビューは新しい順に並ぶ(): void
    {
        $book = $this->createBook();
        $old = Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => 'ふるいレビュー',
        ]);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'あたらしいレビュー',
        ]);

        $this->get("/books/{$book->id}")
            ->assertSeeInOrder(['あたらしいレビュー', 'ふるいレビュー']);
    }

    public function test_存在しない書籍は404(): void
    {
        $this->get('/books/999999')->assertNotFound();
    }

    public function test_ゲストにはレビュー投稿フォームの代わりにログイン導線が出る(): void
    {
        $book = $this->createBook();

        $this->get("/books/{$book->id}")
            ->assertDontSee(route('reviews.store', $book))
            ->assertSee(route('login'));
    }

    public function test_認証済みユーザーにはレビュー投稿フォームが出る(): void
    {
        $book = $this->createBook();

        $this->actingAs(User::factory()->create())
            ->get("/books/{$book->id}")
            ->assertSee(route('reviews.store', $book));
    }
}
