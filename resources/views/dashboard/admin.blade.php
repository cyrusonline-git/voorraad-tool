@extends('layouts.app')
@section('titel', 'Beheer')
@section('inhoud')
@include('dashboard._kop', ['icoon' => 'gear', 'titel' => 'Beheer', 'tekst' => 'Instellingen, depotkoppeling en mailtemplates — toegang en rollen worden in Boels CORE beheerd'])
<div class="row g-3 mb-4">
    @include('dashboard._tegel', ['icoon' => 'people', 'waarde' => $gebruikers, 'label' => 'gebruikers ('.$gebruikersWeek.' actief deze week)', 'link' => route('admin.instellingen'), 'kleur' => '#0d6efd'])
    @include('dashboard._tegel', ['icoon' => 'geo-alt', 'waarde' => $depotsActief, 'label' => 'depots uit CORE'.($depotsZonderNummer ? ', '.$depotsZonderNummer.' zonder nummer' : ''), 'link' => route('admin.depots'), 'kleur' => $depotsZonderNummer ? '#ffc107' : '#198754'])
    @include('dashboard._tegel', ['icoon' => 'link-45deg', 'waarde' => count($nummersOngekoppeld), 'label' => 'depotnummers in de lijst zonder CORE-depot', 'link' => route('admin.depots'), 'kleur' => count($nummersOngekoppeld) ? '#dc3545' : '#198754', 'klasse' => count($nummersOngekoppeld) ? 'text-danger' : ''])
    @include('dashboard._tegel', ['icoon' => 'envelope', 'waarde' => $aanvragenMislukt, 'label' => 'mislukte aanvraagmails (mailer: '.$mailer.')', 'link' => route('aanvragen.index', ['status' => 'mislukt']), 'kleur' => $aanvragenMislukt ? '#dc3545' : '#6c757d'])
</div>
@if($nummersOngekoppeld)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Depotnummers in de materieellijst zonder CORE-depot: @foreach($nummersOngekoppeld as $n)<strong>{{ $n }}</strong> ({{ $gezien[$n]['naam'] }}, {{ $gezien[$n]['aantal'] }} machines)@if(!$loop->last), @endif @endforeach. Vul het nummer in bij Boels CORE → Beheer → Infrastructuur.</div>
@endif
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">Beheerpagina's</div>
            <div class="list-group list-group-flush">
                <a href="{{ route('admin.depots') }}" class="list-group-item list-group-item-action"><i class="bi bi-geo-alt me-2 text-boels"></i><strong>Depots &amp; areas</strong><div class="small text-muted">Uit CORE; depotnummers en mailadressen controleren, opnieuw ophalen</div></a>
                <a href="{{ route('admin.kolommen') }}" class="list-group-item list-group-item-action"><i class="bi bi-table me-2 text-boels"></i><strong>Kolomindeling &amp; statussen</strong><div class="small text-muted">Welke Excel-kolom wat is, en de vertaling van statussen (NL/EN)</div></a>
                <a href="{{ route('admin.mail') }}" class="list-group-item list-group-item-action"><i class="bi bi-envelope me-2 text-boels"></i><strong>Mailtemplates &amp; testmail</strong><div class="small text-muted">Teksten van de aanvraagmail; testmail naar jezelf</div></a>
                <a href="{{ route('admin.instellingen') }}" class="list-group-item list-group-item-action"><i class="bi bi-sliders me-2 text-boels"></i><strong>Instellingen</strong><div class="small text-muted">Naam, afzender, CC, horizon aankomende orders; gebruikers die de app bezochten</div></a>
                <a href="{{ route('voorraad.minimaal') }}" class="list-group-item list-group-item-action"><i class="bi bi-sliders2 me-2 text-boels"></i><strong>Minimale voorraad</strong><div class="small text-muted">{{ $minimaIngesteld }} depots met ingestelde minima</div></a>
                <a href="{{ config('core.url') }}" class="list-group-item list-group-item-action"><i class="bi bi-grid-3x3-gap me-2 text-boels"></i><strong>Boels CORE</strong><div class="small text-muted">Rollen (Beheer → Gebruikers → Voorraad tool) en depotnummers/mailadressen (Infrastructuur)</div></a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">Laatste uploads</div>
            <ul class="list-group list-group-flush small">
                @forelse($uploads as $u)
                    <li class="list-group-item d-flex justify-content-between align-items-center"><a href="{{ route('uploads.toon', $u) }}" class="text-decoration-none">{{ $u->typeNaam() }} {{ $u->referentie }} · {{ number_format($u->aantal_rijen, 0, ',', '.') }} regels</a><span class="text-muted">{{ $u->created_at->format('d-m H:i') }} · {{ $u->gebruiker_naam }}@if($u->meldingen) <i class="bi bi-exclamation-triangle text-warning" title="{{ implode(' | ', $u->meldingen) }}"></i>@endif</span></li>
                @empty
                    <li class="list-group-item text-muted">Nog geen uploads.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header">Laatst actieve gebruikers</div>
            <ul class="list-group list-group-flush small">
                @foreach($laatsteGebruikers as $u)<li class="list-group-item d-flex justify-content-between"><span>{{ $u->name }} <span class="text-muted">{{ $u->depot }}</span></span><span>@foreach((array) $u->rollen as $r)<span class="badge bg-boels ms-1">{{ rol_naam($r) }}</span>@endforeach</span></li>@endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
