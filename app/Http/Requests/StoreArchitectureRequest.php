<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi untuk Step 4 — Architecture (API-WIZARD-ARCHITECTURE).
 *
 * Acuan: PRD/INTAKE.md §7.
 * - preferred_stack     : select Laravel+Blade|Node+React|Other|Saran sistem
 * - hosting_preference  : select WSL|VPS|Cloud|Saran sistem
 * - known_integrations  : optional list
 * - data_sensitivity    : select Public|Internal|Confidential|Restricted
 */
class StoreArchitectureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferred_stack' => [
                'required',
                'string',
                'in:Laravel+Blade,Node+React,Other,Saran sistem',
            ],
            'hosting_preference' => [
                'required',
                'string',
                'in:WSL,VPS,Cloud,Saran sistem',
            ],
            'known_integrations' => ['nullable', 'array'],
            'known_integrations.*' => ['required', 'string', 'max:200'],

            'data_sensitivity' => [
                'required',
                'string',
                'in:Public,Internal,Confidential,Restricted',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'preferred_stack.required'    => 'Stack pilihan wajib diisi.',
            'preferred_stack.in'          => 'Stack pilihan tidak valid.',
            'hosting_preference.required' => 'Hosting pilihan wajib diisi.',
            'hosting_preference.in'       => 'Hosting pilihan tidak valid.',
            'data_sensitivity.required'   => 'Sensitivitas data wajib dipilih.',
            'data_sensitivity.in'         => 'Sensitivitas data tidak valid.',
        ];
    }

    public function architectureData(): array
    {
        return $this->only([
            'preferred_stack',
            'hosting_preference',
            'known_integrations',
            'data_sensitivity',
        ]);
    }
}
