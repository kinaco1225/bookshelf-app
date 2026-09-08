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
| 基本フェーズは認証なし。応用フェーズで書き込み系（POST/PUT/DELETE）に
| Sanctum によるトークン認証を追加する予定。
| ルート名は web 側の books.* と衝突しないよう api.v1. を前置する。
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::apiResource('books', BookController::class);
});
