<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ReadingReportService $reportService) {}

    /**
     * ログインユーザー自身のレビューをもとにした読書レポートを表示する。
     */
    public function index(Request $request): View
    {
        return view('reports.index', [
            'stats' => $this->reportService->build($request->user()),
        ]);
    }
}
