{{--
  form-input — Text input atau textarea dengan label, helper, dan error.
  Usage:
    <x-form-input name="project_name" label="Nama Proyek" :required="true"
                  placeholder="Mis. Sistem Absensi" helper="Maks 80 karakter" />

    <x-form-input name="project_goal" label="Tujuan Proyek" type="textarea"
                  :required="true" :rows="4"
                  helper="Jelaskan tujuan utama proyek dalam 1–2 paragraf." />

  Props:
    - name         (string, required) — name attribute field
    - label        (string, optional)
    - type         ('text' | 'textarea', default 'text')
    - value        (string, optional) — initial value
    - placeholder  (string, optional)
    - helper       (string, optional)
    - required     (bool, optional)
    - maxlength    (int, optional)
    - rows         (int, default 4) untuk textarea
    - error        (string|null, optional) — dari server-side validation
--}}

@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'helper' => null,
    'required' => false,
    'maxlength' => null,
    'rows' => 4,
    'error' => null,
])

@php
    $inputId = 'fi-' . $name;
    $hasError = !empty($error);
@endphp

<div class="mb-4" x-data="{ val: @js(old($name, $value)) }">
    @if($label)
        <label for="{{ $inputId }}" class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="sr-only">(wajib diisi)</span>
            @endif
        </label>
    @endif

    @if($type === 'textarea')
        <textarea id="{{ $inputId }}"
                  name="{{ $name }}"
                  x-model="val"
                  rows="{{ $rows }}"
                  placeholder="{{ $placeholder }}"
                  @if($required) required aria-required="true" @endif
                  @if($maxlength) maxlength="{{ $maxlength }}" @endif
                  @if($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                  class="input-field {{ $hasError ? 'is-invalid' : '' }}"></textarea>
    @else
        <input id="{{ $inputId }}"
               type="{{ $type }}"
               name="{{ $name }}"
               x-model="val"
               value="{{ old($name, $value) }}"
               placeholder="{{ $placeholder }}"
               @if($required) required aria-required="true" @endif
               @if($maxlength) maxlength="{{ $maxlength }}" @endif
               @if($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
               class="input-field {{ $hasError ? 'is-invalid' : '' }}">
    @endif

    @if($hasError)
        <p id="{{ $inputId }}-error" class="form-error" role="alert">{{ $error }}</p>
    @elseif($helper)
        <p class="form-helper">{{ $helper }}</p>
    @endif
</div>