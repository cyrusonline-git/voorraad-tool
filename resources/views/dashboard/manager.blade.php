@extends('layouts.app')
@section('titel', 'Manager')
@section('inhoud')
@include('dashboard._kop', ['icoon' => 'bar-chart-line', 'titel' => 'Manager', 'tekst' => 'Heeft elk depot voldoende voorraad, en hoeveel staat er op service of reparatie'])
<div class="row g-3 mb-4">
    @include('dashboard._tegel', ['icoon' => 'check2-circle', 'waarde' => $aantalOk, 'label' => 'depots halen het minimum', 'link' => route('voorraad.depots'), 'kleur' => '#198754'])
    @include('dashboard._tegel', ['icoon' => 'exclamation-triangle', 'waarde' => $aantalTekort, 'label' => 'depots met tekort', 'link' => route('voorraad.depots'), 'kleur' => $aantalTekort ? '#dc3545' : '#6c757d', 'klasse' => $aantalTekort ? 'text-danger' : ''])
    @include('dashboard._tegel', ['icoon' => 'sliders2', 'waarde' => $aantalNietIngesteld, 'label' => 'depots zonder minimale voorraad', 'link' => route('voorraad.minimaal'), 'kleur' => '#6c757d'])
    @include('dashboard._tegel', ['icoon' => 'hourglass-split', 'waarde' => number_format($totaalService, 0, ',', '.').' / '.number_format($totaalRepair, 0, ',', '.'), 'label' => 'In Service / In Repair (alle depots)', 'link' => route('voorraad.depots'), 'kleur' => '#ffc107'])
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">Depots met tekort <a href="{{ route('voorraad.depots') }}" class="btn btn-sm btn-outline-boels ms-auto">Alle depots</a></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Depot</th><th>Area</th><th class="text-end">Subgroepen tekort</th><th class="text-end">Stuks tekort</th><th class="text-end">Service</th><th class="text-end">Repair</th><th></th></tr></thead>
                <tbody>
                @forelse($depotsTekort as $r)
                    <tr class="table-warning"><td class="fw-semibold">{{ $r['nummer'] }} — {{ $r['depot']->naam }}</td><td class="small">{{ $r['depot']->area }}</td><td class="text-end text-danger fw-bold">{{ $r['tekorten'] }}</td><td class="text-end">{{ $r['tekort_totaal'] }}</td><td class="text-end">{{ $r['in_service'] }}</td><td class="text-end">{{ $r['in_repair'] }}</td>
                        <td><a href="{{ route('voorraad.werkplaats', ['depot' => $r['nummer']]) }}" class="btn btn-sm btn-outline-boels" title="Nakijklijst"><i class="bi bi-wrench-adjustable"></i></a></td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Geen depots met tekort{{ $aantalNietIngesteld ? ' (van '.$aantalNietIngesteld.' depots is nog geen minimum ingesteld)' : '' }}.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
        <div class="card">
            <div class="card-header">Per area</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Area</th><th class="text-end">Depots</th><th class="text-end">OK</th><th class="text-end">Tekort</th><th class="text-end">Niet ingesteld</th><th class="text-end">Available</th><th class="text-end">Service</th><th class="text-end">Repair</th></tr></thead>
                <tbody>
                @foreach($areas as $naam => $a)
                    <tr><td><a href="{{ route('voorraad.depots', ['area' => $naam]) }}">{{ $naam }}</a></td><td class="text-end">{{ $a['depots'] }}</td><td class="text-end text-success">{{ $a['ok'] }}</td><td class="text-end {{ $a['tekort'] ? 'text-danger fw-bold' : '' }}">{{ $a['tekort'] }}</td><td class="text-end text-muted">{{ $a['niet_ingesteld'] }}</td><td class="text-end">{{ number_format($a['available'], 0, ',', '.') }}</td><td class="text-end">{{ number_format($a['in_service'], 0, ',', '.') }}</td><td class="text-end">{{ number_format($a['in_repair'], 0, ',', '.') }}</td></tr>
                @endforeach
                </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">Laatste aanvragen tussen depots</div>
            <ul class="list-group list-group-flush small">
                @forelse($aanvragen as $a)
                    <li class="list-group-item d-flex justify-content-between align-items-center"><a href="{{ route('aanvragen.toon', $a) }}" class="text-decoration-none">{{ $a->eigen_depot_naam ?: $a->aanvrager_naam }} → {{ $a->depot_naam }} · {{ $a->aantal_machines }} stuks</a><span class="text-muted">{{ $a->created_at->format('d-m') }}</span></li>
                @empty
                    <li class="list-group-item text-muted">Nog geen aanvragen.</li>
                @endforelse
            </ul>
            <div class="card-body py-2"><a href="{{ route('aanvragen.index') }}" class="small">Alle aanvragen</a></div>
        </div>
        <div class="d-grid gap-2">
            <a href="{{ route('voorraad.depots') }}" class="btn btn-boels"><i class="bi bi-bar-chart-line me-1"></i>Voorraad per depot</a>
            <a href="{{ route('voorraad.minimaal') }}" class="btn btn-outline-boels"><i class="bi bi-sliders2 me-1"></i>Minimale voorraad instellen</a>
        </div>
    </div>
</div>
@endsection
