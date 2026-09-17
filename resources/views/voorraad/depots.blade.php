@extends('layouts.app')
@section('titel', 'Alle depots')
@php($statusNaam = \App\Models\Materieel::STATUSSEN)
@section('inhoud')
<div class="page-header mb-3"><h1><i class="bi bi-bar-chart-line me-2 text-boels"></i>Voorraad per depot</h1><p>Haalt elk depot de minimale voorraad, en hoeveel staat er op service of reparatie.@if($materieel) Materieellijst van {{ $materieel->created_at->format('d-m-Y H:i') }}.@endif</p></div>
<form method="get" class="card mb-3"><div class="card-body d-flex flex-wrap align-items-end gap-3">
    <div><label class="form-label small mb-1 fw-semibold">Area</label><select name="area" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Alle areas</option>@foreach($areas as $a)<option value="{{ $a }}" {{ $area === $a ? 'selected' : '' }}>{{ $a }}</option>@endforeach</select></div>
    <div><label class="form-label small mb-1 fw-semibold">Zoeken</label><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="depot"></div>
    <button class="btn btn-sm btn-outline-boels"><i class="bi bi-funnel me-1"></i>Toepassen</button>
</div></form>
<div class="row g-3 mb-4">
    @foreach(['available' => '#198754', 'in_service' => '#ffc107', 'in_repair' => '#dc3545', 'on_hire' => '#6c757d'] as $code => $kl)
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:{{ $kl }};{{ $code === 'in_service' ? 'color:#333' : '' }}"><i class="bi bi-box-seam"></i></div><div><div class="kpi-value">{{ number_format($fleetStatus[$code] ?? 0, 0, ',', '.') }}</div><div class="kpi-label">{{ $statusNaam[$code][0] }} (hele vloot)</div></div></div></div></div>
    @endforeach
</div>
<div class="card mb-4"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Depot</th><th>Area</th><th>Minimale voorraad</th><th class="text-end">Onder minimum</th><th class="text-end">Tekort</th><th class="text-end">Available</th><th class="text-end">In Service</th><th class="text-end">In Repair</th><th class="text-end">On Hire</th><th class="text-end">Totaal</th><th></th></tr></thead>
    <tbody>
    @forelse($rijen as $r)
        <tr class="{{ $r['tekorten'] ? 'table-warning' : '' }}">
            <td class="fw-semibold">{{ $r['nummer'] }} — {{ $r['depot']->naam }}</td><td class="small">{{ $r['depot']->area }}</td>
            <td>@if($r['ingesteld'] === 0)<span class="badge bg-light text-dark border">niet ingesteld</span>@elseif($r['ok'])<span class="badge bg-success">gehaald</span>@else<span class="badge bg-danger">tekort</span>@endif <span class="small text-muted">{{ $r['ingesteld'] }} subgroepen</span></td>
            <td class="text-end {{ $r['tekorten'] ? 'text-danger fw-bold' : '' }}">{{ $r['tekorten'] }}</td><td class="text-end">{{ $r['tekort_totaal'] }}</td>
            <td class="text-end">{{ $r['available'] }}</td><td class="text-end">{{ $r['in_service'] }}</td><td class="text-end">{{ $r['in_repair'] }}</td><td class="text-end">{{ $r['on_hire'] }}</td><td class="text-end">{{ $r['totaal'] }}</td>
            <td class="text-nowrap"><a href="{{ route('voorraad.werkplaats', ['depot' => $r['nummer']]) }}" class="btn btn-sm btn-outline-boels" title="Nakijklijst"><i class="bi bi-wrench-adjustable"></i></a> <a href="{{ route('voorraad.minimaal', ['depot' => $r['nummer']]) }}" class="btn btn-sm btn-outline-secondary" title="Minimale voorraad"><i class="bi bi-sliders2"></i></a></td>
        </tr>
    @empty
        <tr><td colspan="11" class="text-center text-muted py-4">Geen depots met een depotnummer. Koppel de depotnummers bij Beheer → Depots.</td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="card"><div class="card-header">Vloot per area (uit de materieellijst)</div><div class="card-body">
    <div class="d-flex flex-wrap gap-3">@foreach($fleetArea as $a => $n)<div><span class="badge bg-secondary">{{ number_format($n, 0, ',', '.') }}</span> {{ $a ?: 'onbekend' }}</div>@endforeach</div>
    @if($materieel)<a href="{{ route('uploads.toon', $materieel) }}" class="btn btn-sm btn-outline-boels mt-3"><i class="bi bi-search me-1"></i>Volledige materieellijst doorzoeken</a>@endif
</div></div>
@endsection
