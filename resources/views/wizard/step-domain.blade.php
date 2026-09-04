@extends('layouts.app', ['title' => 'Step 2 — Domain'])

@section('content')
<div class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
     x-data="autoSaveForm({ url: '{{ url('/api/wizard/domain') }}' })">

    <div class="mb-8">
        <x-progress-step :current="2" :total="5" label="Domain" />
    </div>

    <header class="mb-6">
        <h1 class="text-2xl font-semibold text-text mb-2">Domain &amp; Value Proposition</h1>
        <p class="text-sm text-text-muted">
            Jelaskan domain proyek, masalah yang dipecahkan, dan estimasi skala pengguna.
        </p>
    </header>

    <div class="mb-6 px-3 py-2 text-xs text-text-muted bg-bg-alt border border-border rounded-sm flex items-center gap-2"
         role="status" aria-live="polite">
        <span class="w-2 h-2 rounded-full"
              :class="state === 'saving' ? 'bg-warning' : (state === 'saved' ? 'bg-success' : (state === 'error' ? 'bg-danger' : 'bg-text-muted'))"
              aria-hidden="true"></span>
        <span x-text="label()"></span>
    </div>

    <form method="POST" action="{{ url('/api/wizard/domain') }}" class="card" novalidate
          @submit.prevent="submitAndContinue('/wizard/step/scope')">
        @csrf

        <x-form-select name="domain_category"
                      label="Kategori Domain"
                      :required="true"
                      :options="[
                          'Web' => 'Web',
                          'Mobile' => 'Mobile',
                          'API' => 'API',
                          'Internal Tool' => 'Internal Tool',
                          'Other' => 'Lainnya',
                      ]"
                      placeholder="— Pilih kategori domain —" />

        <x-form-input name="problem_statement"
                      label="Pernyataan Masalah"
                      type="textarea"
                      :required="true"
                      maxlength="500"
                      :rows="4"
                      placeholder="Masalah apa yang dipecahkan oleh proyek ini?"
                      helper="Fokus pada masalah, bukan solusi." />

        <x-form-input name="value_proposition"
                      label="Value Proposition"
                      type="textarea"
                      :required="true"
                      maxlength="300"
                      :rows="3"
                      placeholder="Mengapa solusi ini bernilai bagi pengguna?"
                      helper="Ringkas: apa, untuk siapa, kenapa penting." />

        <x-form-select name="scale_estimate_mvp"
                      label="Estimasi Skala (MVP)"
                      :required="true"
                      :options="[
                          '<100' => '&lt; 100 pengguna',
                          '100-1k' => '100–1.000 pengguna',
                          '1k-10k' => '1.000–10.000 pengguna',
                          '10k+' => '&gt; 10.000 pengguna',
                      ]"
                      placeholder="— Pilih estimasi skala MVP —" />

        <x-form-select name="scale_estimate_12mo"
                      label="Estimasi Skala (12 Bulan ke Depan)"
                      :required="true"
                      :options="[
                          '<100' => '&lt; 100 pengguna',
                          '100-1k' => '100–1.000 pengguna',
                          '1k-10k' => '1.000–10.000 pengguna',
                          '10k+' => '&gt; 10.000 pengguna',
                      ]"
                      placeholder="— Pilih estimasi skala 12 bulan —" />

        <div class="mt-8 flex items-center justify-between gap-4">
            <a href="{{ url('/wizard/step/intake') }}" class="btn-secondary">
                <span aria-hidden="true">&larr;</span> Kembali
            </a>
            <button type="submit" class="btn-primary">
                Lanjut ke Step 3
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
    </form>
</div>
@endsection