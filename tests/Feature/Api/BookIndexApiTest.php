<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookIndexApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $attributes = [], array $genreIds = []): Book
    {
        $book = Book::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
            'description' => '説明',
        ], $attributes));

        if ($genreIds) {
            $book->genres()->sync($genreIds);
        }

        return $book;
    }

    public function test_書籍一覧をjsonで取得できる(): void
    {
        $this->createBook();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'author', 'isbn', 'published_date', 'description', 'image_url', 'genres', 'average_rating', 'reviews_count'],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_各書籍にジャンル_平均評価_レビュー件数が含まれる(): void
    {
        $genre = Genre::create(['name' => '技術書']);
        $book = $this->createBook(['title' => '対象本'], [$genre->id]);
        Review::create(['user_id' => User::factory()->create()->id, 'book_id' => $book->id, 'rating' => 4, 'comment' => 'a']);
        Review::create(['user_id' => User::factory()->create()->id, 'book_id' => $book->id, 'rating' => 5, 'comment' => 'b']);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.genres.0.name', '技術書')
            ->assertJsonPath('data.0.average_rating', 4.5)
            ->assertJsonPath('data.0.reviews_count', 2);
    }

    public function test_レビューが無い書籍の平均評価はnull(): void
    {
        $this->createBook();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.average_rating', null)
            ->assertJsonPath('data.0.reviews_count', 0);
    }

    public function test_キーワードでタイトルと著者を部分一致検索できる(): void
    {
        $this->createBook(['title' => 'Laravel入門', 'author' => '山田']);
        $this->createBook(['title' => 'PHP基礎', 'author' => 'Laravel太郎']);
        $this->createBook(['title' => 'Python', 'author' => '鈴木']);

        $response = $this->getJson('/api/v1/books?keyword=Laravel')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Laravel入門', $titles);
        $this->assertContains('PHP基礎', $titles);
        $this->assertNotContains('Python', $titles);
    }

    public function test_ジャンルidで絞り込める(): void
    {
        $tech = Genre::create(['name' => '技術書']);
        $novel = Genre::create(['name' => '小説']);
        $this->createBook(['title' => '技術書A'], [$tech->id]);
        $this->createBook(['title' => '小説B'], [$novel->id]);

        $response = $this->getJson("/api/v1/books?genre_id={$tech->id}")->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame(['技術書A'], $titles);
    }

    public function test_per_pageで件数を指定できる(): void
    {
        collect(range(1, 5))->each(fn () => $this->createBook());

        $this->getJson('/api/v1/books?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_デフォルトのページあたり件数は20(): void
    {
        $this->createBook();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_存在しないジャンルidはバリデーションエラー(): void
    {
        $this->getJson('/api/v1/books?genre_id=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genre_id');
    }

    public function test_per_pageの上限を超えるとバリデーションエラー(): void
    {
        $this->getJson('/api/v1/books?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
