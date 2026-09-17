<div class="card mb-4">
    <div class="card-header"><i class="bi bi-geo-alt me-2 text-boels"></i>Depots uit Boels CORE <span class="badge bg-secondary ms-1">{{ $depots->count() }}</span>
        @if($eigenDepot)<span class="float-end small text-muted">Jouw depot: <strong>{{ $eigenDepot->naam }}</strong> ({{ $eigenDepot->area }})</span>@endif
    </div>
    <div class="card-body">
        @if($depots->isEmpty())
            <p class="text-muted mb-0">Nog geen depots ontvangen uit CORE. Een beheerder kan ze ophalen via Beheer → Depots.</p>
        @else
            <div class="row g-3">
                @foreach($areas as $area => $lijst)
                <div class="col-md-4 col-lg-3">
                    <div class="fw-semibold mb-1">{{ $area ?: 'Zonder area' }}</div>
                    <ul class="list-unstyled small mb-0">
                        @foreach($lijst as $d)
                        <li class="{{ $eigenDepot && $eigenDepot->id === $d->id ? 'text-boels fw-semibold' : '' }}">
                            <i class="bi bi-dot"></i>{{ $d->naam }}@if($d->depot_nummer) <span class="text-muted">#{{ $d->depot_nummer }}</span>@endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
