<?php

namespace Condoedge\Finance\Models\Dto\Products;

/**
 * Update Product DTO
 *
 * Used to update existing products. All fields are optional except the product ID.
 *
 * @property int $id Product ID to update (required)
 */
class UpdateProductDto extends CreateProductDto
{
    public int $id;

    /**
     * Validation rules for updating a product
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['id'] = 'required|integer|exists:fin_products,id';

        // Make all fields optional for update except id
        foreach ($rules as $field => $rule) {
            if ($field !== 'id') {
                if (is_string($rule)) {
                    // Convert 'required' to 'sometimes' for update
                    $rules[$field] = str_replace('required', 'sometimes', $rule);
                } elseif (is_array($rule)) {
                    // If it's an array, ensure 'required' is replaced with 'sometimes'
                    $rules[$field] = array_map(function ($r) {
                        if (is_string($r)) {
                            return str_replace('required', 'sometimes', $r);
                        }
                    }, $rule);
                }
            }
        }

        return $rules;
    }
}
