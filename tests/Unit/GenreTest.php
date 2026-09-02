<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_書籍リレーションの型が正しい(): void
    {
        $this->assertInstanceOf(BelongsToMany::class, (new Genre)->books());
    }

    public function test_書籍と多対多で紐づく(): void
    {
        $genre = Genre::create(['name' => '技術書']);
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'x',
            'author' => 'x',
            'isbn' => '9784000000002',
            'published_date' => '2020-01-01',
        ]);

        $genre->books()->attach($book->id);

        $this->assertTrue($genre->books->contains($book));
        $this->assertSame(1, Genre::withCount('books')->find($genre->id)->books_count);
    }
}
