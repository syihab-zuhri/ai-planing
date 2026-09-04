@extends('layouts.app', ['title' => 'Step 5 — Clarify'])

@section('content')
@php
    $payload = session('clarify_questions');
    if (!is_array($payload) || empty($payload['questions'])) {
        $payload = [
            'questions' => [
                (object) [
                    'id' => 'ASM-001', 'question' => 'Scope MVP fokus pada web atau mobile?',
                    'impact' => 'scope', 'default' => 'Web', 'confidence' => 'Medium',
                    'type' => 'select', 'options' => ['Web', 'Mobile', 'Keduanya'],
                ],
                (object) [
                    'id' => 'ASM-002', 'question' => 'Apakah target peluncuran < 3 bulan?',
                    'impact' => 'timeline', 'default' => 'Ya, < 3 bulan', 'confidence' => 'High',
                    'type' => 'boolean',
                ],
            ],
            'skip_to_generate' => false,
        ];
    }
    $questions = collect($payload['questions']);
@endphp

<div class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
     x-data="autoSaveForm({ url: '{{ url('/api/wizard/clarify/answers') }}' })">

    <div class="mb-8">
        <x-progress-step :current="5" :total="5" label="Clarify" />
    </div>

    <header class="mb-6">
        <h1 class="text-2xl font-semibold text-text mb-2">Pertanyaan Material</h1>
        <p class="text-sm text-text-muted">
            Sistem memiliki {{ $questions->count() }} pertanyaan untuk menutup gap informasi.
            Setiap pertanyaan punya dampak &amp; nilai default — klik
            <span class="font-medium text-text">Gunakan default</span> untuk melewati.
        </p>
    </header>

    @if($questions->isEmpty())
        <div class="card text-center">
            <p class="text-text-muted">
                Intake Anda sudah cukup lengkap — tidak ada pertanyaan tambahan.
                Lanjut ke generate.
            </p>
            <div class="mt-4">
                <form method="POST" action="{{ url('/api/wizard/clarify/answers') }}"
                      class="inline"
                      @submit.prevent="submitAndContinue('/generate')">
                    @csrf
                    <input type="hidden" name="answers" value="[]">
                    <button type="submit" class="btn-primary">Lanjut ke Generate</button>
                </form>
            </div>
        </div>
    @else
        <form method="POST" action="{{ url('/api/wizard/clarify/answers') }}"
              @submit.prevent="submitAndContinue('/generate')">
            @csrf

            <ul class="space-y-4" role="list">
                @foreach($questions as $q)
                    <li class="card">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs text-text-muted">{{ $q->id }}</span>
                                <span class="text-xs text-text-muted">&middot;</span>
                                <span class="text-xs text-text-muted">
                                    Confidence: <span class="font-medium text-text">{{ $q->confidence ?? 'Medium' }}</span>
                                </span>
                            </div>
                            <x-badge-impact :impact="$q->impact" />
                        </div>

                        <h2 class="text-base font-medium text-text mb-3">{{ $q->question }}</h2>
                        <p class="text-sm text-text-muted mb-4">
                            Default: <span class="font-medium text-text">{{ $q->default ?? $q->default_suggestion ?? '—' }}</span>
                        </p>

                        @if(($q->type ?? '') === 'select' && !empty($q->options))
                            <select name="answers[{{ $loop->index }}][answer]" class="input-field"
                                    aria-label="Jawaban untuk {{ $q->question }}">
                                <option value="{{ $q->default ?? $q->default_suggestion ?? '' }}">— Gunakan default ({{ $q->default ?? $q->default_suggestion ?? '—' }}) —</option>
                                @foreach($q->options as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif(($q->type ?? '') === 'boolean')
                            <select name="answers[{{ $loop->index }}][answer]" class="input-field"
                                    aria-label="Jawaban untuk {{ $q->question }}">
                                <option value="{{ $q->default ?? $q->default_suggestion ?? '' }}">— Gunakan default —</option>
                                <option value="yes">Ya</option>
                                <option value="no">Tidak</option>
                            </select>
                        @else
                            <input type="text" name="answers[{{ $loop->index }}][answer]" class="input-field"
                                   value="{{ $q->default ?? $q->default_suggestion ?? '' }}"
                                   placeholder="Jawaban kustom, atau gunakan default">
                        @endif
                        <input type="hidden" name="answers[{{ $loop->index }}][id]" value="{{ $q->id }}">
                    </li>
                @endforeach
            </ul>

            @if($errors->any())
                <div class="mt-6 px-4 py-3 bg-danger text-white rounded-md text-sm" role="alert">
                    @foreach($errors->all() as $err)
                        <p>{{ $err }}</p>
                    @endforeach
                </div>
            @endif

            <div class="mt-8 flex items-center justify-between gap-4">
                <a href="{{ url('/wizard/step/architecture') }}" class="btn-secondary">
                    <span aria-hidden="true">&larr;</span> Kembali
                </a>
                <button type="submit" class="btn-primary" :disabled="submitting"
                        :aria-busy="submitting ? 'true' : 'false'">
                    <span x-text="submitting ? 'Menyimpan...' : 'Lanjut ke Generate'"></span>
                    <span aria-hidden="true">&rarr;</span>
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
