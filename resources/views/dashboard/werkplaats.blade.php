@extends('layouts.app')
@section('titel', 'Werkplaats')
@php($badge = ['available' => 'bg-success', 'in_service' => 'bg-warning text-dark', 'in_repair' => 'bg-danger'])
@section('inhoud')
@include('dashboard._kop', ['icoon' => 'wrench-adjustable', 'titel' => 'Werkplaats'.($depot ? ' — '.$depot->naam : ''), 'tekst' => 'Wat nakijken om de minimale voorraad op het depot te halen en aankomende orders te kunnen leveren'])
@if(!$depot)
    <div class="alert alert-warning">Er is nog geen depot met een depotnummer. Koppel de nummers in Boels CORE (Infrastructuur).</div>
@else
<div class="row g-3 mb-4">
    @include('dashboard._tegel', ['icoon' => 'exclamation-triangle', 'waarde' => $data['tekorten'], 'label' => 'subgroepen onder het minimum ('.$data['ingesteld'].' ingesteld)', 'link' => route('voorraad.werkplaats', ['depot' => $depot->depot_nummer]), 'kleur' => $data['tekorten'] ? '#dc3545' : '#198754', 'klasse' => $data['tekorten'] ? 'text-danger' : 'text-success'])
    @include('dashboard._tegel', ['icoon' => 'tools', 'waarde' => $data['na_te_kijken'], 'label' => 'machines nakijken voor het minimum', 'link' => route('voorraad.werkplaats', ['depot' => $depot->depot_nummer])])
    @include('dashboard._tegel', ['icoon' => 'calendar-event', 'waarde' => count($orders), 'label' => 'subgroepen nakijken voor orders (< '.$horizon.' dagen)', 'link' => route('voorraad.werkplaats', ['depot' => $depot->depot_nummer]).'#orders', 'kleur' => count($orders) ? '#ffc107' : '#6c757d'])
    @include('dashboard._tegel', ['icoon' => 'hourglass-split', 'waarde' => $data['status']['in_service'].' / '.$data['status']['in_repair'], 'label' => 'In Service / In Repair op '.$depot->naam, 'link' => $materieel ? route('uploads.toon', [$materieel, 'q' => $depot->depot_nummer, 'status' => 'in_service']) : '#', 'kleur' => '#6c757d'])
</div>
@if($data['ingesteld'] === 0)
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Voor {{ $depot->naam }} is nog geen minimale voorraad ingesteld. <a href="{{ route('voorraad.minimaal', ['depot' => $depot->depot_nummer]) }}">Stel die nu in</a>, dan verschijnt hier de nakijklijst.</div>
@endif
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">Grootste tekorten t.o.v. minimum <a href="{{ route('voorraad.werkplaats', ['depot' => $depot->depot_nummer]) }}" class="btn btn-sm btn-outline-boels ms-auto">Volledige nakijklijst</a></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Subgroep</th><th>Omschrijving</th><th class="text-end">Min.</th><th class="text-end">Avail.</th><th class="text-end">Tekort</th><th>Nakijken</th></tr></thead>
                <tbody>
                @forelse($topRijen as $r)
                    <tr class="{{ $r['haalbaar'] < $r['tekort'] ? 'table-danger' : 'table-warning' }}"><td class="fw-semibold">{{ $r['subgroep_nr'] }}</td><td class="small">{{ $r['naam'] }}</td><td class="text-end">{{ $r['minimum'] }}</td><td class="text-end">{{ $r['available'] }}</td><td class="text-end fw-bold text-danger">{{ $r['tekort'] }}</td>
                        <td class="small">@foreach($r['na_te_kijken'] as $m)<span class="badge {{ $badge[$m->status_code] }} me-1">{{ $m->uniek_nr }}</span>@endforeach</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Geen tekorten: alle ingestelde minima worden gehaald.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">Nakijken voor aankomende orders</div>
            <ul class="list-group list-group-flush small">
                @forelse($orders as $o)
                    <li class="list-group-item"><strong>{{ $o['subgroep_nr'] }}</strong> {{ $o['omschrijving'] }} · ingang {{ $o['eerste_datum']?->format('d-m') }} · tekort <span class="text-danger fw-bold">{{ $o['tekort'] }}</span><br>
                        @foreach($o['na_te_kijken'] as $m)<span class="badge {{ $badge[$m->status_code] }} me-1">{{ $m->uniek_nr }}</span>@endforeach</li>
                @empty
                    <li class="list-group-item text-muted">Niets na te kijken voor orders binnen {{ $horizon }} dagen.</li>
                @endforelse
            </ul>
        </div>
        <div class="d-grid gap-2">
            <a href="{{ route('voorraad.minimaal', ['depot' => $depot->depot_nummer]) }}" class="btn btn-outline-boels"><i class="bi bi-sliders2 me-1"></i>Minimale voorraad instellen</a>
            <a href="{{ route('uploads.index') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i>Materieellijst uploaden</a>
        </div>
    </div>
</div>
@endif
@endsection
