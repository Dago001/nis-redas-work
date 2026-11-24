<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsolidatedReport;
use App\Services\Reporting\ConsolidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HqConsolidatedController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $level = $request->query('level');
        $period = $request->query('period');

        $reports = ConsolidatedReport::query()
            ->with(['creator:id,name', 'latestRevision'])
            ->when(is_string($status) && $status !== '', fn ($q) => $q->where('status', $status))
            ->when(is_string($level) && $level !== '', fn ($q) => $q->where('level', $level))
            ->when(is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period), fn ($q) => $q->where('report_period', $period))
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.headquarters.consolidated-index', [
            'reports' => $reports,
            'filters' => compact('status', 'level', 'period'),
        ]);
    }

    public function show(ConsolidatedReport $report): View
    {
        $report->load([
            'creator:id,name',
            'submitter:id,name',
            'approver:id,name',
            'revisions.creator:id,name',
            'revisions.sources.application.user:id,name',
            'revisions.sources.reportVersion',
        ]);

        return view('admin.headquarters.consolidated-show', [
            'report' => $report,
            'latest' => $report->revisions->first(),
        ]);
    }

    public function approve(Request $request, ConsolidatedReport $report): RedirectResponse
    {
        ConsolidationService::approve($report, $request->user());

        return redirect()
            ->route('admin.consolidated.show', $report)
            ->with('status', "Consolidated report {$report->reference} approved.");
    }

    public function returnReport(Request $request, ConsolidatedReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        ConsolidationService::returnReport($report, $request->user(), $validated['reason']);

        return redirect()
            ->route('admin.consolidated.show', $report)
            ->with('status', "Consolidated report {$report->reference} returned for revision.");
    }
}
