<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * 書籍にレビューを投稿する。
     */
    public function store(ReviewRequest $request, Book $book): RedirectResponse
    {
        $request->user()->reviews()->create([
            ...$request->validated(),
            'book_id' => $book->id,
        ]);

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを投稿しました');
    }

    /**
     * レビュー編集フォームを表示する。
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    /**
     * レビューを更新する。
     */
    public function update(ReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('success', 'レビューを更新しました');
    }

    /**
     * レビューを削除する（関連いいねは FK のカスケードで削除）。
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $bookId = $review->book_id;
        $review->delete();

        return redirect()
            ->route('books.show', $bookId)
            ->with('success', 'レビューを削除しました');
    }
}
