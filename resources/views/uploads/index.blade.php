@extends('layouts.app')
@section('titel', 'Uploads')
@section('inhoud')
<div class="page-header mb-4"><h1><i class="bi bi-upload me-2 text-boels"></i>Uploads</h1><p>Excel-bestanden inlezen: de materieellijst (waar staat wat), en contracten of projecten (wat is er nodig).</p></div>
<div class="row g-4">
    <div class="col-lg-4">
        <form method="post" action="{{ route('uploads.opslaan') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-header"><i class="bi bi-file-earmark-excel me-2 text-boels"></i>Nieuw bestand inlezen</div>
            <div class="card-body">
                @if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
                <div class="mb-3">
                    <label class="form-label fw-semibold">Soort bestand</label>
                    @foreach(\App\Models\Upload::TYPES as $t => $naam)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="type" id="type_{{ $t }}" value="{{ $t }}" {{ old('type', 'contract') === $t ? 'checked' : '' }}>
                        <label class="form-check-label" for="type_{{ $t }}">{{ $naam }}
                            <small class="text-muted d-block">{{ ['materieel' => 'Alle machines met depot en status — vervangt de vorige lijst', 'contract' => 'Eén contract/reservering (kolom G = status)', 'project' => 'Meerdere contracten onder één project (kolom Z = status)'][$t] }}</small>
                        </label>
                    </div>
                    @endforeach
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="bestand">Excel-bestand</label>
                    <input type="file" class="form-control" id="bestand" name="bestand" accept=".xlsx,.xls,.csv" required>
                    <div class="form-text">.xlsx of .csv, max. {{ ini_get('upload_max_filesize') }}. De materieellijst van ±52.000 regels leest in ongeveer een halve minuut in. Kolomindeling: Beheer → Kolomindeling.</div>
                </div>
                <button class="btn btn-boels w-100"><i class="bi bi-cloud-upload me-1"></i>Inlezen</button>
            </div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-boxes me-2 text-boels"></i>Actuele materieellijst</div>
            <div class="card-body">
                @if($materieel)
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div><div class="fs-4 fw-bold">{{ number_format($materieelAantal, 0, ',', '.') }}</div><div class="small text-muted">machines</div></div>
                        <div class="flex-grow-1 small">
                            <strong>{{ $materieel->bestandsnaam }}</strong><br>
                            <span class="text-muted">ingelezen {{ $materieel->created_at->format('d-m-Y H:i') }} door {{ $materieel->gebruiker_naam ?: '—' }}</span>
                            @if($materieel->meldingen)<div class="text-warning mt-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ count($materieel->meldingen) }} melding(en)</div>@endif
                        </div>
                        <a href="{{ route('uploads.toon', $materieel) }}" class="btn btn-outline-boels btn-sm"><i class="bi bi-search me-1"></i>Bekijken</a>
                    </div>
                @else
                    <p class="text-muted mb-0">Nog geen materieellijst ingelezen. Zonder deze lijst kan de app niet zoeken waar materieel staat.</p>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-file-earmark-text me-2 text-boels"></i>Contracten en projecten <span class="badge bg-secondary">{{ $uploads->count() }}</span></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Soort</th><th>Referentie</th><th>Bestand</th><th>Regels</th><th>Depot</th><th>Ingelezen</th><th></th></tr></thead>
                    <tbody>
                    @forelse($uploads as $u)
                        <tr>
                            <td><span class="badge {{ $u->type === 'project' ? 'bg-primary' : 'bg-boels' }}">{{ $u->typeNaam() }}</span></td>
                            <td class="fw-semibold">{{ $u->referentie ?: '—' }}</td>
                            <td class="small">{{ $u->bestandsnaam }}@if($u->meldingen) <i class="bi bi-exclamation-triangle text-warning" title="{{ implode(' | ', $u->meldingen) }}"></i>@endif</td>
                            <td>{{ $u->aantal_rijen }}</td>
                            <td>{{ $u->depot_nummer ?: '—' }}</td>
                            <td class="small">{{ $u->created_at->format('d-m-Y H:i') }}<br><span class="text-muted">{{ $u->gebruiker_naam }}</span></td>
                            <td class="text-nowrap">
                                <a href="{{ route('uploads.toon', $u) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-search"></i></a>
                                <form method="post" action="{{ route('uploads.verwijder', $u) }}" class="d-inline" onsubmit="return confirm('Deze upload verwijderen?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nog geen contracten of projecten ingelezen.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
