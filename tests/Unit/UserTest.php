<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function makeBook(User $owner): Book
    {
        return Book::create([
            'user_id' => $owner->id,
            'title' => 'x',
            'author' => 'x',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);
    }

    public function test_リレーションの型が正しい(): void
    {
        $user = new User;

        $this->assertInstanceOf(HasMany::class, $user->books());
        $this->assertInstanceOf(HasMany::class, $user->reviews());
        $this->assertInstanceOf(BelongsToMany::class, $user->favoriteBooks());
        $this->assertInstanceOf(BelongsToMany::class, $user->likedReviews());
    }

    public function test_パスワードはhashedキャストで保存される(): void
    {
        $user = User::factory()->create(['password' => 'plain-password']);

        $this->assertNotSame('plain-password', $user->password);
        $this->assertTrue(Hash::check('plain-password', $user->password));
    }

    public function test_パスワードとremember_tokenはシリアライズ時に隠される(): void
    {
        $array = User::factory()->create()->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_登録した書籍と投稿したレビューを取得できる(): void
    {
        $user = User::factory()->create();
        $book = $this->makeBook($user);
        Review::create([
            'user_id' => $user->id,
            'book_id' => $this->makeBook(User::factory()->create())->id,
            'rating' => 3,
            'comment' => null,
        ]);

        $this->assertTrue($user->books->contains($book));
        $this->assertCount(1, $user->reviews);
    }

    public function test_お気に入り書籍といいねしたレビューを取得できる(): void
    {
        $user = User::factory()->create();
        $book = $this->makeBook(User::factory()->create());
        $review = Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => null,
        ]);

        $user->favoriteBooks()->attach($book->id);
        $user->likedReviews()->attach($review->id);

        $this->assertTrue($user->favoriteBooks->contains($book));
        $this->assertTrue($user->likedReviews->contains($review));
    }
}
