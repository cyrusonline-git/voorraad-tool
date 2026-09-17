@extends('layouts.app')
@section('titel', 'Fleet')
@php($statusNaam = \App\Models\Materieel::STATUSSEN)
@section('inhoud')
@include('dashboard._kop', ['icoon' => 'truck', 'titel' => 'Fleet', 'tekst' => 'Totaaloverzicht: welk materieel staat waar, met welke status'])
<div class="row g-3 mb-4">
    @foreach(['available' => '#198754', 'in_service' => '#ffc107', 'in_repair' => '#dc3545', 'on_hire' => '#6c757d'] as $code => $kl)
        @include('dashboard._tegel', ['icoon' => 'box-seam', 'waarde' => number_format($fleetStatus[$code] ?? 0, 0, ',', '.'), 'label' => $statusNaam[$code][0].' (hele vloot)', 'link' => $materieel ? route('uploads.toon', [$materieel, 'status' => $code]) : '#', 'kleur' => $kl])
    @endforeach
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">Per depot <a href="{{ route('voorraad.depots') }}" class="btn btn-sm btn-outline-boels ms-auto">Details</a></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Depot</th><th class="text-end">Available</th><th class="text-end">Service</th><th class="text-end">Repair</th><th class="text-end">On Hire</th><th class="text-end">Totaal</th><th>Minimum</th></tr></thead>
                <tbody>
                @foreach($depots as $r)
                    <tr><td class="fw-semibold">{{ $r['nummer'] }} — {{ $r['depot']->naam }}</td><td class="text-end">{{ $r['available'] }}</td><td class="text-end">{{ $r['in_service'] }}</td><td class="text-end">{{ $r['in_repair'] }}</td><td class="text-end">{{ $r['on_hire'] }}</td><td class="text-end">{{ $r['totaal'] }}</td>
                        <td>@if($r['ingesteld'] === 0)<span class="badge bg-light text-dark border">niet ingesteld</span>@elseif($r['ok'])<span class="badge bg-success">gehaald</span>@else<span class="badge bg-danger">{{ $r['tekorten'] }} tekort</span>@endif</td></tr>
                @endforeach
                </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">Vloot per area</div>
            <div class="card-body"><div class="d-flex flex-wrap gap-2">@foreach($fleetArea as $a => $n)<span class="badge bg-secondary">{{ number_format($n, 0, ',', '.') }}</span> <span class="me-3 small">{{ $a ?: 'onbekend' }}</span>@endforeach</div></div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Grootste subgroepen</div>
            <ul class="list-group list-group-flush small">
                @foreach($topSubgroepen as $s)<li class="list-group-item d-flex justify-content-between"><span><strong>{{ $s->subgroep_nr }}</strong> {{ $s->naam }}</span><span class="badge bg-secondary">{{ number_format($s->n, 0, ',', '.') }}</span></li>@endforeach
            </ul>
        </div>
        <div class="d-grid gap-2">
            @if($materieel)<a href="{{ route('uploads.toon', $materieel) }}" class="btn btn-boels"><i class="bi bi-search me-1"></i>Materieellijst doorzoeken</a>@endif
            <a href="{{ route('uploads.index') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i>Nieuwe materieellijst uploaden</a>
            <a href="{{ route('aanvragen.index') }}" class="btn btn-outline-secondary"><i class="bi bi-envelope-paper me-1"></i>Aanvragen ({{ $aanvragenWeek }} deze week)</a>
        </div>
    </div>
</div>
@endsection
