{{--
  progress-step — Progress bar horizontal.
  Usage:
    <x-progress-step :current="2" :total="5" label="Domain" />
    <x-progress-step :step="intake" :total="5" />
    <x-progress-step step="intake" />  -- total default 5

  Props:
    - current  (int, optional) — 1-based index step aktif. Default 1.
    - step     (string, optional) — nama step (intake|domain|scope|architecture|clarify).
                                       Akan dipetakan ke index jika diberikan.
    - total    (int, optional, default 5)
    - label    (string, optional) — nama step untuk display
    - showText (bool, default true)
--}}

@props([
    'current' => null,
    'step' => null,
    'total' => 5,
    'label' => null,
    'showText' => true,
])

@php
    // Map nama step ke index 1-based (jika :step diberikan sebagai string)
    $stepMap = [
        'intake'       => 1,
        'domain'       => 2,
        'scope'        => 3,
        'architecture' => 4,
        'clarify'      => 5,
    ];

    if ($current === null && $step !== null && isset($stepMap[(string) $step])) {
        $current = $stepMap[(string) $step];
    }
    $current = $current === null ? 1 : (int) $current;
    $total   = max(1, (int) $total);
    $current = max(1, min($current, $total));

    // Default label dari nama step jika tidak diisi
    if ($label === null && $step !== null && isset($stepMap[(string) $step])) {
        $labels = [
            'intake'       => 'Intake',
            'domain'       => 'Domain',
            'scope'        => 'Scope',
            'architecture' => 'Architecture',
            'clarify'      => 'Clarify',
        ];
        $label = $labels[(string) $step] ?? null;
    }

    $pct = (int) round(($current / $total) * 100);
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }} role="group" aria-label="Progress wizard">
    @if($showText)
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-text">
                Step {{ $current }} dari {{ $total }}
                @if($label)
                    <span class="text-text-muted">— {{ $label }}</span>
                @endif
            </p>
            <p class="text-xs text-text-muted" aria-hidden="true">{{ $pct }}%</p>
        </div>
    @endif

    <div class="w-full h-2 bg-bg-alt rounded-sm overflow-hidden border border-border"
         role="progressbar"
         aria-valuenow="{{ $pct }}"
         aria-valuemin="0"
         aria-valuemax="100"
         @if($label) aria-label="Step {{ $current }} dari {{ $total }} — {{ $label }}" @else aria-label="Progress {{ $pct }} persen" @endif>
        <div class="h-full bg-primary transition-all duration-300 ease-out"
             style="width: {{ $pct }}%"></div>
    </div>
</div>