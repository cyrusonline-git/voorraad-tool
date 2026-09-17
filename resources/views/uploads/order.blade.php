@extends('layouts.app')
@section('titel', $upload->typeNaam().' '.$upload->referentie)
@section('inhoud')
@php($codes = \App\Models\OrderRegel::STATUSSEN)
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1">
        <h1><i class="bi bi-file-earmark-text me-2 text-boels"></i>{{ $upload->typeNaam() }} {{ $upload->referentie }}</h1>
        <p>{{ $upload->bestandsnaam }} · {{ $upload->aantal_rijen }} regels · ingelezen {{ $upload->created_at->format('d-m-Y H:i') }} door {{ $upload->gebruiker_naam ?: '—' }}@if($upload->depot_nummer) · vestiging {{ $upload->depot_nummer }}@endif</p>
    </div>
    <a href="{{ route('uploads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Uploads</a>
    <a href="#" class="btn btn-boels btn-sm disabled" title="Fase 2: zoeken in de materieellijst"><i class="bi bi-search me-1"></i>Beschikbaarheid zoeken <span class="fase-badge">volgt</span></a>
</div>
@if($upload->meldingen)
    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-2"></i>{!! implode('<br>', array_map('e', $upload->meldingen)) !!}</div>
@endif
<div class="row g-3 mb-3">
    @foreach($codes as $code => $naam)
        @if(($statussen[$code] ?? 0) > 0)
        <div class="col-6 col-md-2">
            <a href="{{ route('uploads.toon', [$upload, 'status' => $code]) }}" class="text-decoration-none">
            <div class="card kpi-tile"><div class="kpi-body py-3">
                <div><div class="kpi-value {{ $code === 'not_allocated' ? 'text-boels' : '' }}">{{ $statussen[$code] }}</div><div class="kpi-label">{{ $naam }}</div></div>
            </div></div></a>
        </div>
        @endif
    @endforeach
    <div class="col-12 small text-muted"><strong>{{ $teZoeken }}</strong> regel(s) met status "Niet toegekend" moeten in de materieellijst gezocht worden; de andere statussen zijn al geregeld of niet nodig.</div>
</div>
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span>Regels</span>
        <form method="get" class="ms-auto d-flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Zoek subgroep, artikel, omschrijving...">
            <select name="status" class="form-select form-select-sm" style="width:auto">
                <option value="">Alle statussen</option>
                @foreach($codes as $code => $naam)<option value="{{ $code }}" {{ request('status') === $code ? 'selected' : '' }}>{{ $naam }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-outline-boels"><i class="bi bi-funnel"></i></button>
            @if(request()->hasAny(['q', 'status']))<a href="{{ route('uploads.toon', $upload) }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>#</th>@if($upload->type === 'project')<th>Contract</th><th>Project</th>@endif<th>Type</th><th>Subgroep</th><th>Artikelnr</th><th>Omschrijving</th><th>Status</th><th>Status (bestand)</th>@if($upload->type === 'contract')<th>Aflevering</th>@endif<th>Verhuur vanaf</th><th class="text-end">Aantal</th></tr></thead>
            <tbody>
            @forelse($regels as $r)
                <tr class="{{ $r->status_code === 'not_allocated' ? 'table-warning' : '' }}">
                    <td class="text-muted small">{{ $r->regel_nr }}</td>
                    @if($upload->type === 'project')<td>{{ $r->contract_nr }}</td><td>{{ $r->project_nr }}<div class="small text-muted">{{ $r->project_omschrijving }}</div></td>@endif
                    <td class="small text-muted">{{ $r->extra['type'] ?? '' }}</td>
                    <td class="fw-semibold">{{ $r->subgroep_nr }}</td>
                    <td>{{ $r->artikel_nr }}@if($r->artikel_nr && $r->subgroep_nr && $r->artikel_nr === $r->subgroep_nr) <span class="badge bg-light text-dark border" title="Alleen subgroep, nog geen uniek nummer">geen uniek nr</span>@endif</td>
                    <td>{{ $r->omschrijving }}</td>
                    <td><span class="badge {{ ['not_allocated' => 'bg-boels', 'allocated' => 'bg-success', 'on_hire' => 'bg-secondary', 'off_hire' => 'bg-light text-dark border', 'goods_in' => 'bg-light text-dark border', 'onbekend' => 'bg-danger'][$r->status_code] ?? 'bg-secondary' }}">{{ $codes[$r->status_code] ?? $r->status_code }}</span></td>
                    <td class="small text-muted">{{ $r->status_raw }}</td>
                    @if($upload->type === 'contract')<td class="small">{{ $r->afleverdatum?->format('d-m-Y') }}</td>@endif
                    <td class="small">{{ $r->verhuurdatum?->format('d-m-Y') }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($r->aantal, 2, ',', '.'), '0'), ',') }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-4">Geen regels gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
