<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| 公開API v1
|--------------------------------------------------------------------------
|
| 外部アプリケーション向けの書籍 API（JSON）。
| 読み取り系（GET 一覧/詳細）は認証不要。書き込み系（POST/PUT/DELETE）は
| Sanctum の Bearer トークン認証必須（auth:sanctum）。更新・削除の認可
| （書籍所有者のみ）は各コントローラで $this->authorize() により適用する（BookPolicy）。
| ルート名は web 側の books.* と衝突しないよう api.v1. を前置する。
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/books', [BookController::class, 'index'])->name('books.index');
    Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/books', [BookController::class, 'store'])->name('books.store');
        Route::match(['put', 'patch'], '/books/{book}', [BookController::class, 'update'])->name('books.update');
        Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    });
});
