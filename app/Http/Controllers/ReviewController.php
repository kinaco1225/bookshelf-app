<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use Illuminate\Http\RedirectResponse;

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
            ->with('success', 'レビューを投稿しました。');
    }
}
