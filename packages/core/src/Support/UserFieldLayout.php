<?php

namespace AdAstra\Support;

use AdAstra\Models\FieldLayout;
use AdAstra\Settings;

class UserFieldLayout
{
    private const DOMAIN = 'users';

    private const HANDLE = 'user_field_layout_id';

    /**
     * Return the configured FieldLayout with its full tab/element/field tree,
     * or null when no layout has been assigned.
     */
    public static function resolve(): ?FieldLayout
    {
        $id = static::resolvedId();

        if ($id === null) {
            return null;
        }

        return FieldLayout::with([
            'tabs' => fn($q) => $q->orderBy('sort_order'),
            'tabs.elements' => fn($q) => $q->orderBy('sort_order'),
            'tabs.elements.field',
        ])->find($id);
    }

    /**
     * Return the raw configured layout ID, or null when unset.
     */
    public static function resolvedId(): ?int
    {
        $value = app(Settings::class)->get(self::DOMAIN, self::HANDLE, null, null);

        return $value !== null ? (int) $value : null;
    }
}
