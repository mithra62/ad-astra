# Data Model Concerns

## Overview

The model is built around a **content management core** with five major subsystems that nest together:

```
FieldLayout ──────────────────────────────────┐
  └── Tabs                                     │
        └── TabElements → Field → FieldType    │
                                               │
StatusGroup → Status                           │
                                               │
EntryGroup ←── (field_layout_id, status_group_id)
  └── EntryType ←── (field_layout_id)
        └── Entry
              ├── FieldValue (scalar fields)
              ├── EntryRelationship (relational fields)
              ├── entry_authors (pivot → User)
              └── categories (polymorphic)
```

A **FieldLayout** is a reusable template. It defines **Tabs**, each containing ordered **TabElements** that point to a **Field**. A `Field` knows its **FieldType** (text, textarea, relationship, etc.), which determines how the value is stored and rendered.

An **EntryGroup** is a content channel (like "Blog"). It optionally carries a `field_layout_id` (shared fields for all entries in the group) and a `status_group_id`. Each group has **EntryTypes** (like "Article", "Review"), which can optionally override the group's layout with their own `field_layout_id`. An **Entry** belongs to both a group and a type, and the entry form shows both layouts — type-specific on top, group-shared below.

Field values for an entry are stored in two places depending on type:
- **Scalar** (text, number, date, bool, JSON) → `field_values` with polymorphic ownership
- **Relational** (entry-to-entry links) → `entry_relationships` pivot

---

## Concerns

### 1. Status has no referential integrity

`entries.status` is a plain string (the handle, e.g. `"published"`), not a foreign key. If you delete or rename a Status, existing entries silently keep the old handle with no cascade or validation. Querying `->withStatus('published')` after renaming the status to `'live'` returns nothing with no error. Status renames and deletions require explicit data migrations to keep entries consistent.

### 2. EntryType deletion is blocked by entries, but EntryGroup deletion cascades

`entries.entry_type_id` uses `restrictOnDelete` — you cannot delete an entry type if entries reference it. But `entries.entry_group_id` uses `cascadeOnDelete` — deleting a group wipes all entries silently. This asymmetry is intentional but easy to forget when managing groups.

### 3. Both field layouts render on the entry form, but `getFieldLayout()` returns only one

`Entry::getFieldLayout()` returns the entry type's layout if it exists, otherwise the group's. The entry **form views** show both independently. But `EntryRepository::applyFieldValues()` uses `resolveLayoutFields()` which merges both layouts by field ID — if the same field appears in both the type layout and the group layout, the type-level one silently wins. This won't cause data loss but could cause confusing behavior if a field is accidentally reused across both layouts.

### 4. The `required` flag on TabElement is not enforced server-side

`field_layout_tab_elements.required` is stored and displayed in the UI, but there is no validation in `StoreEntryRequest` or `EntryRepository` that actually enforces it. It is metadata-only — enforcement must be built explicitly if needed.

### 5. Field values have a unique DB constraint, but no application-level guard

`field_values` enforces one value per field per owner via a unique constraint. There is no equivalent guard in `EntryRepository` at the application level — a race condition could attempt two inserts before the constraint fires. The database catches it, but the result is a raw `QueryException` rather than a validation message.

### 6. A field can appear in multiple tabs and multiple layouts simultaneously

There is no uniqueness constraint at the `field_layout_tab_elements` level across layouts — only within a single tab (`UNIQUE(field_layout_tab_id, field_id)`). The same field can exist in Tab A and Tab B of the same layout, or in two different layouts both assigned to the same entry group/type. The `fieldArray()` method uses `mapWithKeys` so the last write wins silently. A within-tab duplicate guard exists in the controller, but cross-tab and cross-layout duplication is unguarded.

### 7. `FieldLayout.name` is nullable in the database but required in the application

The migration defines `name` as `nullable()` but the `StoreFieldLayoutRequest` treats it as required. The form enforces this correctly, but a direct DB insert or seeder can create a nameless layout without error.

### 8. `field_values` polymorphism has an N+1 risk on `field()`

`Entry::field(handle)` filters `$this->fieldValues` in-memory after eager loading. If `fieldValues.field.fieldType` is not eager-loaded — for example, calling `$entry->field('slug')` from a context outside the standard controller query — it lazy-loads each `field` relation one query at a time. The controllers handle this correctly, but any future code path that calls `field()` without the full eager-load chain will silently hit N+1.

### 9. UserSchema singleton has a request-cache that can leak across tests

`UserSchema::instance()` uses a static property for request-level caching. In a standard HTTP context this is fine, but in tests or queue jobs that reuse the same process, the static cache persists across test cases unless explicitly cleared. This is worth knowing if field layout functionality is added to user profiles.

### 10. [RESOLVED] Categories support recursion but have no depth limit

`Category::childrenRecursive()` now includes a depth guard, and `CategoryService` includes cycle detection to prevent circular references.

---

## Healthiest Parts of the Model

- The **field storage split** (scalar in `field_values`, relational in `entry_relationships`) is clean and well-indexed.
- The **layout cascade** (`cascadeOnDelete` from layout → tabs → elements) means field layouts clean up after themselves completely.
- The **polymorphic fieldability** (`Entry`, `Category`, `User` all share the same field system) is a strong foundation for future extension.
- The **unique slug per group** constraint on both entries and categories prevents collisions without requiring a global namespace.

The biggest practical day-to-day risks are the **denormalized status string** (concern 1) and the **unenforced `required` flag** (concern 4) — both will produce silent wrong behavior rather than catchable errors. **Note:** Major architectural risks (polymorphic fragility, Eloquent bypasses) have been addressed.
