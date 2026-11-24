@extends('desk-admin.layout')

@section('content')
<main class="redas-content">
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">Revise {{ $report->reference }}</h1>
        <p class="page-subtitle">Saving creates Revision {{ $report->current_revision_no + 1 }} while preserving all previous revisions.</p>
    </div>
    <a href="{{ route('consolidated.show', $report) }}" class="btn-nis btn-ghost">Back</a>
</div>

@if($errors->any())
<div role="alert" style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
    <ul>@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('consolidated.update', $report) }}">
    @csrf
    @method('PUT')

    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head"><div class="card-head-title">Revision Details</div></div>
        <div class="card-body">
            <div class="fg" style="margin-bottom:14px;">
                <label for="changeNote">Revision Change Note</label>
                <input id="changeNote" type="text" name="change_note" class="ni" value="{{ old('change_note', $report->is_stale ? 'Updated to latest source report versions' : '') }}" placeholder="Reason for this revision">
            </div>
            <div class="fg">
                <label for="revNarrative">Executive Narrative</label>
                <textarea id="revNarrative" name="narrative" class="ni" rows="4">{{ old('narrative', $latest?->narrative) }}</textarea>
            </div>
        </div>
    </div>

    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head"><div class="card-head-title">Source Original Reports (Latest Versions)</div></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="nis-table">
                    <thead>
                        <tr><th>Include</th><th>Ref</th><th>Unit</th><th>Status</th><th>Latest Version</th><th>Exclusion Reason</th></tr>
                    </thead>
                    <tbody>
                        @foreach($candidates as $app)
                        @php $isChecked = in_array($app->id, old('include_ids', $includedIds), true); @endphp
                        <tr>
                            <td><input type="checkbox" name="include_ids[]" value="{{ $app->id }}" @checked($isChecked)></td>
                            <td>RET-{{ str_pad((string) $app->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $app->section_code ?: $app->scope_code }}</td>
                            <td>{{ ucfirst($app->status) }}</td>
                            <td>v{{ max(1, (int) $app->current_version) }}</td>
                            <td><input type="text" name="exclusions[{{ $app->id }}]" class="ni" value="{{ old('exclusions.'.$app->id, $exclusions[$app->id] ?? '') }}" placeholder="Required if excluded"></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;">
        <a href="{{ route('consolidated.show', $report) }}" class="btn-nis btn-ghost">Cancel</a>
        <button type="submit" class="btn-nis btn-primary-nis">Save Revision {{ $report->current_revision_no + 1 }}</button>
    </div>
</form>
</main>
@endsection
