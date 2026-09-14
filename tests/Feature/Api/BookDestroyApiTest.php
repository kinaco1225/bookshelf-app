<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookDestroyApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(): Book
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => '削除対象本',
            'author' => '著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);
        $book->genres()->sync([Genre::create(['name' => '小説'])->id]);

        return $book;
    }

    public function test_書籍を削除できる_204でボディなし(): void
    {
        $book = $this->createBook();

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertNoContent(); // 204 かつ本文が空
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_関連データも一緒に削除される(): void
    {
        $book = $this->createBook();
        Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'レビュー',
        ]);
        User::factory()->create()->favoriteBooks()->attach($book->id);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();

        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertSame(0, DB::table('book_genre')->count());
    }

    public function test_存在しないidは404のjsonエラー(): void
    {
        $this->deleteJson('/api/v1/books/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => '指定されたリソースが見つかりません。']);
    }
}
