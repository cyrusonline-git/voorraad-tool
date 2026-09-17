@extends('layouts.app')
@section('titel', 'Kolomindeling')
@section('inhoud')
<div class="page-header mb-4"><h1><i class="bi bi-table me-2 text-boels"></i>Kolomindeling &amp; statussen</h1><p>Welke kolom in de Excel-bestanden wat betekent, en hoe statussen (NL/EN) worden vertaald. Standaardwaarden staan grijs erbij.</p></div>
<form method="post" action="{{ route('admin.kolommen.opslaan') }}">
@csrf
<div class="row g-4">
    @foreach($types as $type => $t)
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">{{ \App\Models\Upload::TYPES[$type] }}</div>
            <div class="card-body">
                <div class="mb-3 row align-items-center">
                    <label class="col-7 col-form-label small">Rij met kolomkoppen (data begint erna)</label>
                    <div class="col-5"><input type="number" min="1" class="form-control form-control-sm" name="koprij[{{ $type }}]" value="{{ $t['koprij'] }}"></div>
                </div>
                @foreach($t['standaard'] as $veld => $std)
                <div class="mb-2 row align-items-center">
                    <label class="col-7 col-form-label small">{{ $labels[$veld] ?? $veld }} <span class="text-muted">({{ $std }})</span></label>
                    <div class="col-5">
                        <select class="form-select form-select-sm" name="kolommen[{{ $type }}][{{ $veld }}]">
                            <option value="">— niet gebruiken —</option>
                            @foreach($letters as $l)<option value="{{ $l }}" {{ ($t['kolommen'][$veld] ?? '') === $l ? 'selected' : '' }}>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Statusvertaling materieellijst</div>
            <div class="card-body small">
                <p class="text-muted">Tekst in het bestand (hoofdletters maken niet uit) → betekenis. Voeg onderaan eigen regels toe.</p>
                <table class="table table-sm" id="tabelM"><thead><tr><th>Tekst in bestand</th><th>Betekenis</th></tr></thead><tbody>
                @foreach($statusMaterieel as $tekst => $code)
                <tr><td><input class="form-control form-control-sm" name="status_materieel[][tekst]" value="{{ $tekst }}"></td>
                    <td><select class="form-select form-select-sm" name="status_materieel[][code]">@foreach($codesMaterieel as $c => [$en, $nl])<option value="{{ $c }}" {{ $c === $code ? 'selected' : '' }}>{{ $en }} — {{ $nl }}</option>@endforeach</select></td></tr>
                @endforeach
                @for($i = 0; $i < 3; $i++)
                <tr><td><input class="form-control form-control-sm" name="status_materieel[][tekst]" placeholder="nieuwe tekst"></td>
                    <td><select class="form-select form-select-sm" name="status_materieel[][code]"><option value="">—</option>@foreach($codesMaterieel as $c => [$en, $nl])<option value="{{ $c }}">{{ $en }} — {{ $nl }}</option>@endforeach</select></td></tr>
                @endfor
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Statusvertaling contract / project</div>
            <div class="card-body small">
                <p class="text-muted">Alleen regels met betekenis <strong>Niet toegekend</strong> worden in de materieellijst gezocht.</p>
                <table class="table table-sm"><thead><tr><th>Tekst in bestand</th><th>Betekenis</th></tr></thead><tbody>
                @foreach($statusOrder as $tekst => $code)
                <tr><td><input class="form-control form-control-sm" name="status_order[][tekst]" value="{{ $tekst }}"></td>
                    <td><select class="form-select form-select-sm" name="status_order[][code]">@foreach($codesOrder as $c => $nl)<option value="{{ $c }}" {{ $c === $code ? 'selected' : '' }}>{{ $nl }}</option>@endforeach</select></td></tr>
                @endforeach
                @for($i = 0; $i < 3; $i++)
                <tr><td><input class="form-control form-control-sm" name="status_order[][tekst]" placeholder="nieuwe tekst"></td>
                    <td><select class="form-select form-select-sm" name="status_order[][code]"><option value="">—</option>@foreach($codesOrder as $c => $nl)<option value="{{ $c }}">{{ $nl }}</option>@endforeach</select></td></tr>
                @endfor
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-12"><button class="btn btn-boels"><i class="bi bi-save me-1"></i>Opslaan</button></div>
</div>
</form>
@endsection
