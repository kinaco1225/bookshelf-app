<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| 認証条件（auth ミドルウェア）はコントローラではなくこのファイルで指定する。
*/

// 書籍一覧（トップ）— 公開。正規の URL は「/」。
Route::get('/', [BookController::class, 'index'])->name('books.index');
// 「/books」は正規 URL「/」へリダイレクト（要件の「/ または /books」に対応）。
Route::redirect('/books', '/');

// 書籍の登録・編集・削除 — 認証必須。
// 「/books/create」を「/books/{book}」より先に登録する必要があるためグループを前に置く。
Route::middleware('auth')->group(function () {
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    // 編集・更新・削除は登録者本人のみ（BookPolicy）。
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit')->can('update', 'book');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update')->can('update', 'book');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy')->can('delete', 'book');
});

// 書籍詳細 — 公開。{book} のワイルドカードは最後に登録する。
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// レビュー投稿 — 認証必須。
Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])
    ->middleware('auth')
    ->name('reviews.store');

// お気に入り・いいね — 認証必須。
Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
});

// レビューの編集・削除 — 認証必須。投稿者本人のみ（ReviewPolicy）。
Route::middleware('auth')->group(function () {
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit')->can('update', 'review');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update')->can('update', 'review');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy')->can('delete', 'review');
});

/*
|--------------------------------------------------------------------------
| プレースホルダ（後続の機能ステップで各コントローラに差し替える）
|--------------------------------------------------------------------------
| 共有レイアウトのヘッダーナビが route() 名を解決できるよう、先に名前だけ登録している。
*/
Route::get('/ranking', fn () => abort(501, 'ランキング機能は未実装です'))->name('ranking.index');
Route::get('/genres', fn () => abort(501, 'ジャンル管理は未実装です'))->name('genres.index');
