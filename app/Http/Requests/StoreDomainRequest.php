<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi untuk Step 2 — Domain (API-WIZARD-DOMAIN).
 *
 * Acuan: PRD/INTAKE.md §7.
 * - domain_category     : select Web|Mobile|API|Internal Tool|Other
 * - problem_statement   : required, max 500
 * - value_proposition   : required, max 300
 * - scale_estimate_mvp  : select <100|100-1k|1k-10k|10k+
 * - scale_estimate_12mo : select sama seperti di atas
 */
class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain_category' => [
                'required',
                'string',
                'in:Web,Mobile,API,Internal Tool,Other',
            ],
            'problem_statement' => [
                'required',
                'string',
                'max:500',
            ],
            'value_proposition' => [
                'required',
                'string',
                'max:300',
            ],
            'scale_estimate_mvp' => [
                'required',
                'string',
                'in:<100,100-1k,1k-10k,10k+',
            ],
            'scale_estimate_12mo' => [
                'required',
                'string',
                'in:<100,100-1k,1k-10k,10k+',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'domain_category.required'    => 'Kategori domain wajib dipilih.',
            'domain_category.in'          => 'Kategori domain tidak valid.',
            'problem_statement.required'  => 'Pernyataan masalah wajib diisi.',
            'problem_statement.max'       => 'Pernyataan masalah maksimal 500 karakter.',
            'value_proposition.required'  => 'Value proposition wajib diisi.',
            'value_proposition.max'       => 'Value proposition maksimal 300 karakter.',
            'scale_estimate_mvp.required' => 'Estimasi skala MVP wajib dipilih.',
            'scale_estimate_mvp.in'       => 'Estimasi skala MVP tidak valid.',
            'scale_estimate_12mo.required'=> 'Estimasi skala 12 bulan wajib dipilih.',
            'scale_estimate_12mo.in'      => 'Estimasi skala 12 bulan tidak valid.',
        ];
    }

    public function domainData(): array
    {
        return $this->only([
            'domain_category',
            'problem_statement',
            'value_proposition',
            'scale_estimate_mvp',
            'scale_estimate_12mo',
        ]);
    }
}
