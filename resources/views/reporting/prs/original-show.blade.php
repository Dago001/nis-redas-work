@extends($layout)

@section('title', 'PRS Original Report Inspection')

@section('content')
<main class="redas-content">
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">Original Report RET-{{ str_pad((string) $application->id, 5, '0', STR_PAD_LEFT) }}</h1>
        <p class="page-subtitle">Category: {{ ucfirst((string) $application->category) }} | Scope: {{ $application->scope_code }} | Period: {{ $application->report_period }} | Status: {{ ucfirst((string) $application->status) }} | Version v{{ max(1, (int) $application->current_version) }}</p>
    </div>
    <a href="{{ route('prs.dashboard', ['period' => $application->report_period]) }}" class="btn-nis btn-ghost">Back to PRS Dashboard</a>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">Version History ({{ $application->versions->count() }})</div></div>
    <div class="card-body">
        <ul>
            @foreach($application->versions as $ver)
                <li><strong>v{{ $ver->version_no }}</strong> ({{ $ver->reason }}) - {{ $ver->created_at?->toDateTimeString() }} - SHA-256: <code>{{ substr($ver->data_hash, 0, 16) }}...</code></li>
            @endforeach
        </ul>
    </div>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">Referenced by Consolidated Reports</div></div>
    <div class="card-body">
        @forelse($application->consolidatedSources as $src)
            @if($src->revision?->consolidatedReport)
                <p>
                    <a href="{{ route('prs.consolidated.show', $src->revision->consolidatedReport) }}">
                        {{ $src->revision->consolidatedReport->reference }} (Rev {{ $src->revision->revision_no }})
                    </a>
                    - {{ $src->included ? 'Included' : 'Excluded: '.$src->exclusion_reason }}
                </p>
            @endif
        @empty
            <p style="color:var(--gray-500);">Not yet referenced by any consolidated report.</p>
        @endforelse
    </div>
</div>
</main>
@endsection
