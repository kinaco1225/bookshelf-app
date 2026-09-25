<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookIndexRequest;
use App\Http\Requests\Api\V1\BookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

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
     * POST /api/v1/books（Sanctum 認証必須）
     *
     * リクエストボディ: title, author, isbn（13桁・一意）,
     *   published_date, description（任意）, image_url（任意）, genres（1つ以上のジャンルID）
     * 登録者は認証済みユーザー自身になる（user_id はクライアントから指定不可）。
     * 成功時は 201 Created と Location ヘッダ、作成した書籍を返す。
     */
    public function store(BookRequest $request): JsonResponse
    {
        $book = $request->user()->books()->create($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        $book->load(['genres', 'reviews.user'])
            ->loadCount('reviews')
            ->loadAvg('reviews', 'rating');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.v1.books.show', $book));
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
     * PUT/PATCH /api/v1/books/{book}（Sanctum 認証必須、所有者本人のみ）
     *
     * リクエストボディは登録と同一（全項目）。ISBN の一意性チェックは自身を除外。
     * 存在しないIDは 404 の JSON エラー（Handler で統一）。
     */
    public function update(BookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        $book->load(['genres', 'reviews.user'])
            ->loadCount('reviews')
            ->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    /**
     * 書籍を削除する。
     *
     * DELETE /api/v1/books/{book}（Sanctum 認証必須、所有者本人のみ）
     *
     * 関連データ（レビュー・お気に入り・ジャンル紐付け）は外部キーの
     * ON DELETE CASCADE で連動削除される。
     * 成功時は 204 No Content。存在しないIDは 404 の JSON エラー（Handler で統一）。
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }
}
