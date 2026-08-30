<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct()
    {
        // 一覧・詳細は公開。それ以外は認証必須。
        $this->middleware('auth')->except(['index', 'show']);
    }

    /**
     * 書籍一覧（トップページ）を表示する。
     *
     * 全書籍を登録日の新しい順に 10 件ずつページネーションし、
     * 各書籍にジャンルと平均評価を付与する。
     */
    public function index(): View
    {
        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->latest()       // created_at の新しい順
            ->latest('id')   // 同時刻登録時の並びを一意にする
            ->paginate(10);

        return view('books.index', compact('books'));
    }
}
