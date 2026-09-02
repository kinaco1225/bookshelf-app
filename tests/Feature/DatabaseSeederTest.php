<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_ユーザーが5件投入されパスワードがハッシュ化されている(): void
    {
        $this->assertDatabaseCount('users', 5);

        $yamada = User::where('email', 'yamada@example.com')->first();
        $this->assertNotNull($yamada);
        $this->assertSame('山田太郎', $yamada->name);
        $this->assertTrue(Hash::check('password', $yamada->password));
    }

    public function test_ジャンルが10件投入される(): void
    {
        $this->assertDatabaseCount('genres', 10);
    }

    public function test_書籍が11件投入され登録者は山田太郎(): void
    {
        $this->assertDatabaseCount('books', 11);

        $yamada = User::where('email', 'yamada@example.com')->first();
        $this->assertSame(11, Book::where('user_id', $yamada->id)->count());

        $book = Book::where('isbn', '9784422100524')->first(); // 人を動かす
        $this->assertSame('人を動かす', $book->title);
        $this->assertSame('https://placehold.co/200x300/e2e8f0/475569?text=2', $book->image_url);
        $this->assertEqualsCanonicalizing(
            ['ビジネス', '自己啓発'],
            $book->genres->pluck('name')->all()
        );
    }

    public function test_レビューが32件_各書籍2から4件_評価3から5_本人レビューなし(): void
    {
        $this->assertDatabaseCount('reviews', 32);

        $this->assertSame(0, Review::whereNotBetween('rating', [3, 5])->count());

        Book::withCount('reviews')->get()->each(function (Book $book): void {
            $this->assertGreaterThanOrEqual(2, $book->reviews_count);
            $this->assertLessThanOrEqual(4, $book->reviews_count);
        });

        Review::with('book')->get()->each(function (Review $review): void {
            $this->assertNotSame(
                $review->book->user_id,
                $review->user_id,
                '書籍の登録者が自分の書籍にレビューしている'
            );
        });
    }

    public function test_各ユーザーのお気に入りは3から5冊(): void
    {
        User::withCount('favoriteBooks')->get()->each(function (User $user): void {
            $this->assertGreaterThanOrEqual(3, $user->favorite_books_count);
            $this->assertLessThanOrEqual(5, $user->favorite_books_count);
        });
    }

    public function test_いいねは投稿者本人以外から付けられている(): void
    {
        $this->assertGreaterThan(0, DB::table('review_likes')->count());

        $selfLikes = DB::table('review_likes')
            ->join('reviews', 'reviews.id', '=', 'review_likes.review_id')
            ->whereColumn('review_likes.user_id', 'reviews.user_id')
            ->count();

        $this->assertSame(0, $selfLikes);
    }
}
