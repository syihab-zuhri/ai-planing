@extends('layouts.app')

@section('title', 'BlueprintForge — Mulai Proyek Baru')

@section('content')
<main class="max-w-3xl mx-auto px-6 py-12">
    <header class="mb-10">
        <h1 class="text-4xl font-bold tracking-tight">BlueprintForge</h1>
        <p class="mt-3 text-lg text-slate-600">
            Buat blueprint proyek lengkap dalam hitungan menit.
        </p>
    </header>

    <section class="prose-slate">
        <p>
            Wizard singkat ini akan menanyakan beberapa hal tentang proyek Anda,
            lalu AI akan menghasilkan 18 dokumen Markdown sesuai standar PLANNING_v3.
        </p>
        <ul>
            <li>Langkah 1: Intake — nama, tujuan, pengguna, batasan.</li>
            <li>Langkah 2: Domain — kategori, masalah, value prop.</li>
            <li>Langkah 3: Scope — fitur P0/P1/P2.</li>
            <li>Langkah 4: Architecture — stack & hosting.</li>
            <li>Langkah 5: Clarify — jawab ≤ 5 pertanyaan material.</li>
        </ul>
    </section>

    <form method="POST" action="/api/wizard/start" data-turbo="false" class="mt-8">
        @csrf
        <button type="submit"
                class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-white hover:bg-slate-700">
            Mulai Proyek Baru
        </button>
    </form>

    <p class="mt-6 text-sm text-slate-500">
        Tidak perlu login. Disimpan per sesi (cookie HTTP-only).
    </p>
</main>
@endsection