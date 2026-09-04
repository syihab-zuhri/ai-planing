<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi untuk Step 1 — Intake (API-WIZARD-INTAKE).
 *
 * Acuan: PRD/INTAKE.md §7 + API.md §6.
 * - project_name      : required, string, max 80, no < > `
 * - project_goal       : required, string, max 500
 * - target_users      : required, string, max 500
 * - known_constraints : nullable, string, max 500
 */
class StoreIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // session-based auth, sudah di-handle middleware
    }

    /**
     * Rules validasi.
     */
    public function rules(): array
    {
        return [
            'project_name' => [
                'required',
                'string',
                'max:80',
                // BR-INTAKE-001: larang < > ` (XSS ringan).
                'not_regex:/[<>`]/',
            ],
            'project_goal' => [
                'required',
                'string',
                'max:500',
            ],
            'target_users' => [
                'required',
                'string',
                'max:500',
            ],
            'known_constraints' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Pesan error custom (Bahasa Indonesia sesuai PRD/INTAKE.md §9 AC-INTAKE-001).
     */
    public function messages(): array
    {
        return [
            'project_name.required'    => 'Nama proyek wajib diisi.',
            'project_name.max'         => 'Nama proyek maksimal 80 karakter.',
            'project_name.not_regex'   => 'Nama proyek tidak boleh mengandung <, >, atau backtick.',
            'project_goal.required'    => 'Tujuan proyek wajib diisi.',
            'project_goal.max'         => 'Tujuan proyek maksimal 500 karakter.',
            'target_users.required'    => 'Calon pengguna wajib diisi.',
            'target_users.max'         => 'Calon pengguna maksimal 500 karakter.',
            'known_constraints.max'    => 'Batasan yang diketahui maksimal 500 karakter.',
        ];
    }

    /**
     * Field yang akan dipakai setelah validasi sukses.
     */
    public function intakeData(): array
    {
        return $this->only([
            'project_name',
            'project_goal',
            'target_users',
            'known_constraints',
        ]);
    }
}