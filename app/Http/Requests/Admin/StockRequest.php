<?php

namespace App\Http\Requests\Admin;

use App\Models\StockTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class StockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'type' => ['required', Rule::in(StockTransaction::TYPES)],
            'quantity' => $this->quantityRules(),
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Quantity is at least 1 for in/out movements; an adjustment may set the
     * stock level to zero.
     */
    private function quantityRules(): array
    {
        $type = $this->input('type');

        $in = new In(StockTransaction::TYPES);

        if ($type === StockTransaction::TYPE_ADJUSTMENT) {
            return ['required', 'integer'];
        }

        return ['required', 'integer', 'min:1'];
    }
}
