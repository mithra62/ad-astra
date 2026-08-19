<?php

namespace AdAstra\Models;

use Spatie\Permission\Models\Permission as PermissionModel;

/**
 * First-party Permission model.
 *
 * Exists so the detail columns added by
 * 2025_11_18_211520_add_permission_detail_columns.php have a home. Spatie's
 * model ships its own @property block and resolves its table from config at
 * runtime, so schema inference never picks these up — they are declared here.
 *
 * Registered via config/permission.php (models.permission).
 *
 * @property ?string $domain      grouping key used to build the role form
 * @property ?string $description human-readable explanation shown beside the name
 */
class Permission extends PermissionModel
{
}
