@extends('layouts.app', ['title' => 'Generator Blueprint Proyek'])

@section('content')
<div class="relative overflow-hidden bg-gradient-to-b from-slate-50 via-white to-slate-50/50 py-12 sm:py-20 border-b border-border">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header & Badge --}}
        <div class="text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200/80 text-primary text-xs font-semibold uppercase tracking-wider mb-6 shadow-sm">
                <span class="size-2 rounded-full bg-primary animate-pulse"></span>
                Standar Perencanaan PLANNING_v3
            </div>
            
            <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-slate-900 leading-tight">
                Blueprint Arsitektur Software<br class="hidden sm:block">
                <span class="text-primary font-extrabold">Terstruktur & Siap Eksekusi</span>
            </h1>
            
            <p class="mt-4 text-base sm:text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Panduan wizard interaktif untuk merumuskan ide produk Anda menjadi paket spesifikasi teknis lengkap, akurat, dan konsisten dalam waktu kurang dari 30 menit.
            </p>
        </div>

        {{-- Steps Card (Shadcn Style) --}}
        <div class="card mt-12 p-6 sm:p-8 bg-white border border-border rounded-xl shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-border gap-2">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Alur Pengisian Wizard</h2>
                    <p class="text-sm text-text-muted">Sistem AI akan menganalisis parameter proyek Anda dan memproduksi 23 dokumen arsitektur.</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-sm bg-slate-100 text-slate-700 text-xs font-medium self-start sm:self-auto">
                    5 Langkah Terpadu
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
                {{-- Step 1 --}}
                <div class="p-4 rounded-lg bg-slate-50/80 border border-slate-100 flex flex-col justify-between hover:bg-slate-50 transition-colors">
                    <div>
                        <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                            <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">1</span>
                            Intake
                        </div>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                            Definisi identitas: nama proyek, tujuan utama, profil pengguna target, dan batasan teknis/konektivitas.
                        </p>
                    </div>
                </div>

                {{-- Step 2 --}}
                <div class="p-4 rounded-lg bg-slate-50/80 border border-slate-100 flex flex-col justify-between hover:bg-slate-50 transition-colors">
                    <div>
                        <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                            <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">2</span>
                            Domain
                        </div>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                            Fondasi masalah: kategori industri, rumusan masalah, proporsi nilai, dan proyeksi skala pengguna.
                        </p>
                    </div>
                </div>

                {{-- Step 3 --}}
                <div class="p-4 rounded-lg bg-slate-50/80 border border-slate-100 flex flex-col justify-between hover:bg-slate-50 transition-colors">
                    <div>
                        <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                            <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">3</span>
                            Scope
                        </div>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                            Penetapan ruang lingkup: rincian fitur esensial P0, penunjang P1/P2, dan batasan eksplisit out-of-scope.
                        </p>
                    </div>
                </div>

                {{-- Step 4 --}}
                <div class="p-4 rounded-lg bg-slate-50/80 border border-slate-100 flex flex-col justify-between hover:bg-slate-50 transition-colors">
                    <div>
                        <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                            <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">4</span>
                            Architecture
                        </div>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                            Keputusan teknis: stack teknologi pilihan, infrastruktur hosting, database, dan sensitivitas data.
                        </p>
                    </div>
                </div>

                {{-- Step 5 --}}
                <div class="p-4 rounded-lg bg-slate-50/80 border border-slate-100 flex flex-col justify-between hover:bg-slate-50 transition-colors sm:col-span-2 lg:col-span-2">
                    <div>
                        <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                            <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">5</span>
                            Clarification
                        </div>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                            Penyempurnaan otomatis: jawab hingga 5 pertanyaan material dari AI untuk mengeliminasi celah asumsi sebelum blueprint digenerate.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Action CTA --}}
            <div class="mt-8 pt-6 border-t border-border flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <form method="POST" action="/wizard/start" class="m-0">
                    @csrf
                    <button type="submit" class="btn-primary px-6 py-3 text-base shadow-sm hover:shadow transition-all w-full sm:w-auto">
                        Mulai Proyek Baru &rarr;
                    </button>
                </form>

                <div class="flex items-center gap-2 text-xs text-text-muted">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    Sesi otomatis tersimpan aman di browser Anda tanpa perlu login.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
