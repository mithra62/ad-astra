<?php

namespace AdAstra;

use AdAstra\Models\SettingValue;
use AdAstra\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Config-driven, two-tier settings resolver.
 *
 * Field *definitions* (type, label, default, user_overridable, …) live in
 * config/settings.php — no DB schema required for schema changes.
 *
 * Field *values* live in the setting_values table using typed columns:
 *   value_text, value_integer, value_float, value_boolean, value_json
 *
 * Resolution order: user override → system value → config default.
 *
 * Caching:
 *   System raw values  → "settings.system.{domain}"       (1 hour TTL)
 *   User raw values    → "settings.user.{userId}.{domain}" (1 hour TTL)
 *   Both are busted independently on write.
 */
class Settings
{
    /** @var array<string, array<string, mixed>> Request-scoped memo for system raw values. */
    private array $system_memo = [];

    /** @var array<string, array<string, mixed>> Request-scoped memo for user raw values, keyed by "{userId}.{domain}". */
    private array $user_memo = [];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Resolve a single setting value for the given domain + handle.
     *
     * When $user is omitted the currently authenticated user's overrides are
     * applied automatically (pass null explicitly for system-only resolution).
     */
    public function get(string $domain, string $handle, mixed $default = null, ?User $user = null): mixed
    {
        $resolved = $this->all($domain, $user ?? auth()->user());

        return array_key_exists($handle, $resolved) ? $resolved[$handle] : $default;
    }

    /**
     * Return all resolved values for a domain as a keyed array.
     *
     * Resolution: user override → system value → config default.
     * Values are already natively typed via DB column casts — no extra casting needed.
     */
    public function all(string $domain, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $system = $this->systemRaw($domain);
        $userRaw = $user ? $this->userRaw($domain, $user) : [];

        // User overrides win; null user values (not set) do not mask system values.
        $merged = $system;
        foreach ($userRaw as $handle => $value) {
            if ($value !== null) {
                $merged[$handle] = $value;
            }
        }

        return $this->applyDefaults($domain, $merged);
    }

    /**
     * Fetch and cache the raw typed system values for a domain.
     *
     * @return array<string, mixed> ['field_handle' => typed_value]
     */
    private function systemRaw(string $domain): array
    {
        if (isset($this->system_memo[$domain])) {
            return $this->system_memo[$domain];
        }

        return $this->system_memo[$domain] = Cache::remember(
            "settings.system.{$domain}",
            3600,
            function () use ($domain) {
                $fields = $this->domainFields($domain);
                $result = [];

                SettingValue::where('domain', $domain)
                    ->whereNull('user_id')
                    ->get()
                    ->each(function (SettingValue $row) use (&$result, $fields) {
                        $field = $fields[$row->field_handle] ?? null;
                        if ($field !== null) {
                            $col = $this->columnFor($field['type'] ?? 'text');
                            $result[$row->field_handle] = $row->$col;
                        }
                    });

                return $result;
            }
        );
    }

    /**
     * Return all field definitions for a domain keyed by handle.
     *
     * @return array<string, array<string, mixed>>
     */
    private function domainFields(string $domain): array
    {
        static $cache = [];

        if (!isset($cache[$domain])) {
            $fields = config("settings.{$domain}.fields", []);
            $cache[$domain] = collect($fields)->keyBy('handle')->toArray();
        }

        return $cache[$domain];
    }

    /**
     * Map a field type string to the corresponding value_* column name.
     */
    public function columnFor(string $type): string
    {
        return match ($type) {
            'integer', 'select' => 'value_integer',
            'float' => 'value_float',
            'boolean' => 'value_boolean',
            'json' => 'value_json',
            default => 'value_text',
        };
    }

    /**
     * Fetch and cache the raw typed user override values for a domain.
     *
     * @return array<string, mixed> ['field_handle' => typed_value]
     */
    private function userRaw(string $domain, User $user): array
    {
        $memoKey = "{$user->id}.{$domain}";

        if (isset($this->user_memo[$memoKey])) {
            return $this->user_memo[$memoKey];
        }

        return $this->user_memo[$memoKey] = Cache::remember(
            "settings.user.{$user->id}.{$domain}",
            3600,
            function () use ($domain, $user) {
                $fields = $this->domainFields($domain);
                $result = [];

                SettingValue::where('domain', $domain)
                    ->where('user_id', $user->id)
                    ->get()
                    ->each(function (SettingValue $row) use (&$result, $fields) {
                        $field = $fields[$row->field_handle] ?? null;
                        if ($field !== null) {
                            $col = $this->columnFor($field['type'] ?? 'text');
                            $result[$row->field_handle] = $row->$col;
                        }
                    });

                return $result;
            }
        );
    }

    /**
     * Fill in config defaults for any field not represented in $raw.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function applyDefaults(string $domain, array $raw): array
    {
        $result = $raw;

        foreach ($this->domainFields($domain) as $handle => $field) {
            if (!array_key_exists($handle, $result)) {
                $result[$handle] = $field['default'] ?? null;
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Column routing (public so controllers/seeders can use without duplicating)
    // -------------------------------------------------------------------------

    /**
     * Persist a single value and bust the relevant cache entry.
     *
     * Pass $user = null  to write a system-wide value.
     * Pass a User model to write a per-user override.
     */
    public function set(string $domain, string $handle, mixed $value, ?User $user = null): void
    {
        $col = $this->columnFor($this->fieldType($domain, $handle));

        SettingValue::updateOrCreate(
            ['domain' => $domain, 'field_handle' => $handle, 'user_id' => $user?->id],
            [$col => $value]
        );

        $this->bust($domain, $user);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Return the type string for a single field ('text' if not found).
     */
    private function fieldType(string $domain, string $handle): string
    {
        return $this->domainFields($domain)[$handle]['type'] ?? 'text';
    }

    /**
     * Bust the cache for a single domain + user scope.
     *
     * Pass $user = null to bust the system cache.
     * Pass a User to bust only that user's cache.
     */
    public function bust(string $domain, ?User $user = null): void
    {
        if ($user) {
            Cache::forget("settings.user.{$user->id}.{$domain}");
            unset($this->user_memo["{$user->id}.{$domain}"]);
        } else {
            Cache::forget("settings.system.{$domain}");
            unset($this->system_memo[$domain]);
        }
    }

    /**
     * Persist multiple values for a domain in one call, then bust once.
     *
     * @param array<string, mixed> $values ['field_handle' => value, …]
     */
    public function setMany(string $domain, array $values, ?User $user = null): void
    {
        foreach ($values as $handle => $value) {
            $col = $this->columnFor($this->fieldType($domain, $handle));

            SettingValue::updateOrCreate(
                ['domain' => $domain, 'field_handle' => $handle, 'user_id' => $user?->id],
                [$col => $value]
            );
        }

        $this->bust($domain, $user);
    }

    /**
     * Return all resolved system-level values for a domain (no user context).
     */
    public function system(string $domain): array
    {
        return $this->applyDefaults($domain, $this->systemRaw($domain));
    }

    /**
     * Bust every cache key for a domain (system + all known user overrides).
     * Useful after bulk imports or seeder runs.
     */
    public function bustDomain(string $domain): void
    {
        Cache::forget("settings.system.{$domain}");

        SettingValue::where('domain', $domain)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->each(fn($id) => Cache::forget("settings.user.{$id}.{$domain}"));
    }
}
