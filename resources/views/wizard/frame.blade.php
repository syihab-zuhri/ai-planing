@extends('layouts.app')

@section('title', 'BlueprintForge — Wizard')

@section('content')
<main class="max-w-3xl mx-auto px-6 py-10">
    @php
        $step = $step ?? 'intake';
        $stepFile = "wizard.step-{$step}";
        $steps = ['intake','domain','scope','architecture','clarify'];
        $currentStepNum = array_search($step, $steps, true) + 1;
        $totalSteps = count($steps);
    @endphp

    <x-progress-step :current="$currentStepNum" :total="$totalSteps" :label="ucfirst($step)" />

    @includeIf($stepFile)

    <nav class="mt-10 flex justify-between text-sm">
        @php
            $idx = $currentStepNum - 1;
            $prev = $idx > 0 ? $steps[$idx - 1] : null;
        @endphp

        @if ($prev)
            <a href="/wizard/step/{{ $prev }}" class="text-slate-500 hover:underline">← Kembali</a>
        @else
            <span></span>
        @endif

        @if ($idx < count($steps) - 1)
            <a href="/wizard/step/{{ $steps[$idx + 1] }}" class="text-slate-900 hover:underline">Lanjut →</a>
        @else
            <span></span>
        @endif
    </nav>
</main>
@endsection