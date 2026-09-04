@extends('layouts.app', ['title' => 'Tentang BlueprintForge'])

@section('content')
<section class="max-w-wizard mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <header class="mb-6">
        <h1 class="text-2xl font-semibold text-text mb-2">Tentang BlueprintForge</h1>
        <p class="text-sm text-text-muted">
            Mengubah ide singkat menjadi blueprint proyek yang siap dieksekusi.
        </p>
    </header>

    <article class="prose-card card space-y-4 text-sm text-text leading-relaxed">
        <p>
            <span class="font-medium text-primary">BlueprintForge</span> adalah generator
            blueprint proyek perangkat lunak yang mengikuti standar
            <span class="font-mono text-primary">PLANNING_v3</span>. Dirancang untuk
            freelancer dan tim kecil yang ingin bergerak cepat tanpa mengorbankan
            kualitas dokumentasi.
        </p>

        <h2 class="text-lg font-semibold text-text pt-2">Apa yang dihasilkan</h2>
        <ul class="list-disc list-inside space-y-1 text-text-muted">
            <li><span class="font-mono text-text">PLANNING.md</span> — ringkasan proyek</li>
            <li><span class="font-mono text-text">SRS.md</span> — software requirements</li>
            <li><span class="font-mono text-text">PRD/INTAKE.md</span>, <span class="font-mono text-text">PRD/CLARIFICATION.md</span>, <span class="font-mono text-text">PRD/GENERATION.md</span>, <span class="font-mono text-text">PRD/VALIDATION.md</span>, <span class="font-mono text-text">PRD/EXPORT.md</span></li>
            <li><span class="font-mono text-text">ARCHITECTURE.md</span> &amp; <span class="font-mono text-text">ADR/</span></li>
            <li><span class="font-mono text-text">ERD.md</span>, <span class="font-mono text-text">API.md</span></li>
            <li><span class="font-mono text-text">TESTING.md</span>, <span class="font-mono text-text">SECURITY.md</span>, <span class="font-mono text-text">RUNBOOK.md</span></li>
            <li>dan lainnya — total 18 dokumen.</li>
        </ul>

        <h2 class="text-lg font-semibold text-text pt-2">Bagaimana alurnya</h2>
        <ol class="list-decimal list-inside space-y-1 text-text-muted">
            <li>Isi wizard 5 langkah.</li>
            <li>Sistem menghasilkan 18 dokumen.</li>
            <li>Validasi gate otomatis + review manual.</li>
            <li>Export ZIP siap commit.</li>
        </ol>

        <div class="pt-4">
            <a href="{{ url('/') }}" class="btn-primary">
                <span aria-hidden="true">&larr;</span> Kembali ke beranda
            </a>
        </div>
    </article>
</section>
@endsection