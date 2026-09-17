@extends('layouts.app')
@section('titel', 'Beschikbaarheid '.$upload->referentie)
@php($statusNaam = \App\Models\Materieel::STATUSSEN)
@php($kleur = ['available' => 'bg-success', 'in_service' => 'bg-warning text-dark', 'in_repair' => 'bg-danger'])
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1">
        <h1><i class="bi bi-search me-2 text-boels"></i>Beschikbaarheid — {{ $upload->typeNaam() }} {{ $upload->referentie }}</h1>
        <p>Gezocht in de materieellijst van {{ $materieel->created_at->format('d-m-Y H:i') }} ({{ number_format($materieel->aantal_rijen, 0, ',', '.') }} machines). Eerst het eigen depot, daarna per depot; volgorde Available → In Service → In Repair.</p>
    </div>
    <a href="{{ route('uploads.toon', $upload) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Regels</a>
</div>

<form method="get" class="card mb-3"><div class="card-body d-flex flex-wrap align-items-end gap-3">
    <div>
        <label class="form-label small mb-1 fw-semibold">Eigen depot (eerst hier zoeken)</label>
        <select name="depot" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— geen eigen depot —</option>
            @foreach($depots as $d)<option value="{{ $d->depot_nummer }}" {{ $eigen === $d->depot_nummer ? 'selected' : '' }}>{{ $d->depot_nummer }} — {{ $d->naam }}</option>@endforeach
        </select>
    </div>
    <div><label class="form-label small mb-1 fw-semibold">Zoeken</label><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="subgroep, omschrijving, contract"></div>
    <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="tekort" value="1" id="tekort" {{ request()->boolean('tekort') ? 'checked' : '' }}><label class="form-check-label small" for="tekort">Alleen tekorten</label></div>
    <button class="btn btn-sm btn-outline-boels"><i class="bi bi-funnel me-1"></i>Toepassen</button>
    @if(request()->hasAny(['q', 'tekort']))<a href="{{ route('beschikbaarheid', [$upload, 'depot' => $eigen]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif
</div></form>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-list-ol"></i></div><div><div class="kpi-value">{{ $aantalRegels }}</div><div class="kpi-label">regels te zoeken</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#198754"><i class="bi bi-check2-circle"></i></div><div><div class="kpi-value">{{ $totaalGevonden }} <span class="fs-6 text-muted">/ {{ $totaalNodig }}</span></div><div class="kpi-label">machines gevonden / nodig</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:{{ $tekorten ? '#dc3545' : '#6c757d' }}"><i class="bi bi-exclamation-triangle"></i></div><div><div class="kpi-value {{ $tekorten ? 'text-danger' : '' }}">{{ $tekorten }}</div><div class="kpi-label">regels met tekort</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#0d6efd"><i class="bi bi-geo-alt"></i></div><div><div class="kpi-value">{{ count($perDepot) }}</div><div class="kpi-label">depots om te halen{{ $eigenNaam ? ' · eigen: '.$eigenNaam : '' }}</div></div></div></div></div>
</div>

@if($aantalRegels === 0)
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Geen regels om te zoeken: er zijn geen regels met status "Niet toegekend". Toegekend staat al vast, in huur is al geregeld, uit-verhuur en goederen in zijn niet nodig.</div>
@endif

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDepots" type="button"><i class="bi bi-geo-alt me-1"></i>Ophaallijst per depot</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRegels" type="button"><i class="bi bi-list-check me-1"></i>Per regel</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="tabDepots">
    @forelse($perDepot as $nr => $d)
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <span><i class="bi bi-geo-alt-fill me-1 text-boels"></i>{{ $d['depot_nummer'] }} — {{ $d['depot_naam'] }}</span>
            @if($d['eigen'])<span class="badge bg-boels">eigen depot</span>@endif
            <span class="badge bg-secondary">{{ count($d['machines']) }} machines</span>
            @unless($d['eigen'])
            <span class="ms-auto"><a href="#" class="btn btn-sm btn-outline-boels disabled" title="Fase 3: aanvraagmail naar dit depot"><i class="bi bi-envelope me-1"></i>Aanvraag mailen <span class="fase-badge">fase 3</span></a></span>
            @endunless
        </div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Subgroep</th><th>Omschrijving</th>@if($upload->type === 'project')<th>Contract</th>@endif<th>Machinenr</th><th>Merk / model</th><th>Status</th><th>Laatste uit-huur</th></tr></thead>
            <tbody>
            @foreach($d['machines'] as $rij)
                @php($m = $rij['machine'])
                <tr><td class="fw-semibold">{{ $m->subgroep_nr }}</td><td class="small">{{ $rij['regel']->omschrijving ?: $m->omschrijving }}</td>
                    @if($upload->type === 'project')<td class="small">{{ $rij['regel']->contract_nr }}</td>@endif
                    <td><strong>{{ $m->uniek_nr }}</strong></td><td class="small text-muted">{{ trim(($m->extra['merk'] ?? '').' '.($m->extra['model'] ?? '')) }}</td>
                    <td><span class="badge {{ $kleur[$m->status_code] ?? 'bg-secondary' }}">{{ $m->status_raw }}</span></td>
                    <td class="small">{{ $m->laatste_uithuur?->format('d-m-Y') }}</td></tr>
            @endforeach
            </tbody></table></div>
    </div>
    @empty
        @if($aantalRegels > 0)<div class="alert alert-warning">Geen inzetbare machines gevonden voor deze regels.</div>@endif
    @endforelse
</div>
<div class="tab-pane fade" id="tabRegels">
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>#</th>@if($upload->type === 'project')<th>Contract</th>@endif<th>Subgroep</th><th>Omschrijving</th><th>Status</th><th class="text-end">Nodig</th><th class="text-end">Gevonden</th><th>Toegewezen</th><th>Voorraad per depot (Avail / Service / Repair)</th></tr></thead>
        <tbody>
        @forelse($regels as $r)
            @php($regel = $r['regel'])
            <tr class="{{ $r['tekort'] > 0 ? 'table-danger' : '' }}">
                <td class="text-muted small">{{ $regel->regel_nr }}</td>
                @if($upload->type === 'project')<td class="small">{{ $regel->contract_nr }}</td>@endif
                <td class="fw-semibold">{{ $regel->subgroep_nr }}</td>
                <td class="small">{{ $regel->omschrijving }}@if($regel->verhuurdatum)<div class="text-muted">vanaf {{ $regel->verhuurdatum->format('d-m-Y') }}</div>@endif</td>
                <td class="small">{{ $regel->status_raw }}</td>
                <td class="text-end">{{ $r['nodig'] }}</td>
                <td class="text-end {{ $r['tekort'] > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' }}">{{ $r['gevonden'] }}@if($r['tekort'] > 0) <small>(tekort {{ $r['tekort'] }})</small>@endif</td>
                <td class="small">
                    @foreach(collect($r['toewijzing'])->groupBy('depot_nummer') as $nr => $ms)
                        <div><span class="badge {{ $nr === $eigen ? 'bg-boels' : 'bg-light text-dark border' }}">{{ $nr }} {{ $depotNamen[$nr] ?? $ms->first()->depot_naam }}</span>
                        @foreach($ms as $m)<span class="badge {{ $kleur[$m->status_code] ?? 'bg-secondary' }}" title="{{ $m->status_raw }}">{{ $m->uniek_nr }}</span>@endforeach</div>
                    @endforeach
                </td>
                <td class="small">
                    @forelse($r['voorraad'] as $nr => $v)
                        <span class="me-2 text-nowrap {{ $nr === $eigen ? 'fw-bold text-boels' : '' }}">{{ $nr }}: {{ $v['available'] }}/{{ $v['in_service'] }}/{{ $v['in_repair'] }}</span>
                    @empty
                        <span class="text-danger">niets inzetbaar</span>
                    @endforelse
                    @if($r['niet_inzetbaar'])<div class="text-muted">niet inzetbaar: @foreach($r['niet_inzetbaar'] as $code => $n){{ $statusNaam[$code][0] ?? $code }} {{ $n }}@if(!$loop->last), @endif @endforeach</div>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted py-4">Geen regels.</td></tr>
        @endforelse
        </tbody></table></div></div>
</div>
</div>
@endsection
