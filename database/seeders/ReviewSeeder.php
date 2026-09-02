<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * レビューを合計32件投入する。
     *
     * - 各書籍に2〜4件（登録者本人を除く他ユーザーから）
     * - rating は 3〜5
     * - 採点者がいつ実行しても同じになるよう固定の割り当てにしている
     */
    public function run(): void
    {
        $userIds = User::orderBy('id')->pluck('id')->all();
        $books = Book::orderBy('id')->get();

        // 各書籍のレビュー件数（合計 32）
        $counts = [3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 2];

        $comments = [
            3 => ['まずまずの内容でした。', '可もなく不可もなく。', '期待したほどではありませんが読む価値はあります。'],
            4 => ['とても参考になりました。', '読みやすくおすすめできます。', '期待どおりの良書でした。'],
            5 => ['素晴らしい一冊。何度も読み返したいです。', '読んでよかったと心から思える名著。', '文句なしにおすすめします。'],
        ];

        foreach ($books as $index => $book) {
            // 登録者本人はレビューしない
            $pool = array_values(array_diff($userIds, [$book->user_id]));

            for ($j = 0; $j < $counts[$index]; $j++) {
                $rating = 3 + (($index + $j) % 3);

                Review::create([
                    'user_id' => $pool[($index + $j) % count($pool)],
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => $comments[$rating][$j % 3],
                ]);
            }
        }
    }
}
