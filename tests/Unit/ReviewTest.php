<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeReview(array $attributes = []): Review
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'x',
            'author' => 'x',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);

        return Review::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'コメント',
        ], $attributes));
    }

    public function test_リレーションの型が正しい(): void
    {
        $review = new Review;

        $this->assertInstanceOf(BelongsTo::class, $review->user());
        $this->assertInstanceOf(BelongsTo::class, $review->book());
        $this->assertInstanceOf(BelongsToMany::class, $review->likedByUsers());
    }

    public function test_投稿者と書籍を取得できる(): void
    {
        $review = $this->makeReview();

        $this->assertInstanceOf(User::class, $review->user);
        $this->assertInstanceOf(Book::class, $review->book);
    }

    public function test_ratingは整数にキャストされる(): void
    {
        $review = $this->makeReview(['rating' => '5'])->fresh();

        $this->assertSame(5, $review->rating);
    }

    public function test_いいねしたユーザーを取得できる(): void
    {
        $review = $this->makeReview();
        $liker = User::factory()->create();

        $review->likedByUsers()->attach($liker->id);

        $this->assertTrue($review->likedByUsers->contains($liker));
    }
}
