<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplierId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', 'string', 'max:120'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'address_country' => ['nullable', 'string', 'max:120'],
        ];
    }
}
