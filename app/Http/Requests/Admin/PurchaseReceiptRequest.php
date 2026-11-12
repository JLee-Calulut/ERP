<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', Rule::exists('purchase_order_items', 'id')],
            'items.*.quantity_received' => ['required', 'integer', 'min:1'],
        ];
    }
}
