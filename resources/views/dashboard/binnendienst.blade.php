@extends('layouts.app')
@section('titel', 'Binnendienst')
@section('inhoud')
@include('dashboard._kop', ['icoon' => 'headset', 'titel' => 'Binnendienst', 'tekst' => 'Contract of project uploaden en zien op welk depot het materieel beschikbaar is'])
<div class="row g-3 mb-4">
    @include('dashboard._tegel', ['icoon' => 'file-earmark-text', 'waarde' => $openOrders, 'label' => 'orders met regels om te zoeken', 'link' => route('uploads.index'), 'kleur' => $openOrders ? 'var(--boels-orange)' : '#6c757d'])
    @include('dashboard._tegel', ['icoon' => 'search', 'waarde' => $teZoekenTotaal, 'label' => 'regels "Niet toegekend" in alle uploads', 'link' => route('uploads.index'), 'kleur' => '#0d6efd'])
    @include('dashboard._tegel', ['icoon' => 'envelope-paper', 'waarde' => $aanvragenWeek, 'label' => 'aanvragen verstuurd deze week', 'link' => route('aanvragen.index'), 'kleur' => '#198754'])
    @include('dashboard._tegel', ['icoon' => 'boxes', 'waarde' => number_format($materieelAantal, 0, ',', '.'), 'label' => 'machines in de materieellijst', 'link' => $materieel ? route('uploads.toon', $materieel) : route('uploads.index'), 'kleur' => '#6c757d'])
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">Laatste contracten en projecten <a href="{{ route('uploads.index') }}" class="btn btn-sm btn-boels ms-auto"><i class="bi bi-cloud-upload me-1"></i>Nieuw bestand inlezen</a></div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>Soort</th><th>Referentie</th><th class="text-end">Regels</th><th class="text-end">Te zoeken</th><th>Ingelezen</th><th></th></tr></thead>
                <tbody>
                @forelse($uploads as $u)
                    <tr><td><span class="badge {{ $u->type === 'project' ? 'bg-primary' : 'bg-boels' }}">{{ $u->typeNaam() }}</span></td>
                        <td class="fw-semibold">{{ $u->referentie ?: $u->bestandsnaam }}</td><td class="text-end">{{ $u->aantal_rijen }}</td>
                        <td class="text-end">@if($u->te_zoeken)<span class="badge bg-boels">{{ $u->te_zoeken }}</span>@else<span class="text-muted">0</span>@endif</td>
                        <td class="small">{{ $u->created_at->format('d-m-Y H:i') }}<br><span class="text-muted">{{ $u->gebruiker_naam }}</span></td>
                        <td class="text-nowrap"><a href="{{ route('beschikbaarheid', $u) }}" class="btn btn-sm btn-outline-boels" title="Beschikbaarheid zoeken"><i class="bi bi-search"></i></a> <a href="{{ route('uploads.toon', $u) }}" class="btn btn-sm btn-outline-secondary" title="Regels"><i class="bi bi-list-ul"></i></a></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Nog geen contracten of projecten ingelezen. <a href="{{ route('uploads.index') }}">Upload er een</a>.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">Zo werkt het</div>
            <div class="card-body small">
                <ol class="mb-0 ps-3">
                    <li class="mb-1"><strong>Uploads:</strong> lees een contract- of project-Excel in (de materieellijst moet actueel zijn).</li>
                    <li class="mb-1"><strong>Beschikbaarheid zoeken:</strong> regels "Niet toegekend" worden per subgroep gezocht — eerst je eigen depot (Available, dan In Service), daarna andere depots; In Repair als laatste.</li>
                    <li class="mb-1"><strong>Aanvraag mailen:</strong> per depot één mail met subgroep en aantal; het depot kiest zelf de machines.</li>
                    <li><strong>Aanvragen:</strong> alles wat verstuurd is, met status.</li>
                </ol>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Laatste aanvragen</div>
            <ul class="list-group list-group-flush small">
                @forelse($aanvragen as $a)
                    <li class="list-group-item d-flex justify-content-between align-items-center"><a href="{{ route('aanvragen.toon', $a) }}" class="text-decoration-none">{{ $a->depot_naam }} · {{ $a->aantal_machines }} stuks · {{ $a->referentie }}</a><span class="badge {{ $a->status === 'verzonden' ? 'bg-success' : 'bg-danger' }}">{{ $a->status }}</span></li>
                @empty
                    <li class="list-group-item text-muted">Nog geen aanvragen verstuurd.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
