<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createReview(User $author): Review
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);

        return Review::create([
            'user_id' => $author->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '元のコメント',
        ]);
    }

    public function test_ゲストはレビュー編集画面にアクセスできない(): void
    {
        $review = $this->createReview(User::factory()->create());

        $this->get("/reviews/{$review->id}/edit")->assertRedirect('/login');
    }

    public function test_他人はレビュー編集画面にアクセスできず403(): void
    {
        $review = $this->createReview(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get("/reviews/{$review->id}/edit")
            ->assertForbidden();
    }

    public function test_投稿者はレビュー編集画面を表示できる(): void
    {
        $author = User::factory()->create();
        $review = $this->createReview($author);

        $this->actingAs($author)
            ->get("/reviews/{$review->id}/edit")
            ->assertOk()
            ->assertSee('元のコメント');
    }

    public function test_投稿者はレビューを更新できる(): void
    {
        $author = User::factory()->create();
        $review = $this->createReview($author);

        $response = $this->actingAs($author)
            ->put("/reviews/{$review->id}", ['rating' => 5, 'comment' => '更新後のコメント']);

        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('success', 'レビューを更新しました');

        $review->refresh();
        $this->assertSame(5, $review->rating);
        $this->assertSame('更新後のコメント', $review->comment);
    }

    public function test_更新時に二重投稿バリデーションは発火しない(): void
    {
        $author = User::factory()->create();
        $review = $this->createReview($author);

        $this->actingAs($author)
            ->put("/reviews/{$review->id}", ['rating' => 4, 'comment' => 'ok'])
            ->assertSessionHasNoErrors();
    }

    public function test_他人はレビューを更新できず403(): void
    {
        $review = $this->createReview(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->put("/reviews/{$review->id}", ['rating' => 1, 'comment' => '書き換え'])
            ->assertForbidden();

        $this->assertSame('元のコメント', $review->fresh()->comment);
    }

    public function test_投稿者はレビューを削除できいいねも消える(): void
    {
        $author = User::factory()->create();
        $review = $this->createReview($author);
        $review->likedByUsers()->attach(User::factory()->create()->id);

        $response = $this->actingAs($author)->delete("/reviews/{$review->id}");

        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('success', 'レビューを削除しました');

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_他人はレビューを削除できず403(): void
    {
        $review = $this->createReview(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->delete("/reviews/{$review->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
