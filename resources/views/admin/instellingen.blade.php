@extends('layouts.app')
@section('titel', 'Instellingen')
@section('inhoud')
<div class="page-header mb-4"><h1><i class="bi bi-sliders me-2 text-boels"></i>Instellingen</h1><p>Algemene instellingen van de Voorraad tool. Toegang en rollen beheer je in Boels CORE.</p></div>
<div class="row g-4">
    <div class="col-lg-6">
        <form method="post" action="{{ route('admin.instellingen.opslaan') }}" class="card">
            @csrf
            <div class="card-header">Algemeen</div>
            <div class="card-body">
                @foreach($velden as $key => [$label, $standaard, $uitleg])
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="{{ $key }}">{{ $label }}</label>
                    <input type="text" class="form-control" id="{{ $key }}" name="{{ $key }}" value="{{ $waarden[$key] }}">
                    <div class="form-text">{{ $uitleg }}</div>
                </div>
                @endforeach
                <button class="btn btn-boels"><i class="bi bi-save me-1"></i>Opslaan</button>
            </div>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Gebruikers die de app hebben bezocht <span class="badge bg-secondary">{{ $gebruikers->count() }}</span></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Naam</th><th>Depot</th><th>Rollen (uit CORE)</th><th>Laatst gezien</th></tr></thead>
                    <tbody>
                    @forelse($gebruikers as $u)
                        <tr><td>{{ $u->name }}<br><small class="text-muted">{{ $u->email }}</small></td><td>{{ $u->depot ?: '—' }}</td>
                            <td>@foreach((array) $u->rollen as $r)<span class="badge bg-boels me-1">{{ rol_naam($r) }}</span>@endforeach</td>
                            <td class="small">{{ $u->last_seen_at?->format('d-m-Y H:i') }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center">Nog niemand.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body small text-muted">Rollen toekennen of intrekken: Boels CORE → Beheer → Gebruikers → Voorraad tool. Wijzigingen zijn binnen 5 minuten actief in deze app.</div>
        </div>
    </div>
</div>
@endsection
