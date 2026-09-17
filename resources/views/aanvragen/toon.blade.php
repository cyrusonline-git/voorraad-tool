@extends('layouts.app')
@section('titel', 'Aanvraag #'.$aanvraag->id)
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="flex-grow-1"><h1><i class="bi bi-envelope-paper me-2 text-boels"></i>Aanvraag #{{ $aanvraag->id }} aan {{ $aanvraag->depot_nummer }} — {{ $aanvraag->depot_naam }}</h1>
        <p>{{ ucfirst($aanvraag->upload_type) }} {{ $aanvraag->referentie }} · verstuurd {{ $aanvraag->created_at->format('d-m-Y H:i') }} door {{ $aanvraag->aanvrager_naam }}</p></div>
    @if($aanvraag->status === 'verzonden')<span class="badge bg-success fs-6">verzonden</span>@else<span class="badge bg-danger fs-6">mislukt</span>@endif
    <a href="{{ route('aanvragen.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Aanvragen</a>
    @if($aanvraag->upload_id)<a href="{{ route('beschikbaarheid', [$aanvraag->upload_id, 'depot' => $aanvraag->eigen_depot_nummer]) }}" class="btn btn-outline-boels btn-sm"><i class="bi bi-search me-1"></i>Beschikbaarheid</a>@endif
</div>
@if($aanvraag->fout)<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ $aanvraag->fout }}</div>@endif
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-header">Mailgegevens</div><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-4">Aan</dt><dd class="col-8">{{ $aanvraag->aan_email ?: '—' }}</dd>
                <dt class="col-4">Antwoord naar</dt><dd class="col-8">{{ $aanvraag->reply_to ?: '—' }}</dd>
                <dt class="col-4">Kopie</dt><dd class="col-8">{{ $aanvraag->cc ?: '—' }}</dd>
                <dt class="col-4">Onderwerp</dt><dd class="col-8">{{ $aanvraag->onderwerp }}</dd>
                <dt class="col-4">Namens depot</dt><dd class="col-8">{{ $aanvraag->eigen_depot_naam ?: '—' }}</dd>
                <dt class="col-4">Verhuurdatum</dt><dd class="col-8">{{ $aanvraag->verhuurdatum?->format('d-m-Y') ?: 'n.t.b.' }}</dd>
                <dt class="col-4">Reactie vóór</dt><dd class="col-8">{{ $aanvraag->reactie_voor?->format('d-m-Y') ?: '—' }}</dd>
                @if($aanvraag->opmerking)<dt class="col-4">Opmerking</dt><dd class="col-8" style="white-space:pre-wrap">{{ $aanvraag->opmerking }}</dd>@endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header">Machines <span class="badge bg-secondary">{{ $aanvraag->aantal_machines }}</span></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Subgroep</th><th>Omschrijving</th><th>Machinenr</th><th>Merk / model</th><th>Status</th></tr></thead><tbody>
            @foreach($aanvraag->machines ?? [] as $m)<tr><td>{{ $m['subgroep_nr'] }}</td><td class="small">{{ $m['omschrijving'] }}</td><td><strong>{{ $m['uniek_nr'] }}</strong></td><td class="small text-muted">{{ $m['merk_model'] }}</td><td class="small">{{ $m['status'] }}</td></tr>@endforeach
            </tbody></table></div></div>
        <div class="card"><div class="card-header">Tekst van de mail</div><div class="card-body small" style="white-space:pre-wrap">{{ $aanvraag->body }}</div></div>
    </div>
</div>
@endsection
