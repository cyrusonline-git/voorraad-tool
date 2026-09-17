@extends('layouts.app')
@section('titel', 'Depots & areas')
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="flex-grow-1"><h1><i class="bi bi-geo-alt me-2 text-boels"></i>Depots &amp; areas</h1>
        <p>Gespiegeld uit Boels CORE (Beheer → Infrastructuur is leidend)@if($gesynct) · laatst opgehaald {{ $gesynct->format('d-m-Y H:i') }}@endif</p></div>
    <form method="post" action="{{ route('admin.depots.sync') }}">@csrf
        <button class="btn btn-outline-boels"><i class="bi bi-arrow-repeat me-1"></i>Nu ophalen uit CORE</button>
    </form>
</div>
<form method="post" action="{{ route('admin.depots.opslaan') }}">
@csrf
<div class="card">
    <div class="card-header">Per depot: depotnummer zoals in de materieel-Excel (kolom J) en het mailadres voor aanvragen</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Depot</th><th>Area</th><th>Business unit</th><th>Plaats</th><th style="width:140px">Depotnummer</th><th style="width:280px">Aanvraag-mailadres</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($depots as $d)
                <tr class="{{ $d->actief ? '' : 'text-muted' }}">
                    <td class="fw-semibold">{{ $d->naam }}</td>
                    <td>{{ $d->area }}</td>
                    <td class="small">{{ $d->business_unit }}</td>
                    <td class="small">{{ $d->plaats }}</td>
                    <td><input type="text" class="form-control form-control-sm" name="depot[{{ $d->id }}][depot_nummer]" value="{{ $d->depot_nummer }}" placeholder="bijv. 123"></td>
                    <td><input type="email" class="form-control form-control-sm" name="depot[{{ $d->id }}][email]" value="{{ $d->email }}" placeholder="{{ $d->email_core ?: 'geen adres in CORE' }}">
                        @if($d->email_core)<small class="text-muted">CORE: {{ $d->email_core }}</small>@endif</td>
                    <td>@if($d->actief)<span class="badge bg-success">actief</span>@else<span class="badge bg-secondary">niet meer in CORE</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Nog geen depots. Klik op "Nu ophalen uit CORE".</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body"><button class="btn btn-boels"><i class="bi bi-save me-1"></i>Opslaan</button></div>
</div>
</form>
@endsection
