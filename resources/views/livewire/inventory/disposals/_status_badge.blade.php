@php
    $colors = [
        'pending' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-rose-100 text-rose-800',
    ];
    $labels = [
        'pending' => __('disposal.status_pending'),
        'approved' => __('disposal.status_approved'),
        'rejected' => __('disposal.status_rejected'),
    ];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colors[$status] ?? 'bg-slate-100 text-slate-700' }}">
    {{ $labels[$status] ?? $status }}
</span>
