<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(User $owner, array $genreIds = []): Book
    {
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '元のタイトル',
            'author' => '元の著者',
            'isbn' => '9784111111119',
            'published_date' => '2019-01-01',
            'description' => '元の説明',
        ]);
        $book->genres()->sync($genreIds ?: [Genre::create(['name' => '小説'])->id]);

        return $book;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $genreIds, array $overrides = []): array
    {
        return array_merge([
            'title' => '新しいタイトル',
            'author' => '新しい著者',
            'isbn' => '9784111111119',
            'published_date' => '2020-05-05',
            'description' => '新しい説明',
            'genres' => $genreIds,
        ], $overrides);
    }

    public function test_ゲストは編集画面にアクセスできない(): void
    {
        $book = $this->createBook(User::factory()->create());

        $this->get("/books/{$book->id}/edit")->assertRedirect('/login');
    }

    public function test_他人は編集画面にアクセスできず403(): void
    {
        $book = $this->createBook(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get("/books/{$book->id}/edit")
            ->assertForbidden();
    }

    public function test_登録者は編集画面を表示できる(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);

        $this->actingAs($owner)
            ->get("/books/{$book->id}/edit")
            ->assertOk()
            ->assertSee('元のタイトル');
    }

    public function test_登録者は書籍を更新できる(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '技術書']);
        $book = $this->createBook($owner);

        $response = $this->actingAs($owner)
            ->put("/books/{$book->id}", $this->payload([$genre->id]));

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', '書籍を更新しました。');

        $book->refresh();
        $this->assertSame('新しいタイトル', $book->title);
        $this->assertSame([$genre->id], $book->genres->pluck('id')->all());
    }

    public function test_更新時もisbnと出版日は任意項目(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);

        $this->actingAs($owner)
            ->put("/books/{$book->id}", $this->payload(
                $book->genres->pluck('id')->all(),
                ['isbn' => '', 'published_date' => ''],
            ))
            ->assertRedirect(route('books.show', $book));

        $book->refresh();
        $this->assertNull($book->isbn);
        $this->assertNull($book->published_date);
    }

    public function test_他人は書籍を更新できず403(): void
    {
        $book = $this->createBook(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->put("/books/{$book->id}", $this->payload([Genre::create(['name' => '歴史'])->id]))
            ->assertForbidden();

        $this->assertSame('元のタイトル', $book->fresh()->title);
    }

    public function test_更新時のisbn一意性は自身を除外する(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);

        // isbn を変えずにタイトルだけ更新できる
        $this->actingAs($owner)
            ->put("/books/{$book->id}", $this->payload(
                $book->genres->pluck('id')->all(),
                ['isbn' => '9784111111119']
            ))
            ->assertSessionHasNoErrors();
    }

    public function test_他の書籍と同じisbnには更新できない(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Book::create([
            'user_id' => $owner->id,
            'title' => '別の本',
            'author' => '著者',
            'isbn' => '9784999999992',
            'published_date' => '2018-01-01',
        ]);

        $this->actingAs($owner)
            ->put("/books/{$book->id}", $this->payload(
                $book->genres->pluck('id')->all(),
                ['isbn' => '9784999999992']
            ))
            ->assertSessionHasErrors('isbn');
    }

    public function test_登録者は書籍を削除でき関連データも消える(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'レビュー',
        ]);
        User::factory()->create()->favoriteBooks()->attach($book->id);

        $response = $this->actingAs($owner)->delete("/books/{$book->id}");

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を削除しました。');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('book_genre', 0);
    }

    public function test_他人は書籍を削除できず403(): void
    {
        $book = $this->createBook(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->delete("/books/{$book->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }
}
