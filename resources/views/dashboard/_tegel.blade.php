<div class="col-6 col-md-3">
    <a href="{{ $link ?? '#' }}" class="text-decoration-none text-reset"><div class="card kpi-tile h-100"><div class="kpi-body">
        <div class="kpi-icon" style="background:{{ $kleur ?? 'var(--boels-orange)' }};{{ ($kleur ?? '') === '#ffc107' ? 'color:#333' : '' }}"><i class="bi bi-{{ $icoon }}"></i></div>
        <div><div class="kpi-value {{ $klasse ?? '' }}">{{ $waarde }}</div><div class="kpi-label">{{ $label }}</div></div>
    </div></div></a>
</div>
