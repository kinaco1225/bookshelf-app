<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
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

    /**
     * 書籍詳細を表示する。
     *
     * 書籍情報・ジャンル・レビュー（投稿者といいねユーザー付き）を
     * まとめて Eager Load し、レビューは新しい順に並べる。
     */
    public function show(Book $book): View
    {
        $book->load([
            'user',
            'genres',
            'reviews' => fn ($query) => $query->latest()->latest('id'),
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍登録フォームを表示する。
     */
    public function create(): View
    {
        return view('books.create', [
            'genres' => Genre::orderBy('id')->get(),
        ]);
    }

    /**
     * 書籍を登録する。
     */
    public function store(BookRequest $request): RedirectResponse
    {
        $book = $request->user()->books()->create(
            $request->safe()->except('genres')
        );
        $book->genres()->sync($request->validated('genres'));

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }
}
