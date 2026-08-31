<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    private function createReview(): Review
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);

        return Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'レビュー本文',
        ]);
    }

    public function test_ゲストはいいねできずログインへ(): void
    {
        $review = $this->createReview();

        $this->post("/reviews/{$review->id}/like")->assertRedirect('/login');
        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_いいねできる(): void
    {
        $review = $this->createReview();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/reviews/{$review->id}/like")
            ->assertRedirect();

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_もう一度押すといいねが外れる(): void
    {
        $review = $this->createReview();
        $user = User::factory()->create();
        $user->likedReviews()->attach($review->id);

        $this->actingAs($user)->post("/reviews/{$review->id}/like");

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_いいね数が書籍詳細に表示される(): void
    {
        $review = $this->createReview();
        $review->likedByUsers()->attach([
            User::factory()->create()->id,
            User::factory()->create()->id,
        ]);

        $this->get("/books/{$review->book_id}")
            ->assertOk()
            ->assertSee('いいね (2)');
    }
}
