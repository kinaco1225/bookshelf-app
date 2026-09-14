<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookShowApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $attributes = []): Book
    {
        return Book::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
            'description' => '説明',
        ], $attributes));
    }

    public function test_書籍詳細をjsonで取得できる(): void
    {
        $book = $this->createBook(['title' => '詳細対象本']);
        $book->genres()->sync([Genre::create(['name' => '技術書'])->id]);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'author', 'isbn', 'published_date', 'description',
                    'image_url', 'genres', 'average_rating', 'reviews_count', 'reviews',
                ],
            ])
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '詳細対象本')
            ->assertJsonPath('data.genres.0.name', '技術書');
    }

    public function test_レビューが投稿者名_評価_コメント_投稿日時付きで含まれる(): void
    {
        $book = $this->createBook();
        $reviewer = User::factory()->create(['name' => 'レビュアー花子']);
        Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '学びが多い一冊。',
        ]);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.reviews.0.user_name', 'レビュアー花子')
            ->assertJsonPath('data.reviews.0.rating', 4)
            ->assertJsonPath('data.reviews.0.comment', '学びが多い一冊。')
            ->assertJsonStructure(['data' => ['reviews' => [['id', 'user_name', 'rating', 'comment', 'created_at']]]]);
    }

    public function test_平均評価が計算される(): void
    {
        $book = $this->createBook();
        foreach ([3, 4, 5] as $rating) {
            Review::create([
                'user_id' => User::factory()->create()->id,
                'book_id' => $book->id,
                'rating' => $rating,
                'comment' => 'c',
            ]);
        }

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.average_rating', 4);
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

        $comments = collect($this->getJson("/api/v1/books/{$book->id}")->json('data.reviews'))
            ->pluck('comment')
            ->all();

        $this->assertSame(['あたらしいレビュー', 'ふるいレビュー'], $comments);
    }

    public function test_存在しないidは404のjsonエラー(): void
    {
        $this->getJson('/api/v1/books/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => '指定されたリソースが見つかりません。']);
    }
}
