<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 公開API では「見つからない」を統一した JSON エラーで返す
        // （ルートモデルバインド失敗・未定義ルートの両方が対象）。
        // 公開API のリソースは書籍のみのため、文言は書籍向けにしている。
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => '書籍が見つかりませんでした',
                ], 404);
            }
        });

        // 公開API の認可エラー（BookPolicy で所有者以外の更新・削除を拒否）も JSON エラーで返す。
        $this->renderable(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'この書籍を操作する権限がありません',
                ], 403);
            }
        });
    }
}
