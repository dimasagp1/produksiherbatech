<?php

namespace App\Http\Requests\PPIC;

use Illuminate\Foundation\Http\FormRequest;

class StoreMpsItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()?->hasRole('manager');
    }

    public function rules(): array
    {
        return [
            'tanggal' => 'required|date',
            'line_id' => 'nullable|exists:lines,id',
            'work_center_id' => 'nullable|exists:work_centers,id',
            'shift' => 'required|in:shift1,shift2',
            'mp_count' => 'nullable|integer|min:0',
            'target_qty' => 'nullable|integer|min:0',
            'cleaning' => 'boolean',
            'adjusted_qty' => 'nullable|integer|min:0',
            'gap_reason' => 'nullable|in:cleaning,capacity_limit,material_shortage,manual',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'shift.required' => 'Shift wajib dipilih.',
            'shift.in' => 'Shift harus shift1 atau shift2.',
            'gap_reason.in' => 'Alasan gap tidak valid.',
        ];
    }
}
