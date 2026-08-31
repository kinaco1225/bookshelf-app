<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * ログインユーザーのお気に入り書籍一覧を表示する。
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->orderByPivot('created_at', 'desc')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * 書籍のお気に入り登録状態をトグルする（追加⇔解除）。
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $result = $request->user()->favoriteBooks()->toggle($book->id);

        $message = filled($result['attached'])
            ? 'お気に入りに追加しました。'
            : 'お気に入りから外しました。';

        return back()->with('success', $message);
    }
}
