<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
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

// 評価ランキング — 公開。
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// 書籍の登録・編集・削除 — 認証必須。
// 「/books/create」を「/books/{book}」より先に登録する必要があるためグループを前に置く。
Route::middleware('auth')->group(function () {
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    // 編集・更新・削除は登録者本人のみ。認可は各コントローラで $this->authorize() で適用（BookPolicy）。
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

// お気に入り・いいね — 認証必須。
Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
});

// レビューの編集・削除 — 認証必須。認可は各コントローラで $this->authorize() で適用（ReviewPolicy）。
Route::middleware('auth')->group(function () {
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});

// ジャンル — 認証必須（ジャンルは全ユーザー共通のマスタ。所有者制限なし）。
// 「/genres/create」を「/genres/{genre}」より先に登録する。
Route::middleware('auth')->group(function () {
    Route::get('/genres', [GenreController::class, 'index'])->name('genres.index');
    Route::get('/genres/create', [GenreController::class, 'create'])->name('genres.create');
    Route::post('/genres', [GenreController::class, 'store'])->name('genres.store');
    Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');
    Route::get('/genres/{genre}/edit', [GenreController::class, 'edit'])->name('genres.edit');
    Route::put('/genres/{genre}', [GenreController::class, 'update'])->name('genres.update');
    Route::delete('/genres/{genre}', [GenreController::class, 'destroy'])->name('genres.destroy');
});

// 読書計画 — 認証必須（自分の計画のみ）。編集・削除・完了は後続ステップで実装。
// 「/reading-plans/create」を「/reading-plans/{plan}」より先に登録する。
Route::middleware('auth')->group(function () {
    Route::get('/reading-plans/create', [ReadingPlanController::class, 'create'])->name('reading-plans.create');
    Route::post('/reading-plans', [ReadingPlanController::class, 'store'])->name('reading-plans.store');
    Route::get('/reading-plans', [ReadingPlanController::class, 'index'])->name('reading-plans.index');
});

/*
|--------------------------------------------------------------------------
| 応用フェーズ（★）— プレースホルダ
|--------------------------------------------------------------------------
| 応用版 Blade（共有レイアウトのナビ等）が route() を解決できるよう、
| 先に名前だけ登録している。実装は各機能に着手するときに差し替える。
| 「/reading-plans/create」を「/reading-plans/{plan}」より先に登録する。
*/
Route::middleware('auth')->group(function () {
    Route::get('/reports', fn () => abort(501, 'マイ読書レポートは未実装です'))->name('reports.index');

    Route::get('/reading-plans/{plan}/edit', fn () => abort(501, '読書計画編集は未実装です'))->name('reading-plans.edit');
    Route::put('/reading-plans/{plan}', fn () => abort(501, '読書計画編集は未実装です'))->name('reading-plans.update');
    Route::delete('/reading-plans/{plan}', fn () => abort(501, '読書計画削除は未実装です'))->name('reading-plans.destroy');
    Route::post('/reading-plans/{plan}/complete', fn () => abort(501, '読書計画の完了処理は未実装です'))->name('reading-plans.complete');

    Route::get('/notifications', fn () => abort(501, '通知一覧は未実装です'))->name('notifications.index');
    Route::post('/notifications/{id}/read', fn () => abort(501, '通知の既読処理は未実装です'))->name('notifications.read');
});
