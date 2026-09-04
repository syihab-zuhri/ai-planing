{{--
  form-list — List builder dengan Alpine.js untuk add/remove item.
  Usage:
    <x-form-list name="p0_features" label="Fitur P0 (Wajib)" :required="true"
                 helper="Minimal 1, maksimal 10 item."
                 :initial="['Autentikasi pengguna', 'Dashboard ringkasan']"
                 :max="10" />

  Props:
    - name         (string, required)
    - label        (string, optional)
    - initial      (array, optional) — array string untuk hydrate
    - placeholder  (string, optional)
    - helper       (string, optional)
    - required     (bool, optional)
    - max          (int, optional, default Infinity)
    - min          (int, optional, default 0)
    - error        (string|null, optional)
--}}

@props([
    'name',
    'label' => null,
    'initial' => [],
    'placeholder' => 'Ketik item lalu tekan Enter atau klik Tambah',
    'helper' => null,
    'required' => false,
    'max' => null,
    'min' => 0,
    'error' => null,
])

@php
    $listId = 'fl-' . $name;
    $hasError = !empty($error);
    $max = is_null($max) ? 'Infinity' : (int)$max;
    $initialJson = json_encode(array_values(array_filter((array) $initial, fn($v) => $v !== null && $v !== '')));
@endphp

<div class="mb-4"
     x-data='listBuilder({ initial: {{ $initialJson }}, max: {{ $max }} })'>
    @if($label)
        <label class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="sr-only">(wajib diisi)</span>
            @endif
        </label>
    @endif

    <ul class="space-y-2" role="list">
        <template x-for="(item, idx) in items" :key="idx">
            <li class="flex items-center gap-2">
                <span class="text-text-muted text-sm font-mono w-6 text-right" aria-hidden="true" x-text="(idx + 1) + '.'"></span>
                <input type="text"
                       :name="'{{ $name }}[' + idx + ']'"
                       x-model="items[idx]"
                       placeholder="{{ $placeholder }}"
                       class="input-field flex-1"
                       aria-label="Item {{ '{' }}{{ $name }}{{ '}' }} nomor {{ '{' }}idx + 1{{ '}' }}">
                <button type="button"
                        @click="remove(idx)"
                        class="btn-ghost px-2 py-2 text-danger hover:bg-bg-alt"
                        aria-label="Hapus item nomor {{ '{' }}idx + 1{{ '}' }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </li>
        </template>
    </ul>

    <div class="mt-3">
        <button type="button"
                @click="add()"
                :disabled="items.length >= {{ $max }}"
                class="btn-secondary text-sm">
            <span aria-hidden="true">+</span>
            <span>Tambah item</span>
        </button>
        <span class="ml-2 text-xs text-text-muted" aria-live="polite">
            <span x-text="items.length"></span> item
            @if($max !== 'Infinity')
                <span>/ maks {{ $max }}</span>
            @endif
        </span>
    </div>

    @if($hasError)
        <p class="form-error" role="alert">{{ $error }}</p>
    @elseif($helper)
        <p class="form-helper">{{ $helper }}</p>
    @endif
</div>