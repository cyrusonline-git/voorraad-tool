@extends('layouts.app')
@section('titel', 'Aanvraag aan '.$depot->naam)
@php($kleur = ['available' => 'bg-success', 'in_service' => 'bg-warning text-dark', 'in_repair' => 'bg-danger'])
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1"><h1><i class="bi bi-envelope me-2 text-boels"></i>Aanvraag aan {{ $depot->depot_nummer }} — {{ $depot->naam }}</h1>
        <p>{{ $upload->typeNaam() }} {{ $upload->referentie }} · vink aan welke machines je bij dit depot wilt aanvragen</p></div>
    <a href="{{ route('beschikbaarheid', [$upload, 'depot' => $eigenNr]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Beschikbaarheid</a>
</div>
@if(!$aan)
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Voor depot <strong>{{ $depot->naam }}</strong> is geen mailadres bekend in Boels CORE. Vul het in bij CORE → Beheer → Infrastructuur, of als terugval bij Beheer → Depots.</div>
@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('aanvragen.verstuur', $upload) }}">
@csrf
<input type="hidden" name="depot_nr" value="{{ $depot->depot_nummer }}">
<input type="hidden" name="eigen" value="{{ $eigenNr }}">
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center">Machines bij {{ $depot->naam }} <span class="badge bg-secondary ms-2">{{ count($machines) }}</span>
                <span class="ms-auto small"><a href="#" onclick="document.querySelectorAll('.mach').forEach(c=>c.checked=true);return false;">alles</a> · <a href="#" onclick="document.querySelectorAll('.mach').forEach(c=>c.checked=false);return false;">niets</a></span></div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th></th><th>Subgroep</th><th>Omschrijving</th>@if($upload->type === 'project')<th>Contract</th>@endif<th>Machinenr</th><th>Merk / model</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($machines as $rij)
                    @php($m = $rij['machine'])
                    <tr><td><input type="checkbox" class="form-check-input mach" name="machines[]" value="{{ $m->uniek_nr }}" checked></td>
                        <td class="fw-semibold">{{ $m->subgroep_nr }}</td><td class="small">{{ $rij['regel']->omschrijving ?: $m->omschrijving }}</td>
                        @if($upload->type === 'project')<td class="small">{{ $rij['regel']->contract_nr }}</td>@endif
                        <td><strong>{{ $m->uniek_nr }}</strong></td><td class="small text-muted">{{ trim(($m->extra['merk'] ?? '').' '.($m->extra['model'] ?? '')) }}</td>
                        <td><span class="badge {{ $kleur[$m->status_code] ?? 'bg-secondary' }}">{{ $m->status_raw }}</span></td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Geen machines toegewezen aan dit depot. Ga terug naar de beschikbaarheid.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Mail</div>
            <div class="card-body">
                <dl class="row small mb-3">
                    <dt class="col-4">Aan</dt><dd class="col-8">{{ $aan ?: '—' }}</dd>
                    <dt class="col-4">Namens</dt><dd class="col-8">{{ $eigenDepot?->naam ?: (core_gebruiker()['name'] ?? '') }}<br><span class="text-muted">antwoord naar {{ $replyTo ?: '—' }}</span></dd>
                    <dt class="col-4">Kopie</dt><dd class="col-8">{{ core_gebruiker()['email'] ?? '—' }}</dd>
                    <dt class="col-4">Onderwerp</dt><dd class="col-8">{{ $voorbeeldOnderwerp }}</dd>
                </dl>
                <div class="mb-3"><label class="form-label small fw-semibold">Gewenste verhuurdatum</label><input type="date" name="verhuurdatum" class="form-control form-control-sm" value="{{ old('verhuurdatum', $verhuurdatum) }}"></div>
                <div class="mb-3"><label class="form-label small fw-semibold">Reactie gewenst vóór</label><input type="date" name="reactie_voor" class="form-control form-control-sm" value="{{ old('reactie_voor') }}"></div>
                <div class="mb-3"><label class="form-label small fw-semibold">Opmerking voor het depot</label><textarea name="opmerking" rows="4" class="form-control form-control-sm" placeholder="bijv. ophalen met eigen transport op dinsdag">{{ old('opmerking') }}</textarea></div>
                <button class="btn btn-boels w-100" {{ $aan && count($machines) ? '' : 'disabled' }}><i class="bi bi-send me-1"></i>Aanvraag versturen</button>
                <div class="form-text mt-2">De mail bevat de inleiding en afsluiting uit Beheer → Mailtemplates, met de aangevinkte machines als tabel. Hij wordt vastgelegd onder "Aanvragen".</div>
            </div>
        </div>
    </div>
</div>
</form>
@endsection
