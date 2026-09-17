@extends('layouts.app')
@section('titel', 'Aanvragen')
@section('inhoud')
<div class="page-header mb-3"><h1><i class="bi bi-envelope-paper me-2 text-boels"></i>Aanvragen aan depots</h1><p>Alle verstuurde aanvraagmails: wie heeft wanneer welk materieel bij welk depot aangevraagd.</p></div>
<form method="get" class="card mb-3"><div class="card-body d-flex flex-wrap align-items-end gap-2">
    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:260px" placeholder="contract, project, depot, machinenr, aanvrager">
    <select name="depot" class="form-select form-select-sm" style="width:auto"><option value="">Alle depots</option>@foreach($depots as $d)<option value="{{ $d->depot_nummer }}" {{ request('depot') === $d->depot_nummer ? 'selected' : '' }}>{{ $d->depot_nummer }} — {{ $d->naam }}</option>@endforeach</select>
    <select name="status" class="form-select form-select-sm" style="width:auto"><option value="">Alle statussen</option><option value="verzonden" {{ request('status') === 'verzonden' ? 'selected' : '' }}>Verzonden</option><option value="mislukt" {{ request('status') === 'mislukt' ? 'selected' : '' }}>Mislukt</option></select>
    <button class="btn btn-sm btn-outline-boels"><i class="bi bi-funnel"></i></button>
    @if(request()->hasAny(['q', 'depot', 'status']))<a href="{{ route('aanvragen.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Datum</th><th>Contract / project</th><th>Aan depot</th><th>Namens</th><th>Machines</th><th>Aanvrager</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($aanvragen as $a)
        <tr>
            <td class="small text-nowrap">{{ $a->created_at->format('d-m-Y H:i') }}</td>
            <td><span class="badge {{ $a->upload_type === 'project' ? 'bg-primary' : 'bg-boels' }}">{{ ucfirst($a->upload_type) }}</span> <strong>{{ $a->referentie }}</strong>@if($a->omschrijving)<div class="small text-muted">{{ $a->omschrijving }}</div>@endif</td>
            <td>{{ $a->depot_nummer }} {{ $a->depot_naam }}<div class="small text-muted">{{ $a->aan_email }}</div></td>
            <td class="small">{{ $a->eigen_depot_naam ?: '—' }}</td>
            <td><span class="badge bg-secondary">{{ $a->aantal_machines }}</span> <span class="small text-muted">{{ collect($a->machines)->pluck('uniek_nr')->take(3)->implode(', ') }}{{ $a->aantal_machines > 3 ? '…' : '' }}</span></td>
            <td class="small">{{ $a->aanvrager_naam }}</td>
            <td>@if($a->status === 'verzonden')<span class="badge bg-success">verzonden</span>@else<span class="badge bg-danger" title="{{ $a->fout }}">mislukt</span>@endif</td>
            <td><a href="{{ route('aanvragen.toon', $a) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-eye"></i></a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">Nog geen aanvragen verstuurd. Een aanvraag maak je vanuit Uploads → contract/project → Beschikbaarheid zoeken → "Aanvraag mailen" bij een depot.</td></tr>
    @endforelse
    </tbody></table></div>
    @if($aanvragen->hasPages())<div class="card-body py-2">{{ $aanvragen->links() }}</div>@endif
</div>
@endsection
