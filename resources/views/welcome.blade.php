@extends('layouts.app', ['title' => 'Beranda'])

@section('content')
    {{-- ==========================================================
         Hero
         ========================================================== --}}
    <section class="bg-gradient-to-b from-bg-alt to-white border-b border-border">
        <div class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-20 text-center">
            <p class="inline-block px-3 py-1 text-xs font-medium text-primary bg-white border border-border rounded-sm mb-6">
                Generator Blueprint Proyek
            </p>

            <h1 class="text-3xl sm:text-4xl font-bold text-text mb-4 leading-tight">
                Blueprint proyek perangkat lunak
                <br class="hidden sm:block">
                terstruktur dalam <span class="text-primary">&lt;30 menit</span>.
            </h1>

            <p class="text-base sm:text-lg text-text-muted max-w-xl mx-auto mb-8">
                Isi 5 langkah wizard singkat, sistem AI menghasilkan 18 dokumen
                arsitektur &amp; perencanaan sesuai standar
                <span class="font-medium text-primary">PLANNING_v3</span>.
            </p>

            <form method="POST" action="{{ url('/wizard/start') }}" class="inline-block">
                @csrf
                <button type="submit" class="btn-primary text-base px-6 py-3">
                    Mulai Proyek Baru
                    <span aria-hidden="true">&rarr;</span>
                </button>
            </form>

            <p class="mt-4 text-xs text-text-muted">
                Tidak perlu akun. Data tersimpan di sesi peramban Anda.
            </p>
        </div>
    </section>

    {{-- ==========================================================
         Bagaimana cara kerja
         ========================================================== --}}
    <section class="bg-white" aria-labelledby="how-it-works-heading">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <h2 id="how-it-works-heading" class="text-2xl font-semibold text-text text-center mb-10">
                Bagaimana cara kerja
            </h2>

            <ol class="grid grid-cols-1 md:grid-cols-3 gap-6" role="list">
                <li class="card card-hover">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="flex items-center justify-center w-8 h-8 text-sm font-semibold text-white bg-primary rounded-full" aria-hidden="true">1</span>
                        <h3 class="text-lg font-semibold text-text">Isi wizard 5 langkah</h3>
                    </div>
                    <p class="text-sm text-text-muted">
                        Jawab pertanyaan singkat tentang ide, domain, lingkup fitur,
                        dan arsitektur pilihan Anda. Sekitar 5–10 menit.
                    </p>
                </li>

                <li class="card card-hover">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="flex items-center justify-center w-8 h-8 text-sm font-semibold text-white bg-primary rounded-full" aria-hidden="true">2</span>
                        <h3 class="text-lg font-semibold text-text">AI generate 18 dokumen</h3>
                    </div>
                    <p class="text-sm text-text-muted">
                        Sistem menyusun blueprint lengkap: <span class="font-mono text-text">PLANNING.md</span>,
                        SRS, ERD, arsitektur, ADR, dan lainnya.
                    </p>
                </li>

                <li class="card card-hover">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="flex items-center justify-center w-8 h-8 text-sm font-semibold text-white bg-primary rounded-full" aria-hidden="true">3</span>
                        <h3 class="text-lg font-semibold text-text">Export ZIP</h3>
                    </div>
                    <p class="text-sm text-text-muted">
                        Unduh seluruh paket blueprint sebagai arsip ZIP siap
                        di-commit ke repositori proyek Anda.
                    </p>
                </li>
            </ol>
        </div>
    </section>

    {{-- ==========================================================
         Tagline / standar
         ========================================================== --}}
    <section class="bg-bg-alt border-t border-border">
        <div class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-10 text-center">
            <p class="text-sm text-text-muted mb-2">Standar yang dipakai</p>
            <p class="text-xl font-semibold text-primary font-mono">PLANNING_v3.md</p>
            <p class="mt-3 text-sm text-text-muted">
                Dirancang untuk freelancer &amp; tim kecil yang ingin bergerak cepat tanpa
                mengorbankan kualitas dokumentasi.
            </p>
        </div>
    </section>
@endsection