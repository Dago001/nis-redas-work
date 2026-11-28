@php
    $trackerUser = auth()->user();
    $trackerCategory = $trackerUser ? \App\Services\SubmissionWorkflow::categoryForUser($trackerUser) : 'state';
    $trackerList = collect($submissions ?? [])->take(8)->values();

    $stageDefinitions = match ($trackerCategory) {
        \App\Services\SubmissionWorkflow::CATEGORY_CGIS => [
            ['key' => 'submitted', 'label' => 'Submitted by Officer', 'sub' => 'Return filed'],
            ['key' => 'cgis_desk_review', 'label' => 'CGIS Unit Desk Admin', 'sub' => 'Unit head review'],
            ['key' => 'hq_review', 'label' => 'HQ Admin Approval', 'sub' => 'Final HQ sign-off'],
            ['key' => 'approved', 'label' => 'Approved & Archived', 'sub' => 'Ready for PRS collation'],
        ],
        \App\Services\SubmissionWorkflow::CATEGORY_DIRECTORATE => [
            ['key' => 'submitted', 'label' => 'Submitted by Officer', 'sub' => 'Return filed'],
            ['key' => 'directorate_review', 'label' => 'Directorate Admin', 'sub' => 'Directorate review'],
            ['key' => 'hq_review', 'label' => 'HQ Admin Approval', 'sub' => 'Final HQ sign-off'],
            ['key' => 'approved', 'label' => 'Approved & Archived', 'sub' => 'Ready for PRS collation'],
        ],
        \App\Services\SubmissionWorkflow::CATEGORY_ZONE => [
            ['key' => 'submitted', 'label' => 'Submitted by Officer', 'sub' => 'Return filed'],
            ['key' => 'zonal_review', 'label' => 'Zonal Admin', 'sub' => 'Zonal review'],
            ['key' => 'hq_review', 'label' => 'HQ Admin Approval', 'sub' => 'Final HQ sign-off'],
            ['key' => 'approved', 'label' => 'Approved & Archived', 'sub' => 'Ready for PRS collation'],
        ],
        default => [
            ['key' => 'submitted', 'label' => 'Submitted by Officer', 'sub' => 'Return filed'],
            ['key' => 'desk_review', 'label' => 'Formation Desk Admin', 'sub' => 'Command desk review'],
            ['key' => 'zonal_review', 'label' => 'Zonal Admin', 'sub' => 'Zonal command review'],
            ['key' => 'hq_review', 'label' => 'HQ Admin Approval', 'sub' => 'Final HQ sign-off'],
            ['key' => 'approved', 'label' => 'Approved & Archived', 'sub' => 'Ready for PRS collation'],
        ],
    };

    $stageKeys = array_column($stageDefinitions, 'key');
@endphp

<div class="redas-card animate-fade-up delay-3" id="returnWorkflowTrackerCard">
    <div class="card-head">
        <div class="card-head-title">
            <div class="card-head-icon" style="background:var(--nis-50);color:var(--nis-600);">
                <i class="fas fa-route"></i>
            </div>
            Return Workflow
        </div>
        @if($trackerList->count() > 1)
            <select id="workflowTrackerSelect" style="font-size:.74rem;padding:4px 8px;border:1px solid var(--gray-200);border-radius:8px;background:#fff;color:var(--gray-700);max-width:145px;">
                @foreach($trackerList as $idx => $item)
                    @php
                        $itemPeriod = $item->report_period ?: ($item->return_data['report_period'] ?? $item->return_data['period'] ?? $item->created_at?->format('Y-m') ?? ('#' . $item->id));
                    @endphp
                    <option value="{{ $idx }}">{{ $itemPeriod }} (RET-{{ str_pad((string) $item->id, 5, '0', STR_PAD_LEFT) }})</option>
                @endforeach
            </select>
        @elseif($trackerList->count() === 1)
            @php $onlyItem = $trackerList->first(); @endphp
            <span style="font-size:.72rem;font-weight:700;color:var(--nis-700);background:var(--nis-50);padding:3px 8px;border-radius:999px;">
                RET-{{ str_pad((string) $onlyItem->id, 5, '0', STR_PAD_LEFT) }}
            </span>
        @endif
    </div>

    <div class="card-body" style="padding:16px 18px;">
        @if($trackerList->isEmpty())
            <p style="font-size:.8rem;color:var(--gray-500);margin:0 0 12px;">
                Submit a return to track its live approval progress across each stage:
            </p>
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach($stageDefinitions as $sIdx => $stage)
                    <div style="display:flex;align-items:flex-start;gap:10px;position:relative;">
                        <div style="width:22px;height:22px;border-radius:50%;background:var(--gray-100);color:var(--gray-500);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;flex-shrink:0;">
                            {{ $sIdx + 1 }}
                        </div>
                        <div style="line-height:1.3;">
                            <div style="font-size:.8rem;font-weight:600;color:var(--gray-700);">{{ $stage['label'] }}</div>
                            <div style="font-size:.72rem;color:var(--gray-400);">{{ $stage['sub'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            @foreach($trackerList as $idx => $item)
                @php
                    $itemStatus = strtolower((string) $item->status);
                    $rawStage = strtolower((string) $item->workflow_stage);
                    if ($rawStage === 'admin_review') {
                        $rawStage = 'hq_review';
                    }
                    $isApproved = $itemStatus === 'approved' || $rawStage === 'approved';
                    $isReturned = in_array($itemStatus, ['returned', 'rejected', 'queried'], true);
                    $currentIdx = $isApproved ? (count($stageKeys) - 1) : array_search($rawStage, $stageKeys, true);
                    if ($currentIdx === false) {
                        $currentIdx = 1;
                    }

                    $pathEntries = is_array($item->workflow_path) ? $item->workflow_path : [];
                    $stageTimes = [];
                    $latestReason = null;
                    foreach ($pathEntries as $entry) {
                        $st = strtolower((string) ($entry['stage'] ?? ''));
                        if ($st === 'admin_review') {
                            $st = 'hq_review';
                        }
                        if (! empty($entry['at'])) {
                            try {
                                $stageTimes[$st] = \Carbon\Carbon::parse($entry['at'])->format('d M Y, H:i');
                            } catch (\Throwable) {
                                $stageTimes[$st] = (string) $entry['at'];
                            }
                        }
                        if (in_array(strtolower((string) ($entry['action'] ?? '')), ['rejected', 'returned'], true) && ! empty($entry['note'])) {
                            $latestReason = (string) $entry['note'];
                        }
                    }
                    if (! isset($stageTimes['submitted']) && $item->created_at) {
                        $stageTimes['submitted'] = $item->created_at->format('d M Y, H:i');
                    }
                @endphp

                <div class="workflow-tracker-panel" data-panel-idx="{{ $idx }}" style="{{ $idx === 0 ? '' : 'display:none;' }}">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
                        <div>
                            <div style="font-size:.76rem;color:var(--gray-500);">Period</div>
                            <div style="font-size:.86rem;font-weight:700;color:var(--gray-800);">
                                {{ $item->report_period ?: ($item->return_data['report_period'] ?? $item->return_data['period'] ?? $item->created_at?->format('M Y') ?? '-') }}
                            </div>
                        </div>
                        <span class="status-badge {{ $isApproved ? 'badge-approved' : ($isReturned ? 'badge-rejected' : 'badge-pending') }}">
                            {{ $isApproved ? 'Approved' : ($isReturned ? 'Returned' : 'In Review') }}
                        </span>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:0;">
                        @foreach($stageDefinitions as $sIdx => $stage)
                            @php
                                $sKey = $stage['key'];
                                $isLast = $sIdx === count($stageDefinitions) - 1;
                                if ($isApproved) {
                                    $state = 'done';
                                } elseif ($sIdx < $currentIdx) {
                                    $state = 'done';
                                } elseif ($sIdx === $currentIdx) {
                                    $state = $isReturned ? 'rejected' : 'active';
                                } else {
                                    $state = 'upcoming';
                                }

                                $dotStyle = match ($state) {
                                    'done' => 'background:#16a34a;color:#fff;border:2px solid #16a34a;',
                                    'active' => 'background:#fef3c7;color:#d97706;border:2px solid #f59e0b;',
                                    'rejected' => 'background:#fee2e2;color:#dc2626;border:2px solid #ef4444;',
                                    default => 'background:#f8fafc;color:#94a3b8;border:2px solid #cbd5e1;',
                                };
                                $lineColor = $state === 'done' ? '#16a34a' : '#e2e8f0';
                                $statusHint = match ($state) {
                                    'done' => $stageTimes[$sKey] ?? 'Completed',
                                    'active' => 'Awaiting review',
                                    'rejected' => 'Returned for correction',
                                    default => 'Pending',
                                };
                            @endphp
                            <div style="display:flex;gap:12px;">
                                <div style="display:flex;flex-direction:column;align-items:center;width:24px;flex-shrink:0;">
                                    <div style="width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.66rem;font-weight:700;{{ $dotStyle }}">
                                        @if($state === 'done')
                                            <i class="fas fa-check"></i>
                                        @elseif($state === 'rejected')
                                            <i class="fas fa-exclamation"></i>
                                        @elseif($state === 'active')
                                            <i class="fas fa-clock"></i>
                                        @else
                                            {{ $sIdx + 1 }}
                                        @endif
                                    </div>
                                    @unless($isLast)
                                        <div style="width:2px;flex:1;min-height:18px;background:{{ $lineColor }};margin:2px 0;"></div>
                                    @endunless
                                </div>
                                <div style="padding-bottom:{{ $isLast ? '0' : '12px' }};line-height:1.3;">
                                    <div style="font-size:.82rem;font-weight:{{ in_array($state, ['active', 'rejected'], true) ? '700' : '600' }};color:{{ $state === 'upcoming' ? 'var(--gray-400)' : 'var(--gray-800)' }};">
                                        {{ $stage['label'] }}
                                    </div>
                                    <div style="font-size:.72rem;color:{{ $state === 'rejected' ? '#dc2626' : ($state === 'active' ? '#b45309' : 'var(--gray-500)') }};">
                                        {{ $statusHint }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($isReturned && $latestReason)
                        <div style="margin-top:12px;padding:8px 10px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;font-size:.75rem;color:#991b1b;">
                            <strong>Reviewer Note:</strong> {{ $latestReason }}
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>

@if($trackerList->count() > 1)
<script>
(function () {
    var sel = document.getElementById('workflowTrackerSelect');
    if (!sel) return;
    sel.addEventListener('change', function () {
        var idx = sel.value;
        document.querySelectorAll('#returnWorkflowTrackerCard .workflow-tracker-panel').forEach(function (p) {
            p.style.display = p.dataset.panelIdx === idx ? '' : 'none';
        });
    });
})();
</script>
@endif
