<?php

namespace App\EntryTypes;

use App\Models\Entry;
use InvalidArgumentException;

class ProductEntryType extends AbstractEntryType
{
    /**
     * Validate price and sale_price on create; auto-status on stock depletion.
     */
    public function beforeCreate(array $data): array
    {
        $data = $this->validateAndNormalisePricing($data);

        return $data;
    }

    /**
     * Validate pricing, auto-set out-of-stock status when stock hits zero.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        $data = $this->validateAndNormalisePricing($data);
        $data = $this->applyStockStatus($entry, $data);

        return $data;
    }

    /**
     * Require SKU when publishing.
     *
     * {@inheritdoc}
     */
    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

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

    private function validateAndNormalisePricing(array $data): array
    {
        $price     = $data['fields']['price']      ?? null;
        $salePrice = $data['fields']['sale_price'] ?? null;

        if ($price !== null) {
            if ($price < 0) {
                throw new InvalidArgumentException('price cannot be negative.');
            }

            if ($salePrice !== null) {
                if ($price === 0) {
                    unset($data['fields']['sale_price']);
                    throw new InvalidArgumentException(
                        'sale_price cannot be set when price is zero.'
                    );
                }

                if ($salePrice >= $price) {
                    throw new InvalidArgumentException(
                        'sale_price must be less than price.'
                    );
                }
            }
        }

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
