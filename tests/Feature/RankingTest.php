<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    private int $isbnSeq = 0;

    private function createBook(string $title): Book
    {
        $this->isbnSeq++;

        return Book::create([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'author' => '著者',
            'isbn' => str_pad((string) (9780000000000 + $this->isbnSeq), 13, '0'),
            'published_date' => '2020-01-01',
        ]);
    }

    /**
     * @param  array<int, int>  $ratings
     */
    private function reviewBook(Book $book, array $ratings): void
    {
        foreach ($ratings as $rating) {
            Review::create([
                'user_id' => User::factory()->create()->id,
                'book_id' => $book->id,
                'rating' => $rating,
                'comment' => 'テストコメント',
            ]);
        }
    }

    public function test_ゲストもランキングを閲覧できる(): void
    {
        $this->get('/ranking')->assertOk();
    }

    public function test_レビューが無いときはメッセージを表示する(): void
    {
        $this->createBook('レビュー無し本');

        $this->get('/ranking')
            ->assertOk()
            ->assertSee('まだレビューが投稿された書籍がありません。')
            ->assertDontSee('レビュー無し本');
    }

    public function test_平均評価の高い順に表示される(): void
    {
        $low = $this->createBook('低評価本');
        $this->reviewBook($low, [2, 3]); // 平均 2.5

        $high = $this->createBook('高評価本');
        $this->reviewBook($high, [5, 4]); // 平均 4.5

        $mid = $this->createBook('中評価本');
        $this->reviewBook($mid, [3, 4]); // 平均 3.5

        $this->get('/ranking')
            ->assertSeeInOrder(['高評価本', '中評価本', '低評価本']);
    }

    public function test_レビューが無い書籍はランキングに含まれない(): void
    {
        $reviewed = $this->createBook('レビューあり本');
        $this->reviewBook($reviewed, [4]);

        $this->createBook('レビューなし本');

        $this->get('/ranking')
            ->assertSee('レビューあり本')
            ->assertDontSee('レビューなし本');
    }

    public function test_平均評価は小数点第2位までの四捨五入で表示される(): void
    {
        $book = $this->createBook('割り切れない評価の本');
        $this->reviewBook($book, [5, 5, 4]); // 平均 14/3 = 4.6666... → 4.67

        $this->get('/ranking')->assertSee('4.67');
    }

    public function test_上位10冊までしか表示されない(): void
    {
        // 11冊すべて平均5.0。同点は登録順（id 昇順）で並ぶため、
        // 11冊目（ランク本11）だけが圏外になる。
        foreach (range(1, 11) as $i) {
            $this->reviewBook($this->createBook(sprintf('ランク本%02d', $i)), [5]);
        }

        $response = $this->get('/ranking')->assertOk();

        $response->assertSee('ランク本01');
        $response->assertSee('ランク本10');
        $response->assertDontSee('ランク本11');
    }
}
