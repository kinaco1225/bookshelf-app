<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * お気に入りデータを投入する（各ユーザー3〜5冊）。
     *
     * 重複を避けるため syncWithoutDetaching を使用する。
     */
    public function run(): void
    {
        $bookIds = Book::orderBy('id')->pluck('id')->all();
        $users = User::orderBy('id')->get();

        // 各ユーザーのお気に入り冊数（3〜5）
        $counts = [4, 5, 3, 4, 5];

        foreach ($users as $index => $user) {
            $favorites = [];
            for ($j = 0; $j < $counts[$index]; $j++) {
                $favorites[] = $bookIds[($index * 2 + $j) % count($bookIds)];
            }

            $user->favoriteBooks()->syncWithoutDetaching(array_unique($favorites));
        }
    }
}
