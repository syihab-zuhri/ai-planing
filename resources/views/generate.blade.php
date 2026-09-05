@extends('layouts.app', ['title' => 'Generate Blueprint'])

@section('content')
<section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10" data-page="generatePage">
    <p class="text-xs font-semibold uppercase tracking-wider text-accent">Tahap 2</p>
    <h1 class="mt-2 text-3xl font-semibold text-primary">Generate Blueprint</h1>
    <p class="mt-3 text-text-muted">Bangun seluruh dokumen proyek dari data wizard. Setiap dokumen dikerjakan oleh worker di latar belakang, jadi halaman ini boleh ditinggalkan.</p>

        <div class="card p-6 border border-border rounded-lg shadow-sm bg-white" x-data="generateProgress()" x-init="init()">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-border">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-text">Paket Dokumentasi Terstruktur</h2>
                    <p class="text-sm text-text-muted">23 dokumen standar arsitektur: SRS, PRD, ERD, Security, Runbook, hingga ADR.</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-text-muted">AI Gateway Provider:</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm bg-bg-alt border border-border text-xs font-mono text-primary font-medium" x-text="provider || 'ninerouter'"></span>
                    </div>
                </div>
                <button type="button" class="btn-primary" @click="start"
                        :disabled="state === 'starting' || state === 'running'">
                    <span x-text="buttonLabel"></span>
                </button>
            </div>

            <div class="mt-6" aria-live="polite">
                <p x-show="error" class="p-4 rounded-md bg-red-50 border border-red-200 text-danger text-sm" x-text="error"></p>

                <div x-show="state === 'running' || state === 'done'" class="flex flex-col gap-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-text" x-text="`${completed} dari ${total} dokumen terselesaikan`"></span>
                        <span class="font-semibold text-primary" x-text="`${percent}%`"></span>
                    </div>
                    <div class="h-2.5 w-full rounded-full bg-slate-100 border border-slate-200 overflow-hidden"
                         role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full bg-primary transition-all duration-300 rounded-full" :style="`width: ${percent}%`"></div>
                    </div>

                    <ul class="mt-4 divide-y divide-border border border-border rounded-md max-h-96 overflow-y-auto bg-bg-alt">
                        <template x-for="doc in documents" :key="doc.doc_id">
                            <li class="flex items-center justify-between px-4 py-3 gap-3 bg-white hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-2 truncate">
                                    <span class="font-mono text-xs font-medium text-slate-800" x-text="doc.doc_id"></span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs text-text-muted font-mono" x-show="doc.chars > 0"
                                          x-text="`${doc.chars.toLocaleString()} char`"></span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-sm font-medium border"
                                          :class="doc.status === 'done' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (doc.status === 'running' ? 'bg-blue-50 text-primary border-blue-200 animate-pulse' : 'bg-slate-50 text-slate-600 border-slate-200')"
                                          x-text="statusLabel(doc.status)"></span>
                                    <button type="button" class="text-xs font-semibold text-accent hover:underline ml-1"
                                            x-show="doc.status === 'failed'"
                                            @click="retry(doc.doc_id)">Ulangi</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

                <div x-show="state === 'done' && failed === 0" class="mt-6 p-5 rounded-md bg-emerald-50 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold text-emerald-900 flex items-center gap-2">
                            <span class="size-2 rounded-full bg-emerald-600"></span>
                            Generate Selesai Sempurna
                        </p>
                        <p class="text-sm text-emerald-800 mt-0.5"><span x-text="completed" class="font-medium"></span> dokumen blueprint berhasil dibuat secara utuh.</p>
                    </div>
                    <a href="{{ url('/validate') }}" class="btn-primary shrink-0">Lanjut ke Validasi &rarr;</a>
                </div>

                <div x-show="state === 'done' && failed > 0" class="mt-6 p-5 rounded-md bg-amber-50 border border-amber-200">
                    <p class="font-semibold text-amber-900"><span x-text="failed"></span> dokumen perlu diperiksa</p>
                    <p class="text-sm text-amber-800 mt-1">Gunakan tombol "Ulangi" pada dokumen yang gagal, lalu lanjutkan ke tahap validasi.</p>
                </div>
            </div>
        </div>

    <div class="mt-6 flex justify-between">
        <a href="{{ url('/wizard/step/clarify') }}" class="btn-secondary">&larr; Kembali</a>
        <a href="{{ url('/validate') }}" class="btn-ghost">Buka Validasi &rarr;</a>
    </div>
</section>
@endsection
