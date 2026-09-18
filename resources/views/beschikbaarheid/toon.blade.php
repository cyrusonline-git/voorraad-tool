@extends('layouts.app')
@section('titel', 'Beschikbaarheid '.$upload->referentie)
@php($statusNaam = \App\Models\Materieel::STATUSSEN)
@php($kleur = ['available' => 'bg-success', 'in_service' => 'bg-warning text-dark', 'in_repair' => 'bg-danger'])
@push('head')<style>.tabel-depot { table-layout: fixed; } .tabel-depot td, .tabel-depot th { overflow: hidden; text-overflow: ellipsis; } .tabel-depot td:nth-child(3) { white-space: normal; } .tabel-order { table-layout: fixed; } .tabel-order td { white-space: normal; }</style>@endpush
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1">
        <h1><i class="bi bi-search me-2 text-boels"></i>Beschikbaarheid — {{ $upload->typeNaam() }} {{ $upload->referentie }}</h1>
        <p>Gezocht in de materieellijst van {{ $materieel->created_at->format('d-m-Y H:i') }} ({{ number_format($materieel->aantal_rijen, 0, ',', '.') }} machines). Eerst het eigen depot volledig (Available, dan In Service), daarna andere depots (Available, dan In Service); In Repair alleen als laatste. Aanvragen gaan per subgroep en aantal, niet per machinenummer.
        @if($reserveringenAanwezig)<br><i class="bi bi-calendar-check me-1"></i>Reserveringen (quotes) die binnen <strong>{{ $horizon }} dagen</strong> starten zijn van de voorraad afgetrokken{{ $gereserveerdTotaal > 0 ? ':' : '.' }} @if($gereserveerdTotaal > 0)<strong>{{ $gereserveerdTotaal }}</strong> machine(s) op depots bezet voor andere orders.@endif @else<br><span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Nog geen reserveringenlijst ingelezen: aankomende quotes op de depots tellen nu niet mee.</span>@endif</p>
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
    <div class="col-6 col-md"><div class="card kpi-tile h-100" style="border-left:5px solid {{ $order['open'] === 0 && $order['aantal'] > 0 ? '#198754' : '#dc3545' }}"><div class="kpi-body"><div class="kpi-icon" style="background:{{ $order['open'] === 0 && $order['aantal'] > 0 ? '#198754' : '#dc3545' }}"><i class="bi bi-{{ $order['open'] === 0 ? 'check2-all' : 'clipboard-x' }}"></i></div><div><div class="kpi-value {{ $order['open'] === 0 ? 'text-success' : 'text-danger' }}">{{ $order['open'] === 0 ? 'Compleet' : $order['open'].' open' }}</div><div class="kpi-label">{{ $order['geregeld'] }} van {{ $order['nodig'] }} stuks geregeld · {{ $order['compleet'] }}/{{ $order['aantal'] }} subgroepen @if($order['tekort'] > 0)<br><span class="text-danger fw-semibold"><i class="bi bi-x-octagon me-1"></i>{{ $order['tekort'] }} stuks nergens beschikbaar ({{ $order['tekort_subgroepen'] }} subgroep{{ $order['tekort_subgroepen'] === 1 ? '' : 'en' }})</span>@endif</div></div></div></div></div>
    <div class="col-6 col-md"><div class="card kpi-tile h-100"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-list-ol"></i></div><div><div class="kpi-value">{{ $aantalRegels }}</div><div class="kpi-label">regels te zoeken</div></div></div></div></div>
    <div class="col-6 col-md"><div class="card kpi-tile h-100"><div class="kpi-body"><div class="kpi-icon" style="background:#198754"><i class="bi bi-check2-circle"></i></div><div><div class="kpi-value">{{ $totaalGevonden }} <span class="fs-6 text-muted">/ {{ $totaalNodig }}</span></div><div class="kpi-label">machines gevonden / nodig</div></div></div></div></div>
    <div class="col-6 col-md"><div class="card kpi-tile h-100"><div class="kpi-body"><div class="kpi-icon" style="background:{{ $tekorten ? '#dc3545' : '#6c757d' }}"><i class="bi bi-exclamation-triangle"></i></div><div><div class="kpi-value {{ $tekorten ? 'text-danger' : '' }}">{{ $tekorten }}</div><div class="kpi-label">regels met tekort</div></div></div></div></div>
    <div class="col-6 col-md"><div class="card kpi-tile h-100"><div class="kpi-body"><div class="kpi-icon" style="background:#0d6efd"><i class="bi bi-geo-alt"></i></div><div><div class="kpi-value">{{ count($perDepot) }}</div><div class="kpi-label">depots om te halen{{ $eigenNaam ? ' · eigen: '.$eigenNaam : '' }}</div></div></div></div></div>
</div>

@if($aantalRegels === 0)
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Geen regels om te zoeken: er zijn geen regels met status "Niet toegekend". Toegekend staat al vast, in huur is al geregeld, uit-verhuur en goederen in zijn niet nodig.</div>
@endif

@if($order['aantal'] > 0)
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center gap-2"><i class="bi bi-clipboard-check me-1 text-boels"></i>Order compleet? — per subgroep
        <span class="ms-auto small text-muted">Aangevraagd = verstuurde aanvraagmails voor dit {{ strtolower($upload->typeNaam()) }} (zie <a href="{{ route('aanvragen.index', ['q' => $upload->referentie]) }}">Aanvragen</a>); antwoorden van depots worden niet automatisch verwerkt.</span></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0 tabel-order">
        <colgroup><col style="width:100px"><col><col style="width:80px"><col style="width:200px"><col style="width:260px"><col style="width:120px"><col style="width:300px"></colgroup>
        <thead><tr><th>Subgroep</th><th>Omschrijving</th><th class="text-end">Nodig</th><th>Eigen depot{{ $eigenNaam ? ' ('.$eigenNaam.')' : '' }}</th><th>Aangevraagd bij</th><th class="text-end">Nog te regelen</th><th>Advies voor de rest</th></tr></thead>
        <tbody>
        @foreach($order['subgroepen'] as $x)
            <tr class="{{ $x['compleet'] ? 'table-success' : ($x['tekort'] > 0 ? 'table-danger' : 'table-warning') }}">
                <td class="fw-semibold">{{ $x['subgroep_nr'] }}</td><td class="small">{{ $x['omschrijving'] }}</td>
                <td class="text-end fw-bold">{{ $x['nodig'] }}</td>
                <td>@if($x['eigen'])<strong>{{ $x['eigen'] }}</strong> <span class="small text-muted">({{ $x['eigen_status']['available'] }} av / {{ $x['eigen_status']['in_service'] }} serv / {{ $x['eigen_status']['in_repair'] }} rep)</span>@else<span class="text-muted">0</span>@endif</td>
                <td>@forelse($x['aangevraagd'] as $a)<a href="{{ route('aanvragen.toon', $a['aanvraag_id']) }}" class="badge bg-success text-decoration-none me-1" title="aanvraag #{{ $a['aanvraag_id'] }} · {{ $a['datum']->format('d-m-Y H:i') }}">{{ $a['naam'] }} {{ $a['aantal'] }}×</a>@empty<span class="text-muted">—</span>@endforelse</td>
                <td class="text-end">@if($x['compleet'])<span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>compleet</span>@else<span class="fw-bold text-danger fs-6">{{ $x['open'] }}</span>@endif</td>
                <td class="small">@if(!$x['compleet'])@foreach($x['advies'] as $a)<a href="{{ route('aanvragen.nieuw', [$upload, 'depot_nr' => $a['nr'], 'eigen' => $eigen]) }}" class="badge bg-light text-dark border text-decoration-none me-1" title="Aanvraag mailen aan {{ $a['naam'] }}"><i class="bi bi-envelope me-1"></i>{{ $a['naam'] }} {{ $a['aantal'] }}×</a>@endforeach
                    @if($x['tekort'] > 0)<div class="text-danger fw-semibold mt-1"><i class="bi bi-x-octagon me-1"></i>{{ $x['advies'] ? 'ook na aanvragen elders nog' : 'nergens beschikbaar:' }} {{ $x['tekort'] }} te kort</div>@endif
                @endif</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="2">Totaal</td><td class="text-end">{{ $order['nodig'] }}</td><td>{{ array_sum(array_column($order['subgroepen'], 'eigen')) }}</td><td>{{ array_sum(array_column($order['subgroepen'], 'aangevraagd_totaal')) }} aangevraagd</td><td class="text-end {{ $order['open'] ? 'text-danger' : 'text-success' }}">{{ $order['open'] ?: 'compleet' }}</td><td class="{{ $order['tekort'] ? 'text-danger' : '' }}">{{ $order['tekort'] ? $order['tekort'].' stuks nergens beschikbaar' : '' }}</td></tr></tfoot>
    </table></div>
</div>
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
            <span class="badge bg-secondary">{{ $d['aantal'] }} stuks · {{ count($d['regels']) }} subgroepen</span>
            @unless($d['eigen'])
            <span class="ms-auto d-flex align-items-center gap-2">
                @if(isset($order['perDepotAangevraagd'][$nr]))<a href="{{ route('aanvragen.toon', $order['perDepotAangevraagd'][$nr]['aanvraag_id']) }}" class="badge bg-success text-decoration-none"><i class="bi bi-check-lg me-1"></i>aangevraagd {{ $order['perDepotAangevraagd'][$nr]['laatste']->format('d-m H:i') }} · {{ $order['perDepotAangevraagd'][$nr]['aantal'] }} stuks</a>@endif
                <a href="{{ route('aanvragen.nieuw', [$upload, 'depot_nr' => $nr, 'eigen' => $eigen]) }}" class="btn btn-sm {{ isset($order['perDepotAangevraagd'][$nr]) ? 'btn-outline-boels' : 'btn-boels' }}"><i class="bi bi-envelope me-1"></i>{{ isset($order['perDepotAangevraagd'][$nr]) ? 'Nogmaals aanvragen' : 'Aanvraag mailen' }}</a></span>
            @else
            <span class="ms-auto small text-muted">eigen depot: bij de expeditie aanvragen op subgroep</span>
            @endunless
        </div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 tabel-depot">
            <colgroup><col style="width:90px"><col style="width:110px"><col>@if($upload->type === 'project')<col style="width:190px">@endif<col style="width:330px"></colgroup>
            <thead><tr><th>Aantal</th><th>Subgroep</th><th>Omschrijving</th>@if($upload->type === 'project')<th>Contract(en)</th>@endif<th>Waarvan</th></tr></thead>
            <tbody>
            @foreach($d['regels'] as $rij)
                <tr><td><span class="badge bg-boels fs-6 px-3">{{ $rij['aantal'] }}×</span></td>
                    <td class="fw-semibold">{{ $rij['subgroep_nr'] }}</td><td>{{ $rij['omschrijving'] }}</td>
                    @if($upload->type === 'project')<td class="small">{{ implode(', ', $rij['contracten']) }}</td>@endif
                    <td class="small">@if($rij['available'])<span class="badge bg-success">{{ $rij['available'] }} Available</span> @endif @if($rij['in_service'])<span class="badge bg-warning text-dark">{{ $rij['in_service'] }} In Service</span> @endif @if($rij['in_repair'])<span class="badge bg-danger">{{ $rij['in_repair'] }} In Repair</span>@endif</td></tr>
            @endforeach
            </tbody></table></div>
    </div>
    @empty
        @if($aantalRegels > 0)<div class="alert alert-warning">Geen inzetbare machines gevonden voor deze regels.</div>@endif
    @endforelse
</div>
<div class="tab-pane fade" id="tabRegels">
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>#</th>@if($upload->type === 'project')<th>Contract</th>@endif<th>Subgroep</th><th>Omschrijving</th><th>Status</th><th class="text-end">Nodig</th><th class="text-end">Gevonden</th><th>Toegewezen</th><th>Voorraad per depot (Avail / Service / Repair, minus gereserveerd)</th></tr></thead>
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
                        <div><span class="badge {{ (string) $nr === (string) $eigen ? 'bg-boels' : 'bg-light text-dark border' }}">{{ $nr }} {{ $depotNamen[$nr] ?? $ms->first()->depot_naam }}</span>
                        <strong>{{ $ms->count() }}×</strong> <span class="text-muted">({{ $ms->where('status_code', 'available')->count() }} av / {{ $ms->where('status_code', 'in_service')->count() }} serv / {{ $ms->where('status_code', 'in_repair')->count() }} rep)</span></div>
                    @endforeach
                </td>
                <td class="small">
                    @forelse($r['voorraad'] as $nr => $v)
                        <span class="me-2 text-nowrap {{ (string) $nr === (string) $eigen ? 'fw-bold text-boels' : '' }}">{{ $nr }}: {{ $v['available'] }}/{{ $v['in_service'] }}/{{ $v['in_repair'] }} @if(!empty($r['gereserveerd'][(string) $nr]))<span class="text-danger" title="binnen {{ $horizon }} dagen gereserveerd op dit depot">−{{ $r['gereserveerd'][(string) $nr] }} gereserveerd</span>@endif</span>
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
