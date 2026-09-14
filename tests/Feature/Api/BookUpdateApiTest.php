<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $genreIds = []): Book
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
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
     * @param  array<int, int>  $genreIds
     * @return array<string, mixed>
     */
    private function payload(array $genreIds, array $overrides = []): array
    {
        return array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => '新しいタイトル',
            'author' => '新しい著者',
            'isbn' => '9784111111119',
            'published_date' => '2020-05-05',
            'description' => '新しい説明',
            'genres' => $genreIds,
        ], $overrides);
    }

    public function test_書籍を更新できる(): void
    {
        $book = $this->createBook();
        $newGenre = Genre::create(['name' => '技術書']);

        $response = $this->putJson("/api/v1/books/{$book->id}", $this->payload([$newGenre->id]));

        $response->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '新しいタイトル')
            ->assertJsonPath('data.genres.0.name', '技術書');

        $book->refresh();
        $this->assertSame('新しいタイトル', $book->title);
        $this->assertSame([$newGenre->id], $book->genres->pluck('id')->all());
    }

    public function test_存在しないidは404のjsonエラー(): void
    {
        $genre = Genre::create(['name' => '小説']);

        $this->putJson('/api/v1/books/999999', $this->payload([$genre->id]))
            ->assertNotFound()
            ->assertExactJson(['message' => '指定されたリソースが見つかりません。']);
    }

    public function test_isbnを変えずに更新できる_一意性チェックで自身を除外(): void
    {
        $book = $this->createBook();

        $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['isbn' => '9784111111119'],
        ))->assertOk();
    }

    public function test_他の書籍と同じisbnには更新できない(): void
    {
        $book = $this->createBook();
        Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => '別の本',
            'author' => '著者',
            'isbn' => '9784999999992',
            'published_date' => '2018-01-01',
        ]);

        $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['isbn' => '9784999999992'],
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.isbn.0', 'このISBNの書籍はすでに登録されています。');
    }

    public function test_必須項目が欠けると422で日本語メッセージ(): void
    {
        $book = $this->createBook();

        $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['title' => ''],
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'タイトルを入力してください。');

        $this->assertSame('元のタイトル', $book->fresh()->title);
    }

    public function test_登録者idの妥当性も検証される(): void
    {
        $book = $this->createBook();

        $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['user_id' => 999999],
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_ジャンルは1つ以上必須(): void
    {
        $book = $this->createBook();

        $this->putJson("/api/v1/books/{$book->id}", $this->payload([], ['genres' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genres');
    }
}
