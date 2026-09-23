<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
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

    public function test_デフォルトでは新しい順（idの降順）に並ぶ(): void
    {
        $this->createBook(['title' => 'さきに登録した本']);
        $this->createBook(['title' => 'あとに登録した本']);

        $this->get('/')->assertSeeInOrder(['あとに登録した本', 'さきに登録した本']);
    }

    public function test_1ページに10件までしか表示されない(): void
    {
        collect(range(1, 12))->each(
            fn (int $i) => $this->createBook(['title' => sprintf('本%02d', $i)])
        );

        $response = $this->get('/')->assertOk();

        // 新しい順（id降順）なので、最初に登録した「本01」が2ページ目に回り、1ページ目には出ない
        $response->assertDontSee('本01');
        $response->assertSee('page=2');
    }

    public function test_キーワードでタイトルまたは著者を部分一致検索できる(): void
    {
        $this->createBook(['title' => '吾輩は猫である', 'author' => '夏目漱石']);
        $this->createBook(['title' => '坊っちゃん', 'author' => '夏目漱石']);
        $this->createBook(['title' => '人を動かす', 'author' => 'カーネギー']);

        $response = $this->get('/?keyword=夏目');

        $response->assertSee('吾輩は猫である');
        $response->assertSee('坊っちゃん');
        $response->assertDontSee('人を動かす');
    }

    public function test_ジャンルで絞り込める(): void
    {
        $novel = Genre::create(['name' => '小説']);
        $business = Genre::create(['name' => 'ビジネス']);

        $book1 = $this->createBook(['title' => '小説の本']);
        $book1->genres()->sync([$novel->id]);

        $book2 = $this->createBook(['title' => 'ビジネスの本']);
        $book2->genres()->sync([$business->id]);

        $response = $this->get('/?genre='.$novel->id);

        $response->assertSee('小説の本');
        $response->assertDontSee('ビジネスの本');
    }

    public function test_sort_oldestで登録が古い順に並ぶ(): void
    {
        $this->createBook(['title' => 'さきに登録した本']);
        $this->createBook(['title' => 'あとに登録した本']);

        $this->get('/?sort=oldest')->assertSeeInOrder(['さきに登録した本', 'あとに登録した本']);
    }

    public function test_sort_titleでタイトル昇順に並ぶ(): void
    {
        $this->createBook(['title' => 'ば行の本']);
        $this->createBook(['title' => 'あ行の本']);

        $this->get('/?sort=title')->assertSeeInOrder(['あ行の本', 'ば行の本']);
    }

    public function test_sort_ratingで評価が高い順に並びレビューがない書籍は最後(): void
    {
        $noReview = $this->createBook(['title' => 'レビュー無しの本']);
        $lowRated = $this->createBook(['title' => '評価が低い本']);
        $highRated = $this->createBook(['title' => '評価が高い本']);

        Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $lowRated->id,
            'rating' => 2,
            'comment' => 'まあまあ',
        ]);
        Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $highRated->id,
            'rating' => 5,
            'comment' => '最高でした',
        ]);

        $this->get('/?sort=rating')->assertSeeInOrder([
            '評価が高い本', '評価が低い本', 'レビュー無しの本',
        ]);
    }

    public function test_ページネーションリンクは検索条件を維持する(): void
    {
        collect(range(1, 12))->each(
            fn (int $i) => $this->createBook(['title' => sprintf('検索対象%02d', $i)])
        );

        $response = $this->get('/?keyword=検索対象&sort=oldest');

        $response->assertSee('page=2');
        $response->assertSee('keyword=');
        $response->assertSee('sort=oldest');
    }
}
