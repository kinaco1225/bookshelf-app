<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧を表示する（ログインユーザー自身の計画のみ）。
     *
     * GET /reading-plans?status=xxx
     *
     * 期日の近い順に並べる。status が不正・未指定の場合は絞り込みなし（全件）。
     */
    public function index(Request $request): View
    {
        $currentStatus = ReadingPlanStatus::tryFrom((string) $request->query('status'))?->value;

        $readingPlans = $request->user()
            ->readingPlans()
            ->with('book')
            ->when($currentStatus, fn ($query) => $query->where('status', $currentStatus))
            ->orderBy('target_date')
            ->get();

        return view('reading-plans.index', [
            'readingPlans' => $readingPlans,
            'currentStatus' => $currentStatus,
        ]);
    }
}
