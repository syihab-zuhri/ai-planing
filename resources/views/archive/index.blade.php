@extends('layouts.app', ['title' => 'Arsip Blueprint & Unduhan'])

@section('content')
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-border">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-blue-50 border border-blue-200 text-primary text-xs font-semibold uppercase tracking-wider mb-2">
                <span class="size-2 rounded-full bg-primary"></span>
                Arsip Proyek
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">Daftar Blueprint & File Download</h1>
            <p class="mt-1 text-sm text-text-muted">Semua paket arsitektur yang pernah di-generate dan siap diunduh kembali.</p>
        </div>

        <a href="{{ url('/wizard') }}" class="btn-primary self-start sm:self-auto">
            + Buat Blueprint Baru
        </a>
    </div>

    {{-- Project Cards Grid --}}
    <div class="mt-8">
        @if ($projects->isEmpty())
            <div class="card p-12 text-center bg-white border border-border rounded-xl">
                <div class="size-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-2xl mb-4">
                    📁
                </div>
                <h3 class="text-base font-semibold text-slate-900">Belum Ada Proyek yang Di-generate</h3>
                <p class="text-sm text-text-muted mt-1 max-w-md mx-auto">
                    Mulai wizard perencanaan untuk menghasilkan 23 dokumen arsitektur software dan mengunduhnya dalam format ZIP.
                </p>
                <div class="mt-6">
                    <a href="{{ url('/wizard') }}" class="btn-primary">Mulai Wizard Sekarang &rarr;</a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($projects as $item)
                    <div class="card p-6 bg-white border border-border rounded-xl shadow-sm hover:shadow transition-all flex flex-col justify-between">
                        <div>
                            {{-- Card Header --}}
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <h2 class="text-base font-bold text-slate-900 line-clamp-1" title="{{ $item['name'] }}">
                                    {{ $item['name'] }}
                                </h2>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-semibold tracking-wide border {{ $item['gate'] === 'A' ? 'bg-slate-50 text-slate-700 border-slate-200' : 'bg-blue-50 text-primary border-blue-200' }}">
                                    Gate {{ $item['gate'] }}
                                </span>
                            </div>

                            <p class="text-xs text-text-muted line-clamp-2 mb-4 leading-relaxed">
                                {{ $item['goal'] }}
                            </p>

                            {{-- Stats --}}
                            <div class="flex items-center gap-4 py-3 border-y border-slate-100 text-xs text-slate-600 mb-4">
                                <div class="flex items-center gap-1.5 font-medium">
                                    <span class="text-slate-400">📄</span>
                                    <span>{{ $item['docs_count'] }} Dokumen</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-slate-400">🕒</span>
                                    <span>{{ $item['last_activity']->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="pt-2 flex items-center justify-between gap-2">
                            <a href="{{ url('/archive/download/' . $item['id']) }}"
                               class="btn-primary w-full text-center text-xs py-2">
                                ⬇ Unduh ZIP
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
