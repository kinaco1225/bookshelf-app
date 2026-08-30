<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookIndexTest extends TestCase
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

    public function test_ゲストは書籍一覧トップにアクセスできる(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_booksパスはトップページへリダイレクトする(): void
    {
        $this->get('/books')->assertRedirect('/');
    }

    public function test_登録済みの書籍が一覧に表示される(): void
    {
        $this->createBook(['title' => '一覧に出る本']);

        $this->get('/')->assertOk()->assertSee('一覧に出る本');
    }

    public function test_書籍は登録日の新しい順に並ぶ(): void
    {
        $old = $this->createBook(['title' => 'ふるい本']);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $this->createBook(['title' => 'あたらしい本']);

        $this->get('/')->assertSeeInOrder(['あたらしい本', 'ふるい本']);
    }

    public function test_1ページに10件までしか表示されない(): void
    {
        collect(range(1, 12))->each(
            fn (int $i) => $this->createBook(['title' => sprintf('本%02d', $i)])
        );

        $response = $this->get('/')->assertOk();

        // 最古の「本01」は2ページ目に回るため1ページ目には出ない
        $response->assertDontSee('本01');
        $response->assertSee('page=2');
    }
}
