<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCreateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $genreIds
     * @return array<string, mixed>
     */
    private function validPayload(array $genreIds, array $overrides = []): array
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

    public function test_ゲストは登録フォームにアクセスできずログインへリダイレクトされる(): void
    {
        $this->get('/books/create')->assertRedirect('/login');
    }

    public function test_ゲストは書籍を登録できずログインへリダイレクトされる(): void
    {
        $this->post('/books', $this->validPayload([1]))->assertRedirect('/login');
        $this->assertDatabaseCount('books', 0);
    }

    public function test_認証済みユーザーは登録フォームを表示できる(): void
    {
        $genre = Genre::create(['name' => '技術書']);

        $this->actingAs(User::factory()->create())
            ->get('/books/create')
            ->assertOk()
            ->assertSee('技術書');
    }

    public function test_認証済みユーザーは書籍を登録できる(): void
    {
        $user = User::factory()->create();
        $g1 = Genre::create(['name' => '技術書']);
        $g2 = Genre::create(['name' => '自己啓発']);

        $response = $this->actingAs($user)
            ->post('/books', $this->validPayload([$g1->id, $g2->id]));

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success');

        $book = Book::first();
        $this->assertNotNull($book);
        $this->assertSame('テスト駆動開発', $book->title);
        $this->assertSame($user->id, $book->user_id);
        $this->assertEqualsCanonicalizing(
            [$g1->id, $g2->id],
            $book->genres->pluck('id')->all()
        );
    }

    public function test_説明と画像urlは任意項目(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        $this->actingAs($user)
            ->post('/books', $this->validPayload([$genre->id], [
                'description' => '',
                'image_url' => '',
            ]))
            ->assertRedirect(route('books.index'));

        $book = Book::first();
        $this->assertNull($book->description);
        $this->assertNull($book->image_url);
    }

    public function test_タイトルは必須(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        $this->actingAs($user)
            ->post('/books', $this->validPayload([$genre->id], ['title' => '']))
            ->assertSessionHasErrors('title');
        $this->assertDatabaseCount('books', 0);
    }

    public function test_isbnは13桁の数字(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        $this->actingAs($user)
            ->post('/books', $this->validPayload([$genre->id], ['isbn' => '123']))
            ->assertSessionHasErrors('isbn');
    }

    public function test_isbnは重複できない(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        Book::create([
            'user_id' => $user->id,
            'title' => '既存',
            'author' => '著者',
            'isbn' => '9784274217883',
            'published_date' => '2020-01-01',
        ]);

        $this->actingAs($user)
            ->post('/books', $this->validPayload([$genre->id]))
            ->assertSessionHasErrors('isbn');
        $this->assertDatabaseCount('books', 1);
    }

    public function test_ジャンルは1つ以上必須(): void
    {
        $user = User::factory()->create();
        Genre::create(['name' => '小説']);

        $this->actingAs($user)
            ->post('/books', $this->validPayload([], ['genres' => []]))
            ->assertSessionHasErrors('genres');
        $this->assertDatabaseCount('books', 0);
    }
}
