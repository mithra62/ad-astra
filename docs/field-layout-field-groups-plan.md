# Add FieldGroup Support to FieldLayout

## Context

FieldGroups should define the pool of fields available to a FieldLayout — acting as a field palette from which the admin places fields into Tabs as TabElements. The existing `HasFieldGroups` trait on owner models (EntryGroup, CategoryGroup, MediaLibrary) will be cleaned up separately. This plan only adds the new layer on top of FieldLayout.

Intended data flow:
```
FieldLayout  (gains HasFieldGroups)
  ├── fieldGroups()  → FieldGroup[]   ← available field palette
  │     └── fields() → Field[]
  └── tabs()
        └── elements() → TabElement → Field
```

---

## Changes

### 1. Add `HasFieldGroups` to `FieldLayout`

`HasFieldGroups` (`app/Traits/Field/HasFieldGroups.php`) is a reusable trait using the existing polymorphic `field_groupables` table. No new table needed — `FieldLayout` just becomes another morph type on it.

In `app/Models/FieldLayout.php`:
```php
use App\Traits\Field\HasFieldGroups;

// in the class:
use HasFieldGroups;
```

### 2. Add `availableFields()` helper to `FieldLayout`

A convenience method returning the deduplicated set of Fields from all attached FieldGroups — this is what the layout editor will use to show the field palette:

```php
public function availableFields(): \Illuminate\Support\Collection
{
    $this->loadMissing('fieldGroups.fields');
    return $this->fieldGroups->flatMap(fn($g) => $g->fields)->unique('id')->values();
}
```

### 3. Expose FieldGroup assignment in the FieldLayout admin UI

The FieldLayout settings form should allow attaching/detaching FieldGroups. Check:
- The FieldLayout admin controller (`app/Http/Controllers/Admin/FieldLayout*` or equivalent Livewire component)
- The store/update action or service for FieldLayout to sync incoming `field_groups` IDs via `$layout->fieldGroups()->sync($data['field_groups'] ?? [])`

---

## Files to Modify

| File | Change |
|---|---|
| `app/Models/FieldLayout.php` | Add `HasFieldGroups` trait, add `availableFields()` |
| FieldLayout admin controller / Livewire component | Add field group picker to form |
| FieldLayout store/update logic | Sync `field_groups` on save |

No migration needed — `field_groupables` already exists and handles any morph type.

---

## Verification

1. `composer test` — full suite should pass
2. In admin: edit a FieldLayout → FieldGroup multi-select is present and persists on save
3. Assign a FieldGroup to a layout; call `$layout->availableFields()` in tinker and confirm it returns the group's fields
4. Confirm existing behaviour on EntryGroup/CategoryGroup/MediaLibrary is unaffected
