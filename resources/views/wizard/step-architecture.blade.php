@extends('layouts.app', ['title' => 'Step 4 — Architecture'])

@section('content')
<div class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
     x-data="autoSaveForm({ url: '{{ url('/api/wizard/architecture') }}' })">

    <div class="mb-8">
        <x-progress-step :current="4" :total="5" label="Architecture" />
    </div>

    <header class="mb-6">
        <h1 class="text-2xl font-semibold text-text mb-2">Arah Arsitektur</h1>
        <p class="text-sm text-text-muted">
            Pilih teknologi dan preferensi hosting. Anda dapat menerima saran sistem dengan
            memilih opsi "Saran sistem".
        </p>
    </header>

    <div class="mb-6 px-3 py-2 text-xs text-text-muted bg-bg-alt border border-border rounded-sm flex items-center gap-2"
         role="status" aria-live="polite">
        <span class="w-2 h-2 rounded-full"
              :class="state === 'saving' ? 'bg-warning' : (state === 'saved' ? 'bg-success' : (state === 'error' ? 'bg-danger' : 'bg-text-muted'))"
              aria-hidden="true"></span>
        <span x-text="label()"></span>
    </div>

    <form method="POST" action="{{ url('/api/wizard/architecture') }}" class="card" novalidate
          @submit.prevent="submitAndContinue('/wizard/step/clarify')">
        @csrf

        <x-form-select name="preferred_stack"
                      label="Stack Pilihan"
                      :required="true"
                      :options="[
                          'Laravel+Blade' => 'Laravel + Blade',
                          'Node+React' => 'Node.js + React',
                          'Other' => 'Lainnya',
                          'Saran sistem' => 'Saran sistem',
                      ]"
                      placeholder="— Pilih stack —"
                      helper="Pilih 'Saran sistem' jika Anda ingin rekomendasi otomatis." />

        <x-form-select name="hosting_preference"
                      label="Preferensi Hosting"
                      :required="true"
                      :options="[
                          'WSL' => 'WSL (lokal)',
                          'VPS' => 'VPS',
                          'Cloud' => 'Cloud (AWS/GCP/Azure)',
                          'Saran sistem' => 'Saran sistem',
                      ]"
                      placeholder="— Pilih hosting —" />

        <x-form-list name="known_integrations"
                     label="Integrasi yang Sudah Diketahui"
                     placeholder="Mis. Stripe, Twilio, S3, SendGrid"
                     helper="Opsional. Sistem akan menambahkan jika kosong." />

        <x-form-select name="data_sensitivity"
                      label="Sensitivitas Data"
                      :required="true"
                      :options="[
                          'Public' => 'Public (tanpa batasan)',
                          'Internal' => 'Internal (perusahaan)',
                          'Confidential' => 'Confidential (data pribadi/finansial)',
                          'Restricted' => 'Restricted (regulasi khusus: kesehatan, dsb.)',
                      ]"
                      placeholder="— Pilih tingkat sensitivitas —"
                      helper="Mempengaruhi rekomendasi keamanan &amp; compliance." />

        <div class="mt-8 flex items-center justify-between gap-4">
            <a href="{{ url('/wizard/step/scope') }}" class="btn-secondary">
                <span aria-hidden="true">&larr;</span> Kembali
            </a>
            <button type="submit" class="btn-primary">
                Lanjut ke Step 5
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
    </form>
</div>
@endsection