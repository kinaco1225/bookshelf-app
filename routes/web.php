<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
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
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});

// 書籍詳細 — 公開。{book} のワイルドカードは最後に登録する。
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// レビュー投稿 — 認証必須。
Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])
    ->middleware('auth')
    ->name('reviews.store');

// お気に入り — 認証必須。
Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
});

/*
|--------------------------------------------------------------------------
| プレースホルダ（後続の機能ステップで各コントローラに差し替える）
|--------------------------------------------------------------------------
| 共有レイアウトのヘッダーナビが route() 名を解決できるよう、先に名前だけ登録している。
*/
Route::get('/ranking', fn () => abort(501, 'ランキング機能は未実装です'))->name('ranking.index');
Route::get('/genres', fn () => abort(501, 'ジャンル管理は未実装です'))->name('genres.index');

// いいね・レビュー編集（書籍詳細画面が route() を解決できるようにするための仮登録）
Route::post('/reviews/{review}/like', fn () => abort(501, 'いいね機能は未実装です'))->name('reviews.like');
Route::get('/reviews/{review}/edit', fn () => abort(501, 'レビュー機能は未実装です'))->name('reviews.edit');
Route::put('/reviews/{review}', fn () => abort(501, 'レビュー機能は未実装です'))->name('reviews.update');
Route::delete('/reviews/{review}', fn () => abort(501, 'レビュー機能は未実装です'))->name('reviews.destroy');
