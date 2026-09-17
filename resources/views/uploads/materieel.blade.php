@extends('layouts.app')
@section('titel', 'Materieellijst')
@section('inhoud')
@php($codes = \App\Models\Materieel::STATUSSEN)
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1">
        <h1><i class="bi bi-boxes me-2 text-boels"></i>Materieellijst {!! $upload->actueel ? '<span class="badge bg-success align-middle">actueel</span>' : '<span class="badge bg-secondary align-middle">oud</span>' !!}</h1>
        <p>{{ $upload->bestandsnaam }} · {{ number_format($upload->aantal_rijen, 0, ',', '.') }} machines · ingelezen {{ $upload->created_at->format('d-m-Y H:i') }} door {{ $upload->gebruiker_naam ?: '—' }}</p>
    </div>
    <a href="{{ route('uploads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Uploads</a>
</div>
@if($upload->meldingen)
    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-2"></i>{!! implode('<br>', array_map('e', $upload->meldingen)) !!}</div>
@endif
<div class="row g-3 mb-3">
    @foreach($codes as $code => [$en, $nl, $volg])
        @if(($statussen[$code] ?? 0) > 0)
        <div class="col-6 col-md-2 col-lg">
            <a href="{{ route('uploads.toon', [$upload, 'status' => $code]) }}" class="text-decoration-none">
            <div class="card kpi-tile"><div class="kpi-body py-3"><div><div class="kpi-value">{{ number_format($statussen[$code], 0, ',', '.') }}</div><div class="kpi-label">{{ $en }}</div></div></div></div></a>
        </div>
        @endif
    @endforeach
</div>
<div class="row g-4">
    <div class="col-lg-3">
        <div class="card"><div class="card-header">Per depot</div>
            <ul class="list-group list-group-flush small" style="max-height: 520px; overflow: auto;">
                @foreach($depots as $d)<li class="list-group-item d-flex justify-content-between"><span>{{ $d->depot_nummer ?: '?' }} {{ $d->depot_naam }}</span><span class="badge bg-secondary">{{ $d->n }}</span></li>@endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <span>Machines</span>
                <form method="get" class="ms-auto d-flex gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Zoek machinenummer, subgroep, depot...">
                    <select name="status" class="form-select form-select-sm" style="width:auto"><option value="">Alle statussen</option>@foreach($codes as $code => [$en])<option value="{{ $code }}" {{ request('status') === $code ? 'selected' : '' }}>{{ $en }}</option>@endforeach</select>
                    <button class="btn btn-sm btn-outline-boels"><i class="bi bi-funnel"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Machinenr</th><th>Subgroep</th><th>Omschrijving</th><th>Depot</th><th>Area</th><th>Status</th><th>Laatste uit-huur</th></tr></thead>
                    <tbody>
                    @forelse($regels as $m)
                        <tr><td class="fw-semibold">{{ $m->uniek_nr }}</td><td>{{ $m->subgroep_nr }}<div class="small text-muted">{{ $m->subgroep_naam }}</div></td><td class="small">{{ $m->omschrijving }}</td>
                            <td>{{ $m->depot_nummer }} {{ $m->depot_naam }}</td><td class="small">{{ $m->area_raw }}</td>
                            <td><span class="badge {{ ['available' => 'bg-success', 'in_service' => 'bg-warning text-dark', 'in_repair' => 'bg-danger', 'on_hire' => 'bg-secondary', 'own_use' => 'bg-dark', 'in_transfer' => 'bg-info text-dark'][$m->status_code] ?? 'bg-light text-dark border' }}">{{ $m->status_raw }}</span></td>
                            <td class="small">{{ $m->laatste_uithuur?->format('d-m-Y') }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Geen machines gevonden.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body py-2">{{ $regels->links() }}</div>
        </div>
    </div>
</div>
@endsection
