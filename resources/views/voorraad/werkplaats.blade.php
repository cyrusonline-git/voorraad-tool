@extends('layouts.app')
@section('titel', 'Werkplaats nakijklijst')
@php($kleur = ['available' => 'bg-success', 'in_service' => 'bg-warning text-dark', 'in_repair' => 'bg-danger'])
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1"><h1><i class="bi bi-wrench-adjustable me-2 text-boels"></i>Werkplaats — nakijklijst{{ $depot ? ' '.$depot->naam : '' }}</h1>
        <p>Per subgroep: staat er minder Available dan het ingestelde minimum, dan zie je welke machines In Service / In Repair je kunt nakijken om weer op voorraad te komen.@if($materieel) Materieellijst van {{ $materieel->created_at->format('d-m-Y H:i') }}.@endif</p></div>
    <a href="{{ route('voorraad.minimaal', ['depot' => $depot?->depot_nummer]) }}" class="btn btn-outline-boels btn-sm"><i class="bi bi-sliders2 me-1"></i>Minimale voorraad instellen</a>
</div>
@if(!$materieel)<div class="alert alert-warning">Er is nog geen materieellijst ingelezen (Uploads).</div>@endif
@section('extra-filters')
    <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="tekort" value="1" id="tekort" {{ request()->boolean('tekort', true) ? 'checked' : '' }}><label class="form-check-label small" for="tekort">Alleen tekorten</label><input type="hidden" name="tekort" value="{{ request()->boolean('tekort', true) ? 1 : 0 }}" disabled></div>
@endsection
@include('voorraad._depotkeuze')
@if($data)
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:{{ $data['tekorten'] ? '#dc3545' : '#198754' }}"><i class="bi bi-exclamation-triangle"></i></div><div><div class="kpi-value {{ $data['tekorten'] ? 'text-danger' : 'text-success' }}">{{ $data['tekorten'] }}</div><div class="kpi-label">subgroepen onder minimum ({{ $data['ingesteld'] }} ingesteld)</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-tools"></i></div><div><div class="kpi-value">{{ $data['na_te_kijken'] }}</div><div class="kpi-label">machines nakijken (van {{ $data['tekort_totaal'] }} tekort)</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#ffc107;color:#333"><i class="bi bi-hourglass-split"></i></div><div><div class="kpi-value">{{ $data['status']['in_service'] }} <span class="fs-6 text-muted">/ {{ $data['status']['in_repair'] }}</span></div><div class="kpi-label">In Service / In Repair op dit depot</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#198754"><i class="bi bi-check2-circle"></i></div><div><div class="kpi-value">{{ $data['status']['available'] }} <span class="fs-6 text-muted">/ {{ $data['status']['totaal'] }}</span></div><div class="kpi-label">Available / totaal op dit depot</div></div></div></div></div>
</div>
@if($data['ingesteld'] === 0)<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Voor dit depot is nog geen minimale voorraad ingesteld. Stel die in via "Minimale voorraad instellen".</div>@endif
<div class="card mb-4">
    <div class="card-header">Nakijklijst per subgroep</div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Subgroep</th><th>Omschrijving</th><th class="text-end">Minimum</th><th class="text-end">Available</th><th class="text-end">Tekort</th><th class="text-end">Service / Repair</th><th>Nakijken (In Service eerst)</th></tr></thead>
        <tbody>
        @forelse($data['rijen'] as $r)
            <tr class="{{ $r['tekort'] > 0 ? ($r['haalbaar'] < $r['tekort'] ? 'table-danger' : 'table-warning') : '' }}">
                <td class="fw-semibold">{{ $r['subgroep_nr'] }}</td><td class="small">{{ $r['naam'] }}</td>
                <td class="text-end">{{ $r['minimum'] }}</td><td class="text-end">{{ $r['available'] }}</td>
                <td class="text-end fw-bold {{ $r['tekort'] ? 'text-danger' : 'text-success' }}">{{ $r['tekort'] }}</td>
                <td class="text-end small">{{ $r['in_service'] }} / {{ $r['in_repair'] }}</td>
                <td class="small">
                    @foreach($r['na_te_kijken'] as $m)<span class="badge {{ $kleur[$m->status_code] }} me-1" title="{{ $m->status_raw }} · {{ trim(($m->extra['merk'] ?? '').' '.($m->extra['model'] ?? '')) }}">{{ $m->uniek_nr }}</span>@endforeach
                    @if($r['tekort'] > 0 && $r['haalbaar'] < $r['tekort'])<span class="text-danger">nog {{ $r['tekort'] - $r['haalbaar'] }} te kort, ook na nakijken — elders aanvragen</span>@endif
                    @if($r['tekort'] > 0 && $r['kandidaten']->count() > $r['tekort'])<span class="text-muted">(+{{ $r['kandidaten']->count() - $r['tekort'] }} andere)</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">{{ request()->boolean('tekort', true) ? 'Geen tekorten: alle ingestelde minima worden gehaald.' : 'Geen subgroepen gevonden.' }}</td></tr>
        @endforelse
        </tbody></table></div>
</div>
<div class="card">
    <div class="card-header">Aankomende orders voor dit depot (Niet toegekend, ingangsdatum binnen {{ $horizon }} dagen) <span class="badge bg-secondary">{{ count($orders) }}</span></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Ingang</th><th>Subgroep</th><th>Omschrijving</th><th>Orders</th><th class="text-end">Nodig</th><th class="text-end">Available</th><th class="text-end">Service / Repair</th><th class="text-end">Tekort</th></tr></thead>
        <tbody>
        @forelse($orders as $o)
            <tr class="{{ $o['tekort'] > 0 ? 'table-warning' : '' }}"><td class="small">{{ $o['eerste_datum']?->format('d-m-Y') }}</td><td class="fw-semibold">{{ $o['subgroep_nr'] }}</td><td class="small">{{ $o['omschrijving'] }}</td><td class="small">{{ implode(', ', $o['orders']) }}</td>
                <td class="text-end">{{ $o['nodig'] }}</td><td class="text-end">{{ $o['available'] }}</td><td class="text-end small">{{ $o['in_service'] }} / {{ $o['in_repair'] }}</td><td class="text-end fw-bold {{ $o['tekort'] ? 'text-danger' : 'text-success' }}">{{ $o['tekort'] }}</td></tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-3">Geen aankomende orders met status "Niet toegekend" voor dit depot in de geüploade contracten/projecten.</td></tr>
        @endforelse
        </tbody></table></div>
    <div class="card-body small text-muted">Het depot van een order wordt bepaald uit de vestiging (contract, kolom P) of de eerste drie cijfers van het contractnummer. De horizon stel je in bij Beheer → Instellingen.</div>
</div>
@endif
@endsection
