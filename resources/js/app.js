import './bootstrap';
import Alpine from 'alpinejs';

// Axios default (sudah di-set di bootstrap.js, tapi re-affirm untuk fetch helper)
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/* ---------------------------------------------------------------
   BlueprintForge helpers
   --------------------------------------------------------------- */

/**
 * Ambil CSRF token dari meta tag (Laravel default).
 * @returns {string}
 */
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Submit form via fetch dengan CSRF otomatis.
 * Mengembalikan Promise<{ok: boolean, status: number, data: any}>.
 *
 * @param {HTMLFormElement|string} target  Form element atau selector.
 * @param {object} options
 * @param {string} options.url      Endpoint URL.
 * @param {string} [options.method='POST']
 * @returns {Promise<{ok: boolean, status: number, data: any}>}
 */
async function submitForm(target, { url, method = 'POST' } = {}) {
    const form = typeof target === 'string' ? document.querySelector(target) : target;
    if (!form) {
        return { ok: false, status: 0, data: { error: 'Form tidak ditemukan.' } };
    }

    const formData = new FormData(form);
    try {
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData,
        });
        let data = null;
        try {
            data = await res.json();
        } catch (_) {
            data = null;
        }
        return { ok: res.ok, status: res.status, data };
    } catch (err) {
        return { ok: false, status: 0, data: { error: 'Gagal terhubung ke server.' } };
    }
}

/**
 * Auto-save indicator — debounce 1 detik (DSD §6, PRD INTAKE §10).
 *
 * @param {object} cfg
 * @param {HTMLFormElement} cfg.form
 * @param {string} cfg.url
 * @param {(state: 'saving'|'saved'|'idle'|'error', ago?: number) => void} cfg.onState
 */
function bindAutoSave({ form, url, onState }) {
    if (!form) return () => {};
    let timer = null;
    let lastSavedAt = 0;
    let inFlight = false;

    const trigger = () => {
        if (timer) clearTimeout(timer);
        onState && onState('saving');
        timer = setTimeout(async () => {
            if (inFlight) return;
            inFlight = true;
            try {
                const result = await submitForm(form, { url });
                if (result.ok) {
                    lastSavedAt = Date.now();
                    onState && onState('saved', 0);
                } else {
                    onState && onState('error');
                }
            } catch (_) {
                onState && onState('error');
            } finally {
                inFlight = false;
            }
        }, 1000);
    };

    form.addEventListener('input', trigger);
    form.addEventListener('change', trigger);

    // Return disposer + tick() untuk update "X detik lalu"
    const tickInterval = setInterval(() => {
        if (lastSavedAt > 0) {
            const ago = Math.floor((Date.now() - lastSavedAt) / 1000);
            onState && onState('saved', ago);
        }
    }, 5000);

    return () => {
        if (timer) clearTimeout(timer);
        clearInterval(tickInterval);
        form.removeEventListener('input', trigger);
        form.removeEventListener('change', trigger);
    };
}

/* ---------------------------------------------------------------
   Alpine components — global registration agar Blade bisa pakai
   <div x-data="autoSaveForm({...})"> dll.
   --------------------------------------------------------------- */
Alpine.data('autoSaveForm', (config = {}) => ({
    state: 'idle',        // 'idle' | 'saving' | 'saved' | 'error'
    savedAgo: null,
    submitting: false,
    errors: null,
    init() {
        const form = this.$el.matches('form') ? this.$el : this.$el.querySelector('form');
        bindAutoSave({
            form,
            url: config.url || form?.action || '/api/wizard/intake',
            onState: (s, ago) => {
                this.state = s;
                if (typeof ago === 'number') this.savedAgo = ago;
            },
        });
    },
    async submitAndContinue(nextUrl) {
        const form = this.$el.matches('form') ? this.$el : this.$el.querySelector('form');
        this.submitting = true;
        this.errors = null;
        const result = await submitForm(form, { url: config.url || form?.action });
        this.submitting = false;
        if (result.ok) {
            window.location.assign(result.data?.next || nextUrl);
            return;
        }
        this.state = 'error';
        this.errors = result.data?.errors || result.data?.error || { form: ['Gagal menyimpan data.'] };
    },
    label() {
        if (this.state === 'idle') return config.idleLabel || 'Belum ada perubahan';
        if (this.state === 'saving') return 'Menyimpan...';
        if (this.state === 'error') return 'Gagal menyimpan';
        if (this.state === 'saved') {
            const a = this.savedAgo;
            if (a === null || a === undefined || a === 0) return 'Tersimpan';
            return `Tersimpan ${a} detik lalu`;
        }
        return '';
    },
}));

Alpine.data('phaseAction', ({ endpoint, nextUrl = null, download = false }) => ({
    state: 'idle',
    result: null,
    error: null,
    async run() {
        this.state = 'loading';
        this.error = null;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (!response.ok) {
                this.error = data?.error?.message || data?.message || 'Permintaan gagal.';
                this.state = 'error';
                return;
            }
            this.result = data;
            this.state = 'success';
            if (download && data.download_url) {
                this.result.download_url = data.download_url;
            }
        } catch (_) {
            this.error = 'Gagal terhubung ke server.';
            this.state = 'error';
        }
    },
    continueNext() {
        if (nextUrl) window.location.assign(nextUrl);
    },
}));

// Generate page: dispatch batch lalu pantau progres.
// Prioritas SSE (/api/generate/stream); jatuh ke polling /api/generate/status
// bila SSE gagal (PRD/GENERATION.md §10).
Alpine.data('generateProgress', () => ({
    state: 'idle',            // idle | starting | running | done | error
    documents: [],
    provider: null,
    total: 0,
    completed: 0,
    failed: 0,
    error: null,
    _source: null,
    _timer: null,

    get percent() {
        if (!this.total) return 0;
        return Math.round((this.completed / this.total) * 100);
    },

    get buttonLabel() {
        if (this.state === 'starting') return 'Mengirim ke antrean...';
        if (this.state === 'running') return 'Sedang berjalan...';
        if (this.state === 'done') return 'Generate ulang';
        return 'Mulai Generate';
    },

    async init() {
        // Muat status awal; kalau batch sudah pernah jalan, tampilkan langsung.
        const snapshot = await this.fetchStatus();
        if (snapshot && snapshot.counts) {
            const started = snapshot.total - (snapshot.counts.pending || 0);
            if (started > 0) {
                this.state = snapshot.finished ? 'done' : 'running';
                if (!snapshot.finished) this.watch();
            }
        }
    },

    async start() {
        this.state = 'starting';
        this.error = null;

        try {
            const response = await fetch('/api/generate/start', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();

            if (!response.ok) {
                this.error = data?.error?.message || data?.message || 'Permintaan gagal.';
                this.state = 'error';
                return;
            }

            this.total = data.total || this.total;
            this.provider = data.provider || this.provider;
            this.state = 'running';
            await this.fetchStatus();
            this.watch();
        } catch (_) {
            this.error = 'Gagal terhubung ke server.';
            this.state = 'error';
        }
    },

    async retry(docId) {
        this.error = null;
        try {
            await fetch(`/api/generate/retry/${encodeURIComponent(docId)}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            this.state = 'running';
            this.watch();
        } catch (_) {
            this.error = 'Gagal mengirim permintaan ulang.';
        }
    },

    // SSE dulu; kalau error, polling.
    watch() {
        this.stop();

        if (typeof window.EventSource === 'function') {
            try {
                const source = new EventSource('/api/generate/stream', { withCredentials: true });
                this._source = source;

                source.addEventListener('progress', () => this.fetchStatus());
                source.addEventListener('error', () => this.fetchStatus());
                source.addEventListener('complete', () => {
                    this.fetchStatus().then(() => {
                        this.state = 'done';
                        this.stop();
                    });
                });
                source.onerror = () => {
                    // Koneksi SSE putus/diblokir proxy → fallback polling.
                    this.stop();
                    this.poll();
                };
                return;
            } catch (_) {
                // jatuh ke polling
            }
        }

        this.poll();
    },

    poll() {
        this.stop();
        this._timer = setInterval(async () => {
            const snapshot = await this.fetchStatus();
            if (!snapshot || snapshot.finished) {
                this.state = 'done';
                this.stop();
            }
        }, 3000);
    },

    stop() {
        if (this._source) {
            this._source.close();
            this._source = null;
        }
        if (this._timer) {
            clearInterval(this._timer);
            this._timer = null;
        }
    },

    async fetchStatus() {
        try {
            const response = await fetch('/api/generate/status', {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) return null;

            const data = await response.json();
            this.documents = data.documents || [];
            this.provider = data.provider || this.provider;
            this.total = data.total || 0;
            this.completed = (data.counts?.done || 0) + (data.counts?.failed || 0) + (data.counts?.cancelled || 0);
            this.failed = data.counts?.failed || 0;

            if (data.finished && this.state === 'running') {
                this.state = 'done';
                this.stop();
            }

            return data;
        } catch (_) {
            return null;
        }
    },

    statusLabel(status) {
        return {
            pending: 'Menunggu',
            queued: 'Antrean',
            running: 'Proses',
            done: 'Selesai',
            failed: 'Gagal',
            cancelled: 'Dibatalkan',
        }[status] || status;
    },

    statusClass(status) {
        return {
            done: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
            running: 'bg-blue-100 text-blue-800',
            queued: 'bg-slate-100 text-slate-700',
            pending: 'bg-slate-100 text-slate-500',
            cancelled: 'bg-amber-100 text-amber-800',
        }[status] || 'bg-slate-100 text-slate-700';
    },
}));

// List builder helper (dipakai component form-list)
Alpine.data('listBuilder', (config = {}) => ({
    items: Array.isArray(config.initial) ? [...config.initial] : [''],
    max: typeof config.max === 'number' ? config.max : Infinity,
    add() {
        if (this.items.length < this.max) this.items.push('');
    },
    remove(idx) {
        if (this.items.length <= 1) {
            this.items[0] = '';
            return;
        }
        this.items.splice(idx, 1);
    },
}));

window.BlueprintForge = {
    csrfToken,
    submitForm,
    bindAutoSave,
};

// Expose ke window untuk akses dari Blade x-data inline juga
window.csrfToken = csrfToken;

// Init Alpine terakhir (penting)
window.Alpine = Alpine;
Alpine.start();