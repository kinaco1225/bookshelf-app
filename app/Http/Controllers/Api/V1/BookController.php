<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookIndexRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookController extends Controller
{
    /**
     * 書籍一覧を取得する。
     *
     * GET /api/v1/books
     *
     * クエリ: keyword（title/author 部分一致）, genre_id（ジャンル絞り込み）,
     *        page, per_page（デフォルト20・最大100）
     * 各書籍にジャンル・平均評価・レビュー件数を含める。
     */
    public function index(BookIndexRequest $request): AnonymousResourceCollection
    {
        $books = Book::query()
            ->with('genres')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = $request->input('keyword');
                $query->where(function ($q) use ($keyword): void {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('genre_id'), function ($query) use ($request): void {
                $query->whereHas('genres', fn ($q) => $q->whereKey($request->integer('genre_id')));
            })
            ->orderBy('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * 書籍を新規登録する。
     *
     * POST /api/v1/books
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * 書籍詳細を取得する。
     *
     * GET /api/v1/books/{book}
     *
     * ジャンルと、レビュー（投稿者名・評価・コメント・投稿日時）を含める。
     * 存在しないIDは 404 の JSON エラー（Handler で統一）。
     */
    public function show(Book $book): BookResource
    {
        $book->load([
            'genres',
            'reviews' => fn ($query) => $query->latest()->latest('id'),
            'reviews.user',
        ])
            ->loadCount('reviews')
            ->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    /**
     * 書籍を更新する。
     *
     * PUT/PATCH /api/v1/books/{book}
     */
    public function update(Request $request, Book $book)
    {
        //
    }

    /**
     * 書籍を削除する。
     *
     * DELETE /api/v1/books/{book}
     */
    public function destroy(Book $book)
    {
        //
    }
}
