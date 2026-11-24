@extends('desk-admin.layout')

@section('content')
<main class="redas-content">
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">Prepare {{ ucfirst($level) }} Consolidated Report ({{ strtoupper($scope) }})</h1>
        <p class="page-subtitle">Period: {{ $period }}. Original source reports remain preserved and independently accessible.</p>
    </div>
    <a href="{{ route('consolidated.index', ['period' => $period]) }}" class="btn-nis btn-ghost">Back</a>
</div>

@if($errors->any())
<div role="alert" style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
    <strong>Please fix the following errors:</strong>
    <ul>@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('consolidated.store') }}">
    @csrf
    <input type="hidden" name="report_period" value="{{ $period }}">

    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head"><div class="card-head-title">Report Details</div></div>
        <div class="card-body">
            <div class="fg" style="margin-bottom:14px;">
                <label for="conTitle">Consolidated Report Title</label>
                <input id="conTitle" type="text" name="title" class="ni" value="{{ old('title', ucfirst($level).' Consolidated Report — '.strtoupper($scope).' ('.$period.')') }}">
            </div>
            <div class="fg">
                <label for="conNarrative">Executive Narrative &amp; Synthesis</label>
                <textarea id="conNarrative" name="narrative" class="ni" rows="4" placeholder="Summarize operational highlights, trends, and reporting gaps...">{{ old('narrative') }}</textarea>
            </div>
        </div>
    </div>

    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head"><div class="card-head-title">Source Original Reports ({{ $candidates->count() }})</div></div>
        <div class="card-body">
            @if($candidates->isEmpty())
                <p style="color:var(--gray-500);">No original reports found for {{ $period }}.</p>
            @else
                <div class="table-responsive">
                    <table class="nis-table">
                        <thead>
                            <tr><th>Include</th><th>Ref</th><th>Unit</th><th>Officer</th><th>Status</th><th>Version</th><th>Exclusion Reason (if unchecked)</th></tr>
                        </thead>
                        <tbody>
                            @foreach($candidates as $app)
                            <tr>
                                <td><input type="checkbox" name="include_ids[]" value="{{ $app->id }}" checked aria-label="Include RET-{{ $app->id }}"></td>
                                <td><a href="{{ route('consolidated.original', $app) }}" target="_blank">RET-{{ str_pad((string) $app->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                <td>{{ $app->section_code ?: $app->scope_code }}</td>
                                <td>{{ $app->user?->name ?? 'N/A' }}</td>
                                <td>{{ ucfirst($app->status) }}</td>
                                <td>v{{ max(1, (int) $app->current_version) }}</td>
                                <td><input type="text" name="exclusions[{{ $app->id }}]" class="ni" value="{{ old('exclusions.'.$app->id) }}" placeholder="Required only if excluded"></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if(!empty($comparison['indicators']))
    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head"><div class="card-head-title">Cross-Unit Indicator Comparison</div></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="nis-table">
                    <thead>
                        <tr>
                            <th>Indicator</th>
                            @foreach($comparison['rows'] as $row)
                                <th>{{ $row['application']->section_code ?: $row['application']->scope_code }} (#{{ $row['application']->id }})</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($comparison['indicators'] as $code => $name)
                        <tr>
                            <td>{{ $name }}</td>
                            @foreach($comparison['rows'] as $row)
                                <td>{{ $row['values'][$code] ?? '—' }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div style="display:flex;justify-content:flex-end;gap:10px;">
        <a href="{{ route('consolidated.index', ['period' => $period]) }}" class="btn-nis btn-ghost">Cancel</a>
        <button type="submit" class="btn-nis btn-primary-nis">Save Consolidated Draft</button>
    </div>
</form>
</main>
@endsection
