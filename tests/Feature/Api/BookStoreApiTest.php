<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookStoreApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $genreIds
     * @return array<string, mixed>
     */
    private function payload(array $genreIds, array $overrides = []): array
    {
        return array_merge([
            'title' => 'テスト駆動開発',
            'author' => 'Kent Beck',
            'isbn' => '9784274217883',
            'published_date' => '2017-10-14',
            'description' => 'TDD の古典。',
            'image_url' => 'https://example.com/cover.jpg',
            'genres' => $genreIds,
        ], $overrides);
    }

    public function test_ゲストは登録できず401(): void
    {
        $genre = Genre::create(['name' => '小説']);

        $this->postJson('/api/v1/books', $this->payload([$genre->id]))
            ->assertUnauthorized();

        $this->assertDatabaseCount('books', 0);
    }

    public function test_認証済みなら有効なデータで書籍を登録できる(): void
    {
        $g1 = Genre::create(['name' => '技術書']);
        $g2 = Genre::create(['name' => '自己啓発']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', $this->payload([$g1->id, $g2->id]));

        $response->assertCreated()
            ->assertJsonPath('data.title', 'テスト駆動開発')
            ->assertJsonPath('data.isbn', '9784274217883')
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonPath('data.average_rating', null);

        $book = Book::sole();
        $this->assertSame($user->id, $book->user_id);
        $this->assertEqualsCanonicalizing(
            [$g1->id, $g2->id],
            $book->genres->pluck('id')->all()
        );
    }

    public function test_登録者はリクエストのuser_idではなく認証済みユーザーになる(): void
    {
        $genre = Genre::create(['name' => '小説']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/books', $this->payload([$genre->id], ['user_id' => $other->id]))
            ->assertCreated();

        $this->assertSame($user->id, Book::sole()->user_id);
    }

    public function test_レスポンスは201でlocationヘッダを持つ(): void
    {
        $genre = Genre::create(['name' => '小説']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', $this->payload([$genre->id]));

        $response->assertStatus(201);
        $response->assertHeader('Location', route('api.v1.books.show', Book::sole()));
    }

    public function test_説明と画像urlは任意(): void
    {
        $genre = Genre::create(['name' => '小説']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/books', $this->payload([$genre->id], [
            'description' => null,
            'image_url' => null,
        ]))->assertCreated();

        $book = Book::sole();
        $this->assertNull($book->description);
        $this->assertNull($book->image_url);
    }

    public function test_isbnと出版日は任意(): void
    {
        // ISBN検索の自動入力に失敗しても登録できるよう nullable にしている（応用フェーズ）。
        $genre = Genre::create(['name' => '小説']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', $this->payload([$genre->id], [
            'isbn' => null,
            'published_date' => null,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.isbn', null)
            ->assertJsonPath('data.published_date', null);

        $book = Book::sole();
        $this->assertNull($book->isbn);
        $this->assertNull($book->published_date);
    }

    public function test_タイトルは必須で日本語メッセージが返る(): void
    {
        $genre = Genre::create(['name' => '小説']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/books', $this->payload([$genre->id], ['title' => '']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'タイトルを入力してください。');
        $this->assertDatabaseCount('books', 0);
    }

    public function test_isbnは13桁の数字(): void
    {
        $genre = Genre::create(['name' => '小説']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/books', $this->payload([$genre->id], ['isbn' => '123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    public function test_isbnは重複できない(): void
    {
        $genre = Genre::create(['name' => '小説']);
        Sanctum::actingAs(User::factory()->create());
        Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => '既存',
            'author' => '著者',
            'isbn' => '9784274217883',
            'published_date' => '2020-01-01',
        ]);

        $this->postJson('/api/v1/books', $this->payload([$genre->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.isbn.0', 'このISBNの書籍はすでに登録されています。');
        $this->assertDatabaseCount('books', 1);
    }

    public function test_ジャンルは1つ以上必須(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/books', $this->payload([], ['genres' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genres');
    }

    public function test_存在しないジャンルidはエラー(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/books', $this->payload([999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genres.0');
    }
}
