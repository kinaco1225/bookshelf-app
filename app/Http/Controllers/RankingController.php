<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * レビュー平均評価の高い順に上位10冊を表示する。
     *
     * レビューが1件も無い書籍は対象外。
     */
    public function index(): View
    {
        $rankedBooks = Book::query()
            ->whereHas('reviews')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count') // 平均が同点ならレビュー件数が多い順
            ->orderBy('id')                // それでも同点なら登録順
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
