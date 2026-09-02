<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * レビューへの「いいね」を投入する。
     *
     * - 各レビューに0〜3人（レビュー投稿者本人を除く）
     * - 重複を避けるため syncWithoutDetaching を使用する
     */
    public function run(): void
    {
        $userIds = User::orderBy('id')->pluck('id')->all();

        foreach (Review::orderBy('id')->get() as $review) {
            $likeCount = $review->id % 4; // 0〜3

            if ($likeCount === 0) {
                continue;
            }

            // 投稿者本人を除いたユーザーを、レビューIDで回転させて選ぶ
            $pool = array_values(array_diff($userIds, [$review->user_id]));
            $offset = $review->id % count($pool);
            $rotated = array_merge(
                array_slice($pool, $offset),
                array_slice($pool, 0, $offset),
            );

            $review->likedByUsers()->syncWithoutDetaching(
                array_slice($rotated, 0, $likeCount)
            );
        }
    }
}
