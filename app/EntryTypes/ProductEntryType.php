<?php

namespace App\EntryTypes;

use App\Models\Entry;

class ProductEntryType extends AbstractEntryType
{
    /**
     * Normalise pricing fields on create.
     */
    public function beforeCreate(array $data): array
    {
        return $this->normalisePricing($data);
    }

    /**
     * Normalise pricing fields and auto-set out-of-stock status when stock hits zero.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        $data = $this->normalisePricing($data);
        $data = $this->applyStockStatus($entry, $data);

        return $data;
    }

    /**
     * Guard pricing rules and require SKU when publishing.
     *
     * Pricing errors are reported here (via ValidationException through the service
     * layer) rather than thrown from hooks, so they surface as 422 responses instead
     * of 500s.
     *
     * {@inheritdoc}
     */
    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

        $price     = $data['fields']['price']      ?? null;
        $salePrice = $data['fields']['sale_price'] ?? null;

        if ($price !== null) {
            if ($price < 0) {
                $errors['price'] = 'price cannot be negative.';
            } elseif ($salePrice !== null) {
                if ($price === 0) {
                    $errors['sale_price'] = 'sale_price cannot be set when price is zero.';
                } elseif ($salePrice >= $price) {
                    $errors['sale_price'] = 'sale_price must be less than price.';
                }
            }
        }

        $requestedStatus = $data['status'] ?? ($entry?->status_handle);

        if ($requestedStatus === 'published') {
            $sku = $data['fields']['sku'] ?? $this->existingFieldValue($entry, 'sku');

            if (empty($sku)) {
                $errors['sku'] = 'A SKU is required before a product can be published.';
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------

    /**
     * Coerce pricing field types. Runs only after validate() has confirmed the
     * values are logically consistent, so no guards are needed here.
     */
    private function normalisePricing(array $data): array
    {
        return $data;
    }

    private function applyStockStatus(Entry $entry, array $data): array
    {
        $stock = $data['fields']['stock_quantity']
            ?? $this->existingFieldValue($entry, 'stock_quantity');

        if ($stock !== null && (int) $stock === 0) {
            $data['status'] = 'out-of-stock';
        }

        return $data;
    }
}
