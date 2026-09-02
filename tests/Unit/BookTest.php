<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    private function makeBook(): Book
    {
        return Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);
    }

    public function test_リレーションの型が正しい(): void
    {
        $book = new Book;

        $this->assertInstanceOf(BelongsTo::class, $book->user());
        $this->assertInstanceOf(BelongsToMany::class, $book->genres());
        $this->assertInstanceOf(HasMany::class, $book->reviews());
        $this->assertInstanceOf(BelongsToMany::class, $book->favoritedByUsers());
    }

    public function test_登録者を取得できる(): void
    {
        $user = User::factory()->create();
        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'x',
            'author' => 'x',
            'isbn' => '9784000000001',
            'published_date' => '2020-01-01',
        ]);

        $this->assertTrue($book->user->is($user));
    }

    public function test_ジャンルと多対多で紐づく(): void
    {
        $book = $this->makeBook();
        $genre = Genre::create(['name' => '小説']);

        $book->genres()->attach($genre->id);

        $this->assertTrue($book->genres->contains($genre));
        $this->assertTrue($genre->books->contains($book));
    }

    public function test_レビューを複数持てる(): void
    {
        $book = $this->makeBook();
        Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => null,
        ]);

        $this->assertCount(1, $book->reviews);
    }

    public function test_published_dateは文字列のまま扱われる(): void
    {
        $book = $this->makeBook()->fresh();

        $this->assertIsString($book->published_date);
        $this->assertSame('2020-01-01', $book->published_date);
    }
}
