<?php

namespace AdAstra\EntryTypes;

/**
 * Alias table mapping the strings stored in `entry_behaviors.class` (e.g.
 * `behavior.blog-post`) to the concrete AbstractEntryType subclass that
 * implements them.
 *
 * This deliberately does NOT use Relation::morphMap(). That map is Laravel's
 * registry for polymorphic *Eloquent model* aliases and is typed
 * array<class-string<Model>>; entry behaviors are not models, so registering
 * them there was a type violation that happened to work. Keeping the two
 * families apart also stops behavior classes from showing up in reverse
 * lookups over the morph map (see GateBypassRecorder).
 *
 * Registration happens in AppServiceProvider::boot(). Tests may register
 * additional aliases at runtime; entries merge, so later calls do not clobber
 * earlier ones.
 */
class EntryBehaviorRegistry
{
    /** @var array<string, string> */
    private static array $map = [];

    /**
     * Register (or overwrite) behavior aliases.
     *
     * @param array<string, string> $map alias => fully-qualified class name
     */
    public static function register(array $map): void
    {
        self::$map = array_merge(self::$map, $map);
    }

    /**
     * Resolve an alias to its class name, or null when the alias is unknown.
     *
     * The class is not guaranteed to exist or to extend AbstractEntryType —
     * callers validate that themselves so they can report a useful error.
     */
    public static function resolve(string $alias): ?string
    {
        return self::$map[$alias] ?? null;
    }

    /**
     * Every registered alias, keyed by alias.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$map;
    }
}
