@extends('layouts.app')
@section('titel', 'Minimale voorraad')
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1"><h1><i class="bi bi-sliders2 me-2 text-boels"></i>Minimale voorraad{{ $depot ? ' — '.$depot->naam : '' }}</h1>
        <p>Per subgroep het aantal machines dat minimaal <em>Available</em> op dit depot moet staan. 0 = geen minimum. {{ $aantalIngesteld }} subgroepen ingesteld.</p></div>
    <a href="{{ route('voorraad.werkplaats', ['depot' => $depot?->depot_nummer]) }}" class="btn btn-outline-boels btn-sm"><i class="bi bi-wrench-adjustable me-1"></i>Nakijklijst</a>
</div>
@section('extra-filters')
    <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="ingesteld" value="1" id="ingesteld" {{ request()->boolean('ingesteld') ? 'checked' : '' }}><label class="form-check-label small" for="ingesteld">Alleen met minimum</label></div>
@endsection
@include('voorraad._depotkeuze')
@if($depot)
<form method="post" action="{{ route('voorraad.minimaal.opslaan') }}">
@csrf
<input type="hidden" name="depot" value="{{ $depot->depot_nummer }}">
<input type="hidden" name="q" value="{{ request('q') }}"><input type="hidden" name="ingesteld" value="{{ request()->boolean('ingesteld') ? 1 : 0 }}">
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">Subgroepen op {{ $depot->naam }} <span class="badge bg-secondary">{{ $rijen->count() }}</span>
        @if($magBewerken)<button class="btn btn-boels btn-sm ms-auto"><i class="bi bi-save me-1"></i>Opslaan</button>@endif</div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Subgroep</th><th>Omschrijving</th><th class="text-end">Op depot</th><th class="text-end">Available</th><th style="width:130px">Minimum</th></tr></thead>
        <tbody>
        @forelse($rijen as $r)
            <tr class="{{ $r['minimum'] > 0 && $r['available'] < $r['minimum'] ? 'table-warning' : '' }}">
                <td class="fw-semibold">{{ $r['subgroep_nr'] }}</td><td class="small">{{ $r['naam'] }}</td>
                <td class="text-end">{{ $r['totaal'] }}</td><td class="text-end">{{ $r['available'] }}</td>
                <td>@if($magBewerken)<input type="number" min="0" class="form-control form-control-sm" name="minimum[{{ $r['subgroep_nr'] }}]" value="{{ $r['minimum'] ?: '' }}" placeholder="0">@else{{ $r['minimum'] }}@endif</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Geen subgroepen gevonden (is de materieellijst ingelezen?).</td></tr>
        @endforelse
        </tbody></table></div>
    @if($magBewerken)
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div><label class="form-label small mb-1">Subgroep toevoegen die nu niet op het depot staat</label><input type="text" name="nieuw_subgroep" class="form-control form-control-sm" placeholder="subgroepnummer"></div>
        <div><label class="form-label small mb-1">Minimum</label><input type="number" min="0" name="nieuw_minimum" class="form-control form-control-sm" style="width:100px"></div>
        <button class="btn btn-boels btn-sm"><i class="bi bi-save me-1"></i>Opslaan</button>
    </div>
    @endif
</div>
</form>
@if(in_array(actieve_rol(), ['manager', 'fleet', 'admin']))
<form method="post" action="{{ route('voorraad.minimaal.kopieer') }}" class="mt-3 d-flex flex-wrap align-items-end gap-2 small" onsubmit="return confirm('Minima overnemen? Bestaande instellingen op dit depot blijven staan.')">
    @csrf<input type="hidden" name="depot" value="{{ $depot->depot_nummer }}">
    <div><label class="form-label mb-1">Minima overnemen van depot</label><select name="van" class="form-select form-select-sm">@foreach($depots as $d)@if($d->depot_nummer !== $depot->depot_nummer)<option value="{{ $d->depot_nummer }}">{{ $d->depot_nummer }} — {{ $d->naam }}</option>@endif @endforeach</select></div>
    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-copy me-1"></i>Overnemen (alleen lege)</button>
</form>
@endif
@endif
@endsection
