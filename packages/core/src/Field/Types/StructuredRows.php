<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;

class StructuredRows extends AbstractField
{
    protected string $handle = 'structured_rows';

    protected string $name = 'Structured Rows';

    protected array $rules = [
        'nullable',
        'array',
    ];

    protected array $settings_form = [
        'columns' => [
            'type' => 'structured_rows_columns',
            'label' => 'Columns',
            'instructions' => 'Define the columns for each row. At least one column is required.',
            'default' => [],
            'rules' => 'required|array|min:1'
        ],
        'min_rows' => [
            'type' => 'number',
            'label' => 'Minimum Rows',
            'default' => 0,
            'rules' => 'nullable|integer|min:0'
        ],
        'max_rows' => [
            'type' => 'number',
            'label' => 'Maximum Rows',
            'default' => null,
            'rules' => 'nullable|integer|min:1'
        ],
        'add_label' => [
            'type' => 'text',
            'label' => 'Add Row Button Label',
            'default' => 'Add row',
            'rules' => 'nullable|string|max:100'
        ],
    ];

    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function validate(mixed $value): bool|string
    {
        if ($value === null || $value === []) {
            return true;
        }

        $rows = $this->cast($value);
        $minRows = (int)$this->getSetting('min_rows', 0);
        $maxRows = $this->getSetting('max_rows');

        if ($minRows > 0 && count($rows) < $minRows) {
            return "At least {$minRows} row(s) are required.";
        }

        if ($maxRows !== null && count($rows) > (int)$maxRows) {
            return "No more than {$maxRows} row(s) are allowed.";
        }

        $columns = $this->getSetting('columns', []);
        $handles = array_column($columns, 'handle');

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                return "Row " . ($i + 1) . " must be an array.";
            }

            foreach ($handles as $handle) {
                if (!array_key_exists($handle, $row)) {
                    return "Row " . ($i + 1) . " is missing the \"{$handle}\" column.";
                }
            }
        }

        return true;
    }

    public function cast(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    public function render(array $params): string
    {
        $columns = $this->getSetting('columns', []);
        $addLabel = $this->getSetting('add_label', 'Add row');
        $minRows = (int)$this->getSetting('min_rows', 0);

        $rawRows = $this->cast($params['value'] ?? []);

        // Fill missing column keys with null so the template never encounters undefined indices
        $params['rows'] = array_map(function (array $row) use ($columns): array {
            return array_merge(
                array_fill_keys(array_column($columns, 'handle'), null),
                $row
            );
        }, $rawRows);

        $params['columns'] = $columns;
        $params['add_label'] = $addLabel;
        $params['min_rows'] = $minRows;

        return view('_fields.structured_rows', $params)->render();
    }
}
