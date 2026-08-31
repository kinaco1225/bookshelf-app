<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $attributes = []): Book
    {
        return Book::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ], $attributes));
    }

    public function test_ゲストはお気に入り操作ができずログインへ(): void
    {
        $book = $this->createBook();

        $this->post("/books/{$book->id}/favorites")->assertRedirect('/login');
        $this->get('/favorites')->assertRedirect('/login');
    }

    public function test_お気に入りに追加できる(): void
    {
        $book = $this->createBook();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/books/{$book->id}/favorites")
            ->assertRedirect()
            ->assertSessionHas('success', 'お気に入りに追加しました。');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_もう一度押すとお気に入りから外れる(): void
    {
        $book = $this->createBook();
        $user = User::factory()->create();
        $user->favoriteBooks()->attach($book->id);

        $this->actingAs($user)
            ->post("/books/{$book->id}/favorites")
            ->assertSessionHas('success', 'お気に入りから外しました。');

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_お気に入り一覧に自分のお気に入りだけが表示される(): void
    {
        $mine = $this->createBook(['title' => '自分のお気に入り本']);
        $others = $this->createBook(['title' => '他人のお気に入り本']);

        $user = User::factory()->create();
        $user->favoriteBooks()->attach($mine->id);
        User::factory()->create()->favoriteBooks()->attach($others->id);

        $this->actingAs($user)
            ->get('/favorites')
            ->assertOk()
            ->assertSee('自分のお気に入り本')
            ->assertDontSee('他人のお気に入り本');
    }

    public function test_お気に入り一覧は10件ずつページネーションされる(): void
    {
        $user = User::factory()->create();
        collect(range(1, 12))->each(
            fn (int $i) => $user->favoriteBooks()->attach(
                $this->createBook(['title' => sprintf('お気に入り%02d', $i)])->id
            )
        );

        $this->actingAs($user)
            ->get('/favorites')
            ->assertOk()
            ->assertSee('page=2');
    }
}
