<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The framework namespace moved from `App\` to `AdAstra\` when the code was extracted
 * into the AdAstra package. Field types store their handler class as a fully-qualified
 * class-name string in `field_types.object`, so existing rows still point at the old
 * `App\Field\Types\*` classes. Rewrite the stored prefix.
 *
 * Fresh installs are unaffected: the seeders already write `AdAstra\` class names, so on
 * a freshly-seeded database this migration finds nothing to change (idempotent).
 */
return new class extends Migration {
    private const OLD = 'App\\';
    private const NEW = 'AdAstra\\';

    public function up(): void
    {
        $this->rewritePrefix(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rewritePrefix(self::NEW, self::OLD);
    }

    private function rewritePrefix(string $from, string $to): void
    {
        foreach (DB::table('field_types')->get() as $row) {
            if (is_string($row->object) && str_starts_with($row->object, $from)) {
                DB::table('field_types')
                    ->where('id', $row->id)
                    ->update(['object' => $to . substr($row->object, strlen($from))]);
            }
        }
    }
};
