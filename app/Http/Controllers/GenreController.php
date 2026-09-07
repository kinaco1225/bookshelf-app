<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * ジャンル一覧を表示する（各ジャンルの書籍数付き）。
     */
    public function index(): View
    {
        $genres = Genre::query()
            ->withCount('books')
            ->orderBy('id')
            ->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンル詳細（このジャンルに紐づく書籍一覧）を表示する。
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->with('genres')
            ->latest()
            ->latest('id')
            ->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * ジャンル登録フォームを表示する。
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルを登録する。
     */
    public function store(GenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを作成しました。');
    }

    /**
     * ジャンル編集フォームを表示する。
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンルを更新する。
     */
    public function update(GenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンルを削除する。書籍が紐づいている場合は削除しない。
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->exists()) {
            return back()->with('error', '書籍が紐づいているジャンルは削除できません。');
        }

        $genre->delete();

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
