<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $genreIds): Book
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);
        $book->genres()->sync($genreIds);

        return $book;
    }

    public function test_ゲストはジャンル一覧にアクセスできずログインへ(): void
    {
        $this->get('/genres')->assertRedirect('/login');
    }

    public function test_認証済みユーザーはジャンル一覧を表示できる(): void
    {
        Genre::create(['name' => '小説']);
        Genre::create(['name' => '技術書']);

        $this->actingAs(User::factory()->create())
            ->get('/genres')
            ->assertOk()
            ->assertSee('小説')
            ->assertSee('技術書');
    }

    public function test_各ジャンルの書籍数が表示される(): void
    {
        $fiction = Genre::create(['name' => '小説']);
        $tech = Genre::create(['name' => '技術書']);

        $this->createBook([$fiction->id, $tech->id]);
        $this->createBook([$fiction->id]);

        $response = $this->actingAs(User::factory()->create())->get('/genres');

        $response->assertSee('2冊'); // 小説
        $response->assertSee('1冊'); // 技術書
    }

    public function test_ジャンルが無いときはメッセージを表示する(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/genres')
            ->assertOk()
            ->assertSee('ジャンルが登録されていません。');
    }
}
