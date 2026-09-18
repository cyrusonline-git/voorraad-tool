@extends('layouts.app')
@section('titel', 'Reserveringen')
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1">
        <h1><i class="bi bi-calendar-check me-2 text-boels"></i>Reserveringen (quotes) {!! $upload->actueel ? '<span class="badge bg-success align-middle">actueel</span>' : '<span class="badge bg-secondary align-middle">oud</span>' !!}</h1>
        <p>{{ $upload->bestandsnaam }} · {{ number_format($upload->aantal_rijen, 0, ',', '.') }} regels · {{ $upload->referentie }} · ingelezen {{ $upload->created_at->format('d-m-Y H:i') }} door {{ $upload->gebruiker_naam ?: '—' }}</p>
    </div>
    <a href="{{ route('uploads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Uploads</a>
</div>
@if($upload->meldingen)<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-2"></i>{!! implode('<br>', array_map('e', $upload->meldingen)) !!}</div>@endif
<div class="row g-4">
    <div class="col-lg-3">
        <div class="card"><div class="card-header">Per depot <span class="small text-muted">(binnen {{ $horizon }} dgn / totaal)</span></div>
            <ul class="list-group list-group-flush small">
                @foreach($depots as $d)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('uploads.toon', [$upload, 'depot' => $d->depot_nummer]) }}" class="text-decoration-none">{{ $d->depot_nummer ?: '?' }} {{ $d->depot_naam }}</a><span><span class="badge bg-danger">{{ $d->binnen }}</span> <span class="badge bg-secondary">{{ $d->n }}</span></span></li>@endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <span>Regels</span>
                <form method="get" class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="contract, subgroep, depot...">
                    <input type="hidden" name="depot" value="{{ request('depot') }}">
                    <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="horizon" value="1" id="hz" {{ request()->boolean('horizon') ? 'checked' : '' }}><label class="form-check-label small" for="hz">alleen binnen {{ $horizon }} dagen</label></div>
                    <button class="btn btn-sm btn-outline-boels"><i class="bi bi-funnel"></i></button>
                    @if(request()->hasAny(['q', 'depot', 'horizon']))<a href="{{ route('uploads.toon', $upload) }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif
                </form>
            </div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Start</th><th>Contract</th><th>Depot</th><th>Subgroep</th><th>Omschrijving</th><th class="text-end">Aantal</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($regels as $r)
                    <tr class="{{ $r->startdatum && $r->startdatum->lte(now()->addDays($horizon)) ? 'table-warning' : '' }}"><td class="small">{{ $r->startdatum?->format('d-m-Y') }}</td><td>{{ $r->contract_nr }}</td><td>{{ $r->depot_nummer }} {{ $r->depot_naam }}</td><td class="fw-semibold">{{ $r->subgroep_nr }}</td><td class="small">{{ $r->omschrijving }}</td><td class="text-end">{{ rtrim(rtrim(number_format($r->aantal, 2, ',', '.'), '0'), ',') }}</td><td class="small text-muted">{{ $r->status_raw }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Geen reserveringen gevonden.</td></tr>
                @endforelse
                </tbody></table></div>
            <div class="card-body py-2">{{ $regels->links() }}</div>
        </div>
    </div>
</div>
@endsection
