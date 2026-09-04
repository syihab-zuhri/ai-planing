{{--
  badge-impact — Badge untuk dampak pertanyaan clarify.
  Usage:
    <x-badge-impact impact="scope" />        → primary
    <x-badge-impact impact="biaya" />        → accent
    <x-badge-impact impact="security" />     → danger
    <x-badge-impact impact="timeline" />     → warning

  Props:
    - impact  (string, required) — salah satu dari: scope|biaya|security|timeline
    - label   (string, optional) — override teks label
--}}

@props([
    'impact' => 'scope',
    'label' => null,
])

@php
    $map = [
        'scope'    => ['label' => 'Scope',    'class' => 'bg-primary text-white'],
        'biaya'    => ['label' => 'Biaya',    'class' => 'bg-accent text-white'],
        'security' => ['label' => 'Security', 'class' => 'bg-danger text-white'],
        'timeline' => ['label' => 'Timeline', 'class' => 'bg-warning text-white'],
    ];
    $entry = $map[$impact] ?? $map['scope'];
    $text = $label ?? $entry['label'];
    $classes = $entry['class'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-sm ' . $classes]) }}
      role="status"
      aria-label="Dampak: {{ $text }}">
    {{ $text }}
</span>