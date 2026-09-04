{{--
  form-select — Select dropdown dengan label, helper, dan error.
  Usage:
    <x-form-select name="domain_category" label="Kategori Domain" :required="true"
                  :options="['web' => 'Web', 'mobile' => 'Mobile', 'api' => 'API', 'internal_tool' => 'Internal Tool', 'other' => 'Lainnya']"
                  placeholder="— Pilih kategori —" />

  Props:
    - name         (string, required)
    - label        (string, optional)
    - options      (array, required) — key => label
    - value        (string, optional) — selected key
    - placeholder  (string, optional) — empty option label
    - helper       (string, optional)
    - required     (bool, optional)
    - error        (string|null, optional)
--}}

@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'helper' => null,
    'required' => false,
    'error' => null,
])

@php
    $inputId = 'fs-' . $name;
    $hasError = !empty($error);
    $selected = old($name, $value);
@endphp

<div class="mb-4">
    @if($label)
        <label for="{{ $inputId }}" class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="sr-only">(wajib diisi)</span>
            @endif
        </label>
    @endif

    <select id="{{ $inputId }}"
            name="{{ $name }}"
            @if($required) required aria-required="true" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
            class="input-field {{ $hasError ? 'is-invalid' : '' }}">
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $key => $optLabel)
            <option value="{{ $key }}" @selected((string)$selected === (string)$key)>{{ $optLabel }}</option>
        @endforeach
    </select>

    @if($hasError)
        <p id="{{ $inputId }}-error" class="form-error" role="alert">{{ $error }}</p>
    @elseif($helper)
        <p class="form-helper">{{ $helper }}</p>
    @endif
</div>