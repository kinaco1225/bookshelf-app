<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\ReadingPlanStoreRequest;
use App\Http\Requests\ReadingPlanUpdateRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\RedirectResponse;
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

    /**
     * 読書計画作成フォームを表示する。
     */
    public function create(): View
    {
        return view('reading-plans.create', [
            'books' => Book::orderBy('title')->get(['id', 'title', 'author']),
        ]);
    }

    /**
     * 読書計画を作成する。状態は常に「進行中」で始まる。
     */
    public function store(ReadingPlanStoreRequest $request): RedirectResponse
    {
        $request->user()->readingPlans()->create([
            'book_id' => $request->validated('book_id'),
            'target_date' => $request->validated('target_date'),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を作成しました');
    }

    /**
     * 読書計画編集フォームを表示する。完了済みは編集不可（ReadingPlanPolicy）。
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->load('book');

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画を更新する（期日のみ変更可）。
     */
    public function update(ReadingPlanUpdateRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update($request->validated());

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました');
    }

    /**
     * 読書計画を削除する（状態は問わない）。
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました');
    }

    /**
     * 読書計画を「読了」にする。完了済みは対象外（ReadingPlanPolicy の update と同じ制限）。
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now()->toDateString(),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を「完了」にしました');
    }
}
