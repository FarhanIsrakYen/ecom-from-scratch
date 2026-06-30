<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variant = $this->route('variant');
        $variantId = is_object($variant) ? $variant->getKey() : (int) $variant;

        return [
            'product_id' => ['sometimes', 'required', 'integer', Rule::exists('products', 'id')],
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'attributes' => ['sometimes', 'required', 'array'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
