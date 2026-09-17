<form method="get" class="card mb-3"><div class="card-body d-flex flex-wrap align-items-end gap-3">
    <div><label class="form-label small mb-1 fw-semibold">Depot</label>
        <select name="depot" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach($depots as $d)<option value="{{ $d->depot_nummer }}" {{ $depot && $depot->depot_nummer === $d->depot_nummer ? 'selected' : '' }}>{{ $d->depot_nummer }} — {{ $d->naam }}</option>@endforeach
        </select></div>
    <div><label class="form-label small mb-1 fw-semibold">Zoeken</label><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="subgroep of omschrijving"></div>
    {{ $slot ?? '' }}
    @yield('extra-filters')
    <button class="btn btn-sm btn-outline-boels"><i class="bi bi-funnel me-1"></i>Toepassen</button>
</div></form>
