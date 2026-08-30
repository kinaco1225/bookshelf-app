<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 書籍一覧（トップ）— 公開。/ と /books の両方で表示する。
Route::get('/', [BookController::class, 'index'])->name('books.index');
Route::get('/books', [BookController::class, 'index']);

// 書籍リソース（index は上で定義済みのため除外）。
// show は公開、create/store/edit/update/destroy は BookController の
// コンストラクタで auth ミドルウェアを適用している。
Route::resource('books', BookController::class)->except(['index']);

/*
|--------------------------------------------------------------------------
| プレースホルダ（後続の機能ステップで各コントローラに差し替える）
|--------------------------------------------------------------------------
| 共有レイアウトのヘッダーナビが route() 名を解決できるよう、先に名前だけ登録している。
*/
Route::get('/ranking', fn () => abort(501, 'ランキング機能は未実装です'))->name('ranking.index');
Route::get('/genres', fn () => abort(501, 'ジャンル管理は未実装です'))->name('genres.index');
Route::get('/favorites', fn () => abort(501, 'お気に入り機能は未実装です'))->name('favorites.index');
