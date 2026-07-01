<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;
use AdAstra\Models\User;
use Illuminate\Support\Collection;

class Users extends AbstractField
{
    protected string $handle = 'users';

    protected string $name = 'Users';

    protected array $rules = [
        'nullable',
        'array',
    ];

    protected array $settings_form = [
        'roles' => [
            'type' => 'select_multiple',
            'label' => 'Restrict to Roles',
            'options' => 'roles',
            'instructions' => 'Limit selectable users to those with these roles. Leave empty to allow all users.',
            'default' => [],
            'rules' => 'nullable|array'
        ],
        'limit' => [
            'type' => 'number',
            'label' => 'Selection Limit',
            'instructions' => 'Maximum users that may be selected. 0 = unlimited.',
            'default' => 0,
            'rules' => 'nullable|integer|min:0'
        ],
        'display' => [
            'type' => 'select',
            'label' => 'Display As',
            'options' => [
                [
                    'value' => 'dropdown',
                    'label' => 'Dropdown (searchable)'
                ],
                [
                    'value' => 'checkboxes',
                    'label' => 'Checkboxes'
                ],
                [
                    'value' => 'tokens',
                    'label' => 'Token list'
                ]
            ],
            'default' => 'dropdown',
            'rules' => 'nullable|string|in:dropdown,checkboxes,tokens'
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'roles' => \AdAstra\Models\Role::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn($r) => ['value' => $r->id, 'label' => $r->name])
                ->all(),
        ];
    }

    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function cast(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        return [];
    }

    public function validate(mixed $value): bool|string
    {
        if ($value === null || $value === []) {
            return true;
        }

        $ids = $this->cast($value);
        $limit = (int)$this->getSetting('limit', 0);

        if ($limit > 0 && count($ids) > $limit) {
            return "No more than {$limit} user(s) may be selected.";
        }

        $found = User::whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, $found);
        if (!empty($missing)) {
            return 'One or more selected users no longer exist.';
        }

        $roles = $this->getSetting('roles', []);
        if (!empty($roles)) {
            $invalidUser = User::whereIn('id', $ids)
                ->whereDoesntHave('roles', fn($q) => $q->whereIn('id', (array)$roles))
                ->exists();

            if ($invalidUser) {
                return 'One or more selected users do not have the required role.';
            }
        }

        return true;
    }

    /**
     * Resolves stored IDs to User models, selecting safe columns only.
     * Never exposes password hashes, tokens, or remember_token.
     */
    public function value(mixed $raw): Collection
    {
        $ids = $this->cast($raw);

        if (empty($ids)) {
            return collect();
        }

        return User::select(['id', 'name', 'email'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn($u) => array_search($u->id, $ids))
            ->values();
    }

    public function render(array $params): string
    {
        $roles = $this->getSetting('roles', []);

        $query = User::select(['id', 'name', 'email'])->orderBy('name');
        if (!empty($roles)) {
            $query->whereHas('roles', fn($q) => $q->whereIn('id', (array)$roles));
        }

        $params['available_users'] = $query->get();
        $params['display'] = $this->getSetting('display', 'dropdown');
        $params['selected_ids'] = $this->extractSelectedIds($params['value'] ?? null);

        return view('_fields.users', $params)->render();
    }

    private function extractSelectedIds(mixed $value): array
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->pluck('id')->map(fn($id) => (int)$id)->all();
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        return [];
    }
}
