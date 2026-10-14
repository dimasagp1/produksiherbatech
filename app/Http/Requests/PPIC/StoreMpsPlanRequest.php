<?php

namespace App\Http\Requests\PPIC;

use Illuminate\Foundation\Http\FormRequest;

class StoreMpsPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()?->hasRole('manager');
    }

    public function rules(): array
    {
        return [
            'produk_id' => 'required|exists:produks,id',
            'work_center_id' => 'required|exists:work_centers,id',
            'month_year' => 'required|date_format:Y-m',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'produk_id.required' => 'Produk wajib dipilih.',
            'work_center_id.required' => 'Work Center wajib dipilih.',
            'month_year.required' => 'Bulan & Tahun wajib diisi.',
            'month_year.date_format' => 'Format Bulan & Tahun harus YYYY-MM.',
        ];
    }
}
