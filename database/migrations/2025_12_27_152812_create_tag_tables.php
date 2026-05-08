<?php

// spatie/laravel-tags was removed from composer.json. The original migration
// created orphaned 'tags' and 'taggables' tables with no corresponding models.
// This file should be deleted from the repository. It is replaced with a no-op
// to prevent orphaned table creation on fresh installs.

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {}
    public function down(): void {}
};
