<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewStoreTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(): Book
    {
        return Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);
    }

    public function test_ゲストはレビューを投稿できずログインへ(): void
    {
        $book = $this->createBook();

        $this->post("/books/{$book->id}/reviews", ['rating' => 5, 'comment' => 'よい'])
            ->assertRedirect('/login');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_認証済みユーザーはレビューを投稿できる(): void
    {
        $book = $this->createBook();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post("/books/{$book->id}/reviews", ['rating' => 4, 'comment' => '参考になった']);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success');

        $review = Review::sole();
        $this->assertSame($user->id, $review->user_id);
        $this->assertSame($book->id, $review->book_id);
        $this->assertSame(4, $review->rating);
        $this->assertSame('参考になった', $review->comment);
    }

    public function test_コメントは必須(): void
    {
        $book = $this->createBook();

        $this->actingAs(User::factory()->create())
            ->post("/books/{$book->id}/reviews", ['rating' => 3, 'comment' => ''])
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_評価は必須(): void
    {
        $book = $this->createBook();

        $this->actingAs(User::factory()->create())
            ->post("/books/{$book->id}/reviews", ['comment' => 'コメントのみ'])
            ->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_評価は1から5の範囲(): void
    {
        $book = $this->createBook();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/books/{$book->id}/reviews", ['rating' => 0])
            ->assertSessionHasErrors('rating');

        $this->actingAs($user)
            ->post("/books/{$book->id}/reviews", ['rating' => 6])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_同じ書籍に二重投稿はできない(): void
    {
        $book = $this->createBook();
        $user = User::factory()->create();
        Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '最初の投稿',
        ]);

        $this->actingAs($user)
            ->post("/books/{$book->id}/reviews", ['rating' => 1, 'comment' => '二回目'])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_別ユーザーは同じ書籍にレビューできる(): void
    {
        $book = $this->createBook();
        Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '他人のレビュー',
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/books/{$book->id}/reviews", ['rating' => 2, 'comment' => '私のレビュー'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reviews', 2);
    }
}
