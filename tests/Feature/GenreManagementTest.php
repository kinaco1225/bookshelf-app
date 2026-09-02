<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function bookInGenre(Genre $genre): Book
    {
        $book = Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ]);
        $book->genres()->sync([$genre->id]);

        return $book;
    }

    // --- 詳細 ---

    public function test_ゲストはジャンル詳細にアクセスできない(): void
    {
        $genre = Genre::create(['name' => '小説']);

        $this->get("/genres/{$genre->id}")->assertRedirect('/login');
    }

    public function test_ジャンル詳細に紐づく書籍が表示される(): void
    {
        $this->actingUser();
        $genre = Genre::create(['name' => '小説']);
        $inGenre = $this->bookInGenre($genre);
        $inGenre->update(['title' => 'このジャンルの本']);

        $other = Genre::create(['name' => '技術書']);
        $this->bookInGenre($other)->update(['title' => '別ジャンルの本']);

        $this->get("/genres/{$genre->id}")
            ->assertOk()
            ->assertSee('このジャンルの本')
            ->assertDontSee('別ジャンルの本');
    }

    public function test_ジャンル詳細の書籍は10件ずつページネーションされる(): void
    {
        $this->actingUser();
        $genre = Genre::create(['name' => '小説']);
        collect(range(1, 12))->each(fn () => $this->bookInGenre($genre));

        $this->get("/genres/{$genre->id}")
            ->assertOk()
            ->assertSee('page=2');
    }

    // --- 登録 ---

    public function test_認証済みユーザーはジャンル登録フォームを表示できる(): void
    {
        $this->actingUser();

        $this->get('/genres/create')->assertOk();
    }

    public function test_ゲストはジャンル登録フォームにアクセスできない(): void
    {
        $this->get('/genres/create')->assertRedirect('/login');
    }

    public function test_認証済みユーザーはジャンルを登録できる(): void
    {
        $this->actingUser();

        $this->post('/genres', ['name' => 'ライトノベル'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを登録しました。');

        $this->assertDatabaseHas('genres', ['name' => 'ライトノベル']);
    }

    public function test_ジャンル名は必須(): void
    {
        $this->actingUser();

        $this->post('/genres', ['name' => ''])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('genres', 0);
    }

    public function test_ジャンル名は重複できない(): void
    {
        $this->actingUser();
        Genre::create(['name' => '小説']);

        $this->post('/genres', ['name' => '小説'])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('genres', 1);
    }

    // --- 編集 ---

    public function test_ゲストはジャンル編集フォームにアクセスできない(): void
    {
        $genre = Genre::create(['name' => '小説']);

        $this->get("/genres/{$genre->id}/edit")->assertRedirect('/login');
    }

    public function test_認証済みユーザーはジャンル編集フォームに現在の名前が表示される(): void
    {
        $this->actingUser();
        $genre = Genre::create(['name' => '小説']);

        $this->get("/genres/{$genre->id}/edit")
            ->assertOk()
            ->assertSee('小説');
    }

    public function test_認証済みユーザーはジャンルを更新できる(): void
    {
        $this->actingUser();
        $genre = Genre::create(['name' => '小説']);

        $this->put("/genres/{$genre->id}", ['name' => '文芸'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->assertSame('文芸', $genre->fresh()->name);
    }

    public function test_更新時に名前を変えなくてもエラーにならない(): void
    {
        $this->actingUser();
        $genre = Genre::create(['name' => '小説']);

        $this->put("/genres/{$genre->id}", ['name' => '小説'])
            ->assertSessionHasNoErrors();
    }

    public function test_他のジャンルと同じ名前には更新できない(): void
    {
        $this->actingUser();
        Genre::create(['name' => '技術書']);
        $genre = Genre::create(['name' => '小説']);

        $this->put("/genres/{$genre->id}", ['name' => '技術書'])
            ->assertSessionHasErrors('name');
    }

    // --- 削除 ---

    public function test_書籍が紐づかないジャンルは削除できる(): void
    {
        $this->actingUser();
        $genre = Genre::create(['name' => '未使用ジャンル']);

        $this->delete("/genres/{$genre->id}")
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_書籍が紐づくジャンルは削除できない(): void
    {
        $this->actingUser();
        $genre = Genre::create(['name' => '使用中ジャンル']);
        $this->bookInGenre($genre);

        $this->delete("/genres/{$genre->id}")
            ->assertSessionHas('error', '書籍が紐づいているジャンルは削除できません。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
