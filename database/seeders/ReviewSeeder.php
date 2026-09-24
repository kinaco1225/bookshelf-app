<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * レビューを書籍ごとに2〜4件投入する（登録者本人を除くユーザーからランダムに選出）。
     *
     * - rating は 1〜5 の全範囲（マイ読書レポートの評価分布グラフが意味のある分布になるように）
     * - comment は評価ごとの日本語テンプレートからランダムに選ぶ
     */
    public function run(): void
    {
        $users = User::all();

        $comments = [
            1 => ['残念ながら合いませんでした。', '期待と違いました。'],
            2 => ['少し期待外れでした。', '内容が薄い印象。', 'もう少し深掘りしてほしかった。'],
            3 => ['普通でした。', '可もなく不可もなく。', '期待したほどではなかった。'],
            4 => ['とても参考になりました。', '読みやすくておすすめです。', '期待通りの内容でした。'],
            5 => ['素晴らしい本でした！', '人生が変わりました。', '何度も読み返しています。'],
        ];

        Book::all()->each(function (Book $book) use ($users, $comments) {
            $users
                ->reject(fn (User $user) => $user->id === $book->user_id)
                ->random(rand(2, 4))
                ->each(function (User $reviewer) use ($book, $comments) {
                    $rating = rand(1, 5);

                    Review::create([
                        'user_id' => $reviewer->id,
                        'book_id' => $book->id,
                        'rating' => $rating,
                        'comment' => collect($comments[$rating])->random(),
                    ]);
                });
        });
    }
}
