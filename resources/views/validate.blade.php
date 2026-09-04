@extends('layouts.app', ['title' => 'Validasi Blueprint'])

@section('content')
<section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10" data-page="validatePage"
         x-data="phaseAction({ endpoint: '/api/validate/run', nextUrl: '/export' })">
    <p class="text-xs font-semibold uppercase tracking-wider text-accent">Tahap 3</p>
    <h1 class="mt-2 text-3xl font-semibold text-primary">Validasi Blueprint</h1>
    <p class="mt-3 text-text-muted">Periksa kelengkapan struktur, isi dokumen, dan tentukan gate kelayakan export.</p>

    <div class="card mt-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-text">Quality gate</h2>
                <p class="text-sm text-text-muted">Gate B atau lebih tinggi diperlukan untuk membuat paket ZIP.</p>
            </div>
            <button type="button" class="btn-primary" @click="run" :disabled="state === 'loading'">
                <span x-text="state === 'loading' ? 'Memvalidasi...' : 'Jalankan Validasi'"></span>
            </button>
        </div>

        <div class="mt-6" aria-live="polite">
            <p x-show="state === 'error'" class="px-4 py-3 rounded-md bg-red-50 text-danger" x-text="error"></p>
            <div x-show="state === 'success'" class="space-y-4">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-text-muted">Gate hasil:</span>
                    <span class="inline-flex px-3 py-1 rounded-md bg-primary text-white font-semibold" x-text="result?.gate"></span>
                </div>
                <div x-show="result?.blockers?.length" class="px-4 py-3 rounded-md bg-red-50 text-danger">
                    <p class="font-medium">Blocker</p>
                    <ul class="mt-2 list-disc pl-5 text-sm"><template x-for="item in result?.blockers || []"><li x-text="item"></li></template></ul>
                </div>
                <div x-show="result?.warnings?.length" class="px-4 py-3 rounded-md bg-amber-50 text-amber-800">
                    <p class="font-medium">Peringatan</p>
                    <ul class="mt-2 list-disc pl-5 text-sm"><template x-for="item in result?.warnings || []"><li x-text="item"></li></template></ul>
                </div>
                <button type="button" class="btn-primary" @click="continueNext"
                        :disabled="!['B','C','D'].includes(result?.gate)">Lanjut ke Export</button>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-between">
        <a href="{{ url('/generate') }}" class="btn-secondary">&larr; Kembali</a>
        <a href="{{ url('/export') }}" class="btn-ghost">Buka Export &rarr;</a>
    </div>
</section>
@endsection
