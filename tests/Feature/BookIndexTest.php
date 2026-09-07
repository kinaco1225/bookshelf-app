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

    public function test_書籍はidの昇順に並ぶ(): void
    {
        $this->createBook(['title' => 'さきに登録した本']);
        $this->createBook(['title' => 'あとに登録した本']);

        $this->get('/')->assertSeeInOrder(['さきに登録した本', 'あとに登録した本']);
    }

    public function test_1ページに10件までしか表示されない(): void
    {
        collect(range(1, 12))->each(
            fn (int $i) => $this->createBook(['title' => sprintf('本%02d', $i)])
        );

        $response = $this->get('/')->assertOk();

        // id 昇順なので id が大きい「本12」は2ページ目に回り、1ページ目には出ない
        $response->assertDontSee('本12');
        $response->assertSee('page=2');
    }
}
