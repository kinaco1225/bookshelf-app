<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookUpdateApiTest extends TestCase
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
     * @param  array<int, int>  $genreIds
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

    public function test_ゲストは更新できず401(): void
    {
        $book = $this->createBook(User::factory()->create());

        $this->putJson("/api/v1/books/{$book->id}", $this->payload($book->genres->pluck('id')->all()))
            ->assertUnauthorized();

        $this->assertSame('元のタイトル', $book->fresh()->title);
    }

    public function test_所有者以外は更新できず403(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/books/{$book->id}", $this->payload($book->genres->pluck('id')->all()))
            ->assertForbidden();

        $this->assertSame('元のタイトル', $book->fresh()->title);
    }

    public function test_所有者は書籍を更新できる(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        $newGenre = Genre::create(['name' => '技術書']);
        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/books/{$book->id}", $this->payload([$newGenre->id]));

        $response->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '新しいタイトル')
            ->assertJsonPath('data.genres.0.name', '技術書');

        $book->refresh();
        $this->assertSame('新しいタイトル', $book->title);
        $this->assertSame([$newGenre->id], $book->genres->pluck('id')->all());
    }

    public function test_リクエストにuser_idを含めても登録者は変わらない(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = $this->createBook($owner);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['user_id' => $other->id],
        ))->assertOk();

        $this->assertSame($owner->id, $book->fresh()->user_id);
    }

    public function test_isbnと出版日は空にできる(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['isbn' => null, 'published_date' => null],
        ));

        $response->assertOk()
            ->assertJsonPath('data.isbn', null)
            ->assertJsonPath('data.published_date', null);

        $book->refresh();
        $this->assertNull($book->isbn);
        $this->assertNull($book->published_date);
    }

    public function test_存在しないidは404のjsonエラー(): void
    {
        $genre = Genre::create(['name' => '小説']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/books/999999', $this->payload([$genre->id]))
            ->assertNotFound()
            ->assertExactJson(['message' => '指定されたリソースが見つかりません']);
    }

    public function test_isbnを変えずに更新できる_一意性チェックで自身を除外(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['isbn' => '9784111111119'],
        ))->assertOk();
    }

    public function test_他の書籍と同じisbnには更新できない(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Sanctum::actingAs($owner);
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
            ->assertJsonPath('errors.isbn.0', 'このISBNの書籍はすでに登録されています');
    }

    public function test_必須項目が欠けると422で日本語メッセージ(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/books/{$book->id}", $this->payload(
            $book->genres->pluck('id')->all(),
            ['title' => ''],
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'タイトルを入力してください');

        $this->assertSame('元のタイトル', $book->fresh()->title);
    }

    public function test_ジャンルは1つ以上必須(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/books/{$book->id}", $this->payload([], ['genres' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genres');
    }
}
