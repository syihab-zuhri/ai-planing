@extends('layouts.app', ['title' => 'Step 1 — Intake'])

@section('content')
<div class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
     x-data="autoSaveForm({ url: '{{ url('/api/wizard/intake') }}' })">

    {{-- Progress --}}
    <div class="mb-8">
        <x-progress-step :current="1" :total="5" label="Intake" />
    </div>

    {{-- Header step --}}
    <header class="mb-6">
        <h1 class="text-2xl font-semibold text-text mb-2">Intake Proyek</h1>
        <p class="text-sm text-text-muted">
            Ceritakan ide dasar proyek Anda dalam beberapa kalimat. Semua field
            wajib diisi kecuali batasan yang diketahui.
        </p>
    </header>

    {{-- Auto-save indicator --}}
    <div class="mb-6 px-3 py-2 text-xs text-text-muted bg-bg-alt border border-border rounded-sm flex items-center gap-2"
         role="status" aria-live="polite">
        <span class="w-2 h-2 rounded-full"
              :class="state === 'saving' ? 'bg-warning' : (state === 'saved' ? 'bg-success' : (state === 'error' ? 'bg-danger' : 'bg-text-muted'))"
              aria-hidden="true"></span>
        <span x-text="label()"></span>
    </div>

    {{-- Form --}}
    <form method="POST" action="{{ url('/api/wizard/intake') }}" class="card" novalidate
          @submit.prevent="submitAndContinue('/wizard/step/domain')">
        @csrf

        <x-form-input name="project_name"
                      label="Nama Proyek"
                      :required="true"
                      maxlength="80"
                      placeholder="Mis. Sistem Absensi Sekolah"
                      helper="Maksimal 80 karakter." />

        <x-form-input name="project_goal"
                      label="Tujuan Proyek"
                      type="textarea"
                      :required="true"
                      maxlength="500"
                      :rows="4"
                      placeholder="Apa yang ingin dicapai oleh proyek ini?"
                      helper="Jelaskan tujuan utama dalam 1–2 paragraf." />

        <x-form-input name="target_users"
                      label="Calon Pengguna"
                      type="textarea"
                      :required="true"
                      maxlength="500"
                      :rows="3"
                      placeholder="Siapa yang akan menggunakan produk ini?"
                      helper="Mis. guru, siswa, orang tua, admin sekolah." />

        <x-form-input name="known_constraints"
                      label="Batasan yang Diketahui"
                      type="textarea"
                      :rows="3"
                      maxlength="500"
                      placeholder="Mis. harus berjalan di WSL, browser Chrome saja, tanpa internet."
                      helper="Opsional. Batasan teknis, regulasi, atau deadline." />

        {{-- Navigation --}}
        <div class="mt-8 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="btn-ghost">
                <span aria-hidden="true">&larr;</span> Kembali ke beranda
            </a>
            <button type="submit" class="btn-primary">
                Lanjut ke Step 2
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
    </form>
</div>
@endsection