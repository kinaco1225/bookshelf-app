<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * 読書計画データを6件投入する。
     *
     * 採点者がいつ実行しても同じ挙動になるよう、now() 起点で動的に target_date を設定する。
     * 主要シナリオ（1〜5）は山田太郎に集約し、6件目は鈴木花子（他ユーザー認可テスト用）。
     * book_id は同一ユーザー×in_progress の重複制御に抵触しないよう、計画ごとに異なる書籍を割り当てる。
     */
    public function run(): void
    {
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();
        $books = Book::orderBy('id')->take(6)->get();

        $plans = [
            // 1. 3日前リマインダー対象
            [
                'user_id' => $yamada->id,
                'book_id' => $books[0]->id,
                'target_date' => now()->addDays(3),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 2. 当日リマインダー対象
            [
                'user_id' => $yamada->id,
                'book_id' => $books[1]->id,
                'target_date' => now(),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 3. バッチで自動失効 + 3日後再エンゲージメント対象（二重シナリオ）
            [
                'user_id' => $yamada->id,
                'book_id' => $books[2]->id,
                'target_date' => now()->subDays(3),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 4. リマインダー対象外
            [
                'user_id' => $yamada->id,
                'book_id' => $books[3]->id,
                'target_date' => now()->addDays(7),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 5. 完了済み
            [
                'user_id' => $yamada->id,
                'book_id' => $books[4]->id,
                'target_date' => now()->subDays(10),
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => now()->subDays(5),
            ],
            // 6. 他ユーザー認可テスト用（鈴木花子、山田太郎ログイン中に /reading-plans/{id}/edit 直打ちで403確認）
            [
                'user_id' => $suzuki->id,
                'book_id' => $books[5]->id,
                'target_date' => now()->addDays(5),
                'status' => ReadingPlanStatus::InProgress,
            ],
        ];

        foreach ($plans as $plan) {
            ReadingPlan::create($plan);
        }
    }
}
