@extends('layouts.app', ['title' => 'Export Blueprint'])

@section('content')
<section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10" data-page="exportPage"
         x-data="phaseAction({ endpoint: '/api/export/start', download: true })">
    <p class="text-xs font-semibold uppercase tracking-wider text-accent">Tahap 4</p>
    <h1 class="mt-2 text-3xl font-semibold text-primary">Export Blueprint</h1>
    <p class="mt-3 text-text-muted">Kemas seluruh dokumen yang sudah divalidasi menjadi satu arsip ZIP.</p>

    <div class="card mt-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-text">Paket siap unduh</h2>
                <p class="text-sm text-text-muted">Tautan download memiliki masa berlaku terbatas demi keamanan.</p>
            </div>
            <button type="button" class="btn-primary" @click="run" :disabled="state === 'loading'">
                <span x-text="state === 'loading' ? 'Menyiapkan ZIP...' : 'Buat Paket ZIP'"></span>
            </button>
        </div>

        <div class="mt-6" aria-live="polite">
            <p x-show="state === 'error'" class="px-4 py-3 rounded-md bg-red-50 text-danger" x-text="error"></p>
            <div x-show="state === 'success'" class="px-4 py-4 rounded-md bg-green-50 border border-green-200">
                <p class="font-medium text-green-800">Paket export berhasil dibuat.</p>
                <p class="text-sm text-green-700 mt-1">Berlaku sampai <span x-text="result?.expires_at"></span>.</p>
                <a class="btn-primary mt-4" :href="result?.download_url" download>Unduh ZIP</a>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <a href="{{ url('/validate') }}" class="btn-secondary">&larr; Kembali ke Validasi</a>
    </div>
</section>
@endsection
