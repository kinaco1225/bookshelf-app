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
     * 全書籍を id の昇順に 10 件ずつページネーションし、
     * 各書籍にジャンルと平均評価を付与する。
     */
    public function index(): View
    {
        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->orderBy('id')
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

    /**
     * 書籍編集フォームを表示する。
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');

        return view('books.edit', [
            'book' => $book,
            'genres' => Genre::orderBy('id')->get(),
        ]);
    }

    /**
     * 書籍を更新する。
     */
    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    /**
     * 書籍を削除する（関連レビュー・お気に入り・ジャンル紐付けは FK のカスケードで削除）。
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
