<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:base_price'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'draft'])],
            'is_featured' => ['sometimes', 'boolean'],
            'variants' => ['sometimes', 'array'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:255', Rule::unique('product_variants', 'sku')],
            'variants.*.attributes' => ['required_with:variants', 'array'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*.image_path' => ['required_with:images', 'string', 'max:2048'],
            'images.*.is_primary' => ['sometimes', 'boolean'],
            'images.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
