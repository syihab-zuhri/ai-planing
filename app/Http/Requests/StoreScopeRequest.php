<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi untuk Step 3 — Scope (API-WIZARD-SCOPE).
 *
 * Acuan: PRD/INTAKE.md §7 + API.md §6.
 * - p0_features   : required list (min 1, max 10), each item max 200 chars
 * - p1_features   : optional list (max 10)
 * - p2_features   : optional list (max 10)
 * - out_of_scope  : optional list
 */
class StoreScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'p0_features'   => ['required', 'array', 'min:1', 'max:10'],
            'p0_features.*' => ['required', 'string', 'max:200'],

            'p1_features'   => ['nullable', 'array', 'max:10'],
            'p1_features.*' => ['required', 'string', 'max:200'],

            'p2_features'   => ['nullable', 'array', 'max:10'],
            'p2_features.*' => ['required', 'string', 'max:200'],

            'out_of_scope'  => ['nullable', 'array'],
            'out_of_scope.*'=> ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'p0_features.required'    => 'Minimal 1 fitur P0 wajib diisi.',
            'p0_features.max'         => 'Maksimal 10 fitur P0.',
            'p0_features.*.max'      => 'Setiap fitur P0 maksimal 200 karakter.',
            'p1_features.max'         => 'Maksimal 10 fitur P1.',
            'p2_features.max'         => 'Maksimal 10 fitur P2.',
        ];
    }

    public function scopeData(): array
    {
        return $this->only([
            'p0_features',
            'p1_features',
            'p2_features',
            'out_of_scope',
        ]);
    }
}
