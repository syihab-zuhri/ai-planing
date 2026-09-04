@extends('layouts.app', ['title' => 'Step 3 — Scope'])

@section('content')
<div class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
     x-data="autoSaveForm({ url: '{{ url('/api/wizard/scope') }}' })">

    <div class="mb-8">
        <x-progress-step :current="3" :total="5" label="Scope" />
    </div>

    <header class="mb-6">
        <h1 class="text-2xl font-semibold text-text mb-2">Lingkup Fitur</h1>
        <p class="text-sm text-text-muted">
            Kelompokkan fitur berdasarkan prioritas. P0 wajib untuk MVP, P1/P2 untuk iterasi
            berikutnya, dan di luar scope jelaskan agar tidak salah ekspektasi.
        </p>
    </header>

    <div class="mb-6 px-3 py-2 text-xs text-text-muted bg-bg-alt border border-border rounded-sm flex items-center gap-2"
         role="status" aria-live="polite">
        <span class="w-2 h-2 rounded-full"
              :class="state === 'saving' ? 'bg-warning' : (state === 'saved' ? 'bg-success' : (state === 'error' ? 'bg-danger' : 'bg-text-muted'))"
              aria-hidden="true"></span>
        <span x-text="label()"></span>
    </div>

    <form method="POST" action="{{ url('/api/wizard/scope') }}" class="card" novalidate
          @submit.prevent="submitAndContinue('/wizard/step/architecture')">
        @csrf

        <x-form-list name="p0_features"
                     label="Fitur P0 (Wajib untuk MVP)"
                     :required="true"
                     :max="10"
                     placeholder="Mis. Autentikasi pengguna dengan email &amp; password"
                     helper="Minimal 1 item, maksimal 10. Ini yang harus jalan di MVP." />

        <x-form-list name="p1_features"
                     label="Fitur P1 (Iterasi Berikutnya)"
                     :max="10"
                     placeholder="Mis. Notifikasi email untuk event penting"
                     helper="Opsional. Fitur tambahan setelah MVP jalan." />

        <x-form-list name="p2_features"
                     label="Fitur P2 (Backlog)"
                     :max="10"
                     placeholder="Mis. Integrasi dengan payment gateway"
                     helper="Opsional. Ide untuk masa depan." />

        <x-form-list name="out_of_scope"
                     label="Di Luar Scope"
                     placeholder="Mis. Aplikasi mobile native, multi-bahasa"
                     helper="Opsional. Cantumkan agar ekspektasi jelas." />

        <div class="mt-8 flex items-center justify-between gap-4">
            <a href="{{ url('/wizard/step/domain') }}" class="btn-secondary">
                <span aria-hidden="true">&larr;</span> Kembali
            </a>
            <button type="submit" class="btn-primary">
                Lanjut ke Step 4
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
    </form>
</div>
@endsection