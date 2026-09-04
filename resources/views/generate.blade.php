@extends('layouts.app', ['title' => 'Generate Blueprint'])

@section('content')
<section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10" data-page="generatePage"
         x-data="phaseAction({ endpoint: '/api/generate/start', nextUrl: '/validate' })">
    <p class="text-xs font-semibold uppercase tracking-wider text-accent">Tahap 2</p>
    <h1 class="mt-2 text-3xl font-semibold text-primary">Generate Blueprint</h1>
    <p class="mt-3 text-text-muted">Bangun seluruh dokumen proyek dari data wizard menggunakan provider mock yang deterministik.</p>

    <div class="card mt-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-text">Paket dokumentasi lengkap</h2>
                <p class="text-sm text-text-muted">PRD, SRS, arsitektur, keamanan, testing, deployment, dan dokumen pendukung.</p>
            </div>
            <button type="button" class="btn-primary" @click="run" :disabled="state === 'loading'">
                <span x-text="state === 'loading' ? 'Membuat dokumen...' : 'Mulai Generate'"></span>
            </button>
        </div>

        <div class="mt-6" aria-live="polite">
            <p x-show="state === 'error'" class="px-4 py-3 rounded-md bg-red-50 text-danger" x-text="error"></p>
            <div x-show="state === 'success'" class="px-4 py-4 rounded-md bg-green-50 border border-green-200">
                <p class="font-medium text-green-800">Generate selesai.</p>
                <p class="text-sm text-green-700 mt-1"><span x-text="result?.total || 0"></span> dokumen berhasil diproses.</p>
                <button type="button" class="btn-primary mt-4" @click="continueNext">Lanjut ke Validasi</button>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-between">
        <a href="{{ url('/wizard/step/clarify') }}" class="btn-secondary">&larr; Kembali</a>
        <a href="{{ url('/validate') }}" class="btn-ghost">Buka Validasi &rarr;</a>
    </div>
</section>
@endsection
