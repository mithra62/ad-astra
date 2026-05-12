# Field Type Settings Layer — Action Plan

## Goal

Add a per-field-type settings layer so that, when a Field is created or edited, the admin UI renders a settings panel specific to the chosen FieldType. Settings are stored as JSON on `fields.settings` and made available to the type instance at render/validate time.

The pattern mirrors ExpressionEngine's channel-field settings API: each FieldType class declares its configurable settings as a PHP array; the framework does the rest (rendering, validation, storage, repopulation).

This plan also covers six new FieldTypes: **Select**, **Multi Select**, **Radio Group**, **Slider**, **Structured Rows**, and **Users**.

---

## Current State

### What already exists

- `AbstractField::$settings_form` — protected array property, already declared; `Text` has a partial definition (`placeholder` key with `type`, `required`, `rules`).
- `fields.settings` — JSON column already on the `fields` table. It exists but nothing writes to it from the admin UI.
- `Field\Type::instance()` — instantiates the type class and passes `$this->settings` (the **Type** model's settings, not the **Field** model's settings) as the constructor argument.
- `AbstractField::getSetting()` — already reads from the injected `$settings` array.
- `resources/views/admin/_inc/_form-fields.twig` — fully-built macro library: `input`, `password`, `file`, `textarea`, `select`, `color`, `slider`, `toggle`, `checkbox_card`. Each macro handles `old()` repopulation, error state styling, `aria-invalid`/`aria-describedby`, description text, and ID derivation from `name`. This is the canonical input component set; the settings form widget layer will delegate to it.
- `resources/views/admin/_inc/_structured-rows.twig` — fully-built structured-rows macro with `data-matrix-*` JS attributes, `<template>` cloning, and column types `text`, `textarea`, `select`, `checkbox`. This is the UI primitive the Structured Rows FieldType will wrap.

### What is broken / missing

1. **Settings never reach the instance from the Field model.** `Type::instance()` passes type-level settings (`Type->settings`); field-specific settings (`Field->settings`) are never merged in.
2. **The admin forms have no settings panel.** The create/edit views show a static set of fields only.
3. **`$settings_form` has no enforced schema.** The single existing definition (`Text`) is sparse; there is no `label`, `default`, or `instructions` key.
4. **No validation of settings on save.** `StoreFieldRequest` / `EditFieldRequest` do not validate `settings[*]` keys at all.
5. **No mechanism to dynamically render settings when the type dropdown changes.**
6. **Existing FieldType `render()` methods do not use the macro library.** `Boolean` and `ColorPicker` in particular should render via `toggle` and `color` macros for visual consistency with every other admin form.

---

## Proposed Architecture

### 1. Standardise the `$settings_form` schema

Each entry in `$settings_form` maps a setting key to a descriptor array:

```php
protected array $settings_form = [
    'placeholder' => [
        'type'         => 'text',            // see widget types below
        'label'        => 'Placeholder',
        'instructions' => 'Shown inside the input when empty.',
        'default'      => null,
        'required'     => false,
        'rules'        => 'nullable|string|max:255',
    ],
    'max_length' => [
        'type'    => 'number',
        'label'   => 'Max Length',
        'default' => null,
        'rules'   => 'nullable|integer|min:1',
    ],
];
```

**Supported widget types and their macro backing:**

| `type` value | `_form-fields.twig` macro | Notes |
|---|---|---|
| `text` | `input` (type=text) | |
| `number` | `input` (type=number) | |
| `textarea` | `textarea` | |
| `toggle` | `toggle` | Boolean on/off |
| `select` | `select` | Requires `options` key (static assoc array). Single-value. |
| `select_multiple` | `select` (with `multiple=true`) | DB-sourced or static; see §select macro change |
| `slider` | `slider` | Requires `min`, `max`; supports `step`, `suffix`. See §slider widget. |
| `color` | `color` | Stores hex string. Note dual-input caveat below. |
| `key_value` | Custom repeater (no macro equivalent) | Ordered `{key, label}` pairs; Alpine.js add/remove rows |
| `structured_rows_columns` | Custom (uses `_structured-rows.twig`) | Column-schema editor for Structured Rows FieldType |

> **Naming note:** `select` and `select_multiple` as widget types configure *settings* with dropdowns. The `Select` and `Multi Select` FieldTypes are *content fields* that render as dropdowns. Different layers; do not conflate.

### 2. The `_settings_widget.twig` dispatcher

Rather than hand-rolling input markup, `_settings_widget.twig` imports `_form-fields.twig` and delegates to the appropriate macro. This means the settings panel inherits all error handling, `old()` repopulation, aria attributes, and styling from the canonical macro library automatically — no parallel implementation to drift.

```twig
{% import 'admin._inc._form-fields' as f %}

{% if def.type == 'text' %}
    {{ f.input('settings[' ~ handle ~ ']', def.label, value, 'text', def.instructions) }}

{% elseif def.type == 'number' %}
    {{ f.input('settings[' ~ handle ~ ']', def.label, value, 'number', def.instructions) }}

{% elseif def.type == 'textarea' %}
    {{ f.textarea('settings[' ~ handle ~ ']', def.label, value, null, def.instructions) }}

{% elseif def.type == 'toggle' %}
    {{ f.toggle('settings[' ~ handle ~ ']', def.label, value, def.instructions) }}

{% elseif def.type == 'select' %}
    {{ f.select('settings[' ~ handle ~ ']', def.label, def.options, value, def.instructions) }}

{% elseif def.type == 'slider' %}
    {{ f.slider('settings[' ~ handle ~ ']', def.label, value, def.min, def.max, def.step, def.instructions, null, def.suffix) }}

{% elseif def.type == 'color' %}
    {{ f.color('settings[' ~ handle ~ ']', def.label, value, def.instructions) }}

{% elseif def.type == 'select_multiple' %}
    {{ f.select('settings[' ~ handle ~ '][]', def.label, def.options, value, def.instructions, null, null, true) }}

{% elseif def.type == 'key_value' %}
    {# custom repeater — no macro equivalent #}
    {{ include('admin.fields._settings_key_value', {handle: handle, def: def, value: value}) }}

{% elseif def.type == 'structured_rows_columns' %}
    {{ include('admin.fields._settings_structured_rows_columns', {handle: handle, def: def, value: value}) }}

{% endif %}
```

The `name` convention `settings[placeholder]` maps cleanly through the macro's ID and error key derivation: `field_id` becomes `settings_placeholder`, `field_error_key` becomes `settings.placeholder` — both correct for Laravel validation and DOM targeting.

### 3. Required change to the `select` macro

Add a `multiple` boolean parameter (default `false`). When true, render `<select multiple>` and handle `value` as an array for the `selected` check:

```twig
{# _form-fields.twig — select macro signature change #}
{% macro select(name, label, options, value, description, id, error_key, multiple) %}
    ...
    <select ... {% if multiple %}multiple{% endif %}>
        {% for option in options %}
            {% set is_selected = multiple ? (option.value in value) : (selected_value == option.value) %}
            <option value="{{ option.value }}" {% if is_selected %}selected{% endif %}>{{ option.label }}</option>
        {% endfor %}
    </select>
```

This single change enables all DB-sourced multi-select settings widgets without any new macro.

### 4. The `slider` widget type — dynamic bounds

When a `slider` widget is used to configure the *default value* of a Slider FieldType, its `min`/`max`/`step`/`suffix` parameters should resolve from sibling settings values rather than the schema declaration. The `_settings_widget.twig` dispatcher handles this: when `def.type == 'slider'`, it reads `def.min`, `def.max`, etc., which the AJAX controller populates from either the schema or the current settings values for `min`/`max` fields if they exist.

### 5. The `color` macro — dual-input naming

The `color` macro submits two inputs: `name` (the `<input type="color">`) and `name_hex` (the text input). In the settings context, `settings[color_preset]` produces an extra `settings[color_preset]_hex` key in the request. The `filterSettings()` method in the action class strips any key not declared in `settingsForm()`, so it is harmless — but it should be noted in the action class with a comment so future maintainers do not wonder why the `_hex` key disappears.

### 6. JS re-initialisation after AJAX panel swap

`color_scripts()`, `slider_scripts()`, and the structured-rows scripts are included once per layout. After the type dropdown triggers a `fetch()` and injects new settings panel HTML, any color or slider widgets in the swapped-in fragment need their init functions re-called. All three use `data-*-ready="true"` guards so a global re-init is safe:

```js
document.getElementById('field_type_id').addEventListener('change', function () {
    fetch(`/admin/fields/type-settings?type_id=${this.value}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('field-type-settings').innerHTML = html;
            if (window.initColorFields)  window.initColorFields();
            if (window.initRangeSliders) window.initRangeSliders();
            if (window.initStructuredRowsFields) window.initStructuredRowsFields();
        });
});
```

For this to work, the three init functions must be extracted from their IIFE wrappers and attached to `window` (or a shared namespace) so they are callable after the initial page load.

### 7. Add helper methods to `AbstractField`

```php
public function settingsForm(): array { return $this->settings_form; }

public function settingsDefaults(): array
{
    return collect($this->settings_form)
        ->map(fn($def) => $def['default'] ?? null)
        ->all();
}

public function settingsRules(): array
{
    return collect($this->settings_form)
        ->mapWithKeys(fn($def, $key) => [
            "settings.{$key}" => $def['rules'] ?? 'nullable',
        ])
        ->all();
}
```

### 8. Fix settings propagation in `Field\Type::instance()`

```php
public function instance(array $fieldSettings = []): AbstractField
{
    $merged = array_merge($this->settings ?? [], $fieldSettings);
    // instantiate with $merged
}
```

Add `Field::typeInstance()`:

```php
public function typeInstance(): AbstractField
{
    return $this->fieldType->instance($this->settings ?? []);
}
```

Update `Field::render()` and `FieldValue::resolvedValue()` to call `typeInstance()` instead of `fieldType->instance()`. This is the most critical runtime fix — without it, placeholder, limit, options, columns, etc. are never passed to rendered field inputs.

### 9. New AJAX route: `GET /admin/fields/type-settings`

```
GET /admin/fields/type-settings?type_id={id}&field_id={id|null}
```

The controller:
1. Resolves `Field\Type` by `type_id` and instantiates the type class.
2. Calls `settingsForm()` to get the descriptor array.
3. For `select_multiple` widgets and any one-off DB-sourced settings (e.g. Relationship's entry group list, FileUpload's library list, Users' role list), fetches current DB records and formats as `[{value, label}]` arrays to match the macro's `options` signature.
4. If `field_id` is provided (edit page), passes `Field->settings` as `current_values`.
5. Returns `response()->view('admin.fields._settings_panel', $data)`.

**Validation error repopulation:** On a validation failure, re-render the full page (not AJAX). The controller reads `old('settings', $field->settings ?? [])` and passes it to the view. The macros' internal `old()` calls handle the rest. No AJAX needed for error state.

### 10. `_settings_panel.twig`

```twig
{% if settings_form is empty %}
    <p class="text-sm text-slate-400 italic">This field type has no configurable settings.</p>
{% else %}
    {% for handle, def in settings_form %}
        {{ include('admin.fields._settings_widget', {
            handle: handle,
            def: def,
            value: current_values[handle] ?? def.default
        }) }}
    {% endfor %}
{% endif %}
```

### 11. Update `StoreFieldRequest` / `EditFieldRequest`

```php
public function rules(): array
{
    $base = ['name' => 'required|string', ...];
    $typeId = $this->input('field_type_id');
    if ($typeId && $type = FieldType::find($typeId)) {
        $base = array_merge($base, $type->instance()->settingsRules());
    }
    return $base;
}
```

### 12. Update `CreateNewField` / `EditField` actions

```php
$field->settings = $this->filterSettings($request->input('settings', []), $typeInstance);
```

`filterSettings()` strips undeclared keys (including the `color` macro's `_hex` suffix), applies defaults for missing keys, and normalises `key_value` and `structured_rows_columns` arrays into their canonical JSON formats before storage.

### 13. Update all existing field type `$settings_form` definitions

| Field Type | Settings | `render()` macro target |
|---|---|---|
| **Text** | `placeholder`, `max_length`, `min_length` | `input` |
| **Textarea** | `placeholder`, `max_length`, `rows` | `textarea` |
| **Number** | `min`, `max`, `step`, `decimals`, `default` | `input` (type=number) |
| **Date** | `min_date`, `max_date`, `default`, `format` | `input` (type=date) |
| **Boolean** | `default`, `label_on`, `label_off` | `toggle` macro |
| **ColorPicker** | `format` (select: hex/rgb/hsl), `alpha` (toggle), `presets` (key_value) | `color` macro |
| **Html** | `toolbar` (select: minimal/standard/full), `allowed_tags` | (rich editor) |
| **EmailAddress** | — | `input` (type=email) |
| **Telephone** | — | `input` (type=tel) |
| **Url** | — | `input` (type=url) |
| **FileUpload** | `library` (one-off multi-select from media libraries), `allowed_types` (key_value), `min`, `max` | `file` macro + AJAX bridge (see §FileUpload render) |
| **Relationship** | `entry_groups` (one-off multi-select from entry groups), `entry_types` (one-off multi-select from entry types), `limit` (number) | (entry picker) |

`EmailAddress`, `Telephone`, and `Url` declare empty `$settings_form` — the panel shows the "no configurable settings" message.

### 14. Macros that stay admin-only — never FieldTypes

| Macro | Reason |
|---|---|
| `password` | No legitimate content field use case; passwords are not stored in entries |
| `file` | Redundant with `FileUpload`; lacks Media Library integration; admin upload forms only |
| `checkbox_card` | Semantically identical to Boolean (single checked value); better as a `display` variant on Boolean than a separate FieldType |

`checkbox_card` could be added as a `display` setting on `Boolean` (`toggle` vs `card`) in a future pass. It is not a new FieldType.

---

## New Field Types

### Select

A single-value dropdown populated from options defined at the field level.

- **Handle:** `select`
- **Storage:** `value_text` (the selected option key)
- **`render()` target:** `select` macro from `_form-fields.twig`
- **`$settings_form`:**

| Setting | Widget | Notes |
|---|---|---|
| `options` | `key_value` | Ordered key → label pairs. Required. |
| `placeholder` | `text` | First empty option label, e.g. "— Choose —". |
| `default` | `text` | Pre-selected option key. |

- **`validate()`:** Value must be one of the configured option keys (or null/empty).
- **`getRules()`:** `['nullable', 'string']`

**Orphaned value handling:** If an admin removes an option key after entries have stored it, render the stored value as a disabled option with a visual indicator. On re-save of the entry, warn but do not block by default unless `strict_options` is enabled.

---

### Multi Select

Multiple values from a defined option list.

- **Handle:** `multi_select`
- **Storage:** `value_json` (array of selected option keys)
- **`render()` target:** `select` macro with `multiple=true`, or a checkbox list depending on `display` setting
- **`$settings_form`:**

| Setting | Widget | Notes |
|---|---|---|
| `options` | `key_value` | Ordered key → label pairs. Required. |
| `min` | `number` | Minimum selections (0 = no minimum). |
| `max` | `number` | Maximum selections (empty = unlimited). |
| `display` | `select` | `checkboxes` (default) or `multiselect`. |

- **`validate()`:** All values must be valid option keys; enforces min/max.
- **`cast()`:** Decodes `value_json` to PHP array of strings.
- **`getRules()`:** `['nullable', 'array']`

Multi Select is a separate type from Select (not a flag) because `storageColumn()` and `cast()` differ, and a flag would require branching in both methods.

---

### Radio Group

Single-value selection from a visible list of radio inputs.

- **Handle:** `radio_group`
- **Storage:** `value_text` (the selected option key)
- **`render()` target:** custom radio group view (no equivalent macro; renders `<label><input type="radio">` loop)
- **`$settings_form`:**

| Setting | Widget | Notes |
|---|---|---|
| `options` | `key_value` | Ordered key → label pairs. Required. |
| `default` | `text` | Pre-selected option key. |
| `layout` | `select` | `stacked` (default) or `inline`. |

- **`validate()`:** Same as Select — value must be a valid option key or empty.
- **`getRules()`:** `['nullable', 'string']`

Select, Multi Select, and Radio Group share "value must match configured option keys" validation. Extract into a `ValidatesAgainstOptions` trait used by all three to avoid duplication. The orphaned-value handling logic lives in the same trait.

---

### Slider

A bounded range input. Stores the same column types as `Number` but the UX contract is meaningfully different: the value is always within bounds, the range is visible, and free-text entry is not possible.

- **Handle:** `slider`
- **Storage:** `value_integer` (or `value_float` when `decimals > 0`, same logic as `Number`)
- **`render()` target:** `slider` macro from `_form-fields.twig`
- **`$settings_form`:**

| Setting | Widget | Notes |
|---|---|---|
| `min` | `number` | Required. Lower bound. |
| `max` | `number` | Required. Upper bound. |
| `step` | `number` | Default `1`. |
| `suffix` | `text` | Unit label appended to displayed value, e.g. `%`, `px`, `★`. |
| `decimals` | `number` | Drives `storageColumn()` same as Number. |
| `default` | `slider` | Uses the slider widget — the AJAX controller resolves `min`/`max`/`step`/`suffix` from sibling setting values when building the descriptor for this widget. |

- **`validate()`:** Value must be a number within `[min, max]`. Clamp rather than reject if the stored value falls outside bounds after settings change.
- **`storageColumn()`:** Returns `value_float` when `decimals > 0`, `value_integer` otherwise (same as `Number`).

The `default` setting using the `slider` widget type itself is the canonical example of the dynamic-bounds resolution pattern described in §4 above.

---

### Structured Rows

A repeatable set of rows with user-defined columns. Wraps the existing `_structured-rows.twig` macro as a content FieldType.

- **Handle:** `structured_rows`
- **Storage:** `value_json` — indexed array of row objects:
  ```json
  [
      {"heading": "Chapter 1", "body": "...", "featured": "1"},
      {"heading": "Chapter 2", "body": "...", "featured": "0"}
  ]
  ```
- **`$settings_form`:**

| Setting | Widget | Notes |
|---|---|---|
| `columns` | `structured_rows_columns` | Column schema definitions. Required; at least one column. |
| `min_rows` | `number` | Minimum rows (0 = none). |
| `max_rows` | `number` | Maximum rows (empty = unlimited). |
| `add_label` | `text` | "Add row" button label. Default: `Add row`. |

#### The `structured_rows_columns` settings widget

Renders a simplified structured-rows table (via `_structured-rows.twig`) where each row defines one output column. Columns of the definition table:

| Field | Input | Purpose |
|---|---|---|
| `handle` | text | Key used in stored JSON |
| `label` | text | Column header shown to content editors |
| `type` | select | `text`, `textarea`, `number`, `select`, `checkbox` |
| `placeholder` | text | For text/textarea columns |
| `width` | text | CSS width hint, e.g. `200px` |
| `options` | text | For `type=select` only: comma-separated `key:Label` pairs |

Stored in `settings.columns` as a JSON array of column descriptors:
```json
[
    {"handle": "heading", "label": "Heading",  "type": "text",   "placeholder": "", "width": "",      "options": ""},
    {"handle": "body",    "label": "Body",     "type": "textarea","placeholder": "","width": "",      "options": ""},
    {"handle": "tier",    "label": "Tier",     "type": "select", "placeholder": "", "width": "120px", "options": "basic:Basic,pro:Pro"}
]
```

#### Rendering at content-edit time

`render()` passes the column schema and current row data to the macro. A thin wrapper view (`_structured-rows-field.twig`) imports the macro and calls it, ensuring the macro's `scripts()` block is included once per page.

#### `validate()`

1. Value is an array or null/empty.
2. Row count ≥ `min_rows` and ≤ `max_rows`.
3. Each row is an associative array containing all declared column handles.
4. Extra keys (from removed columns) are stripped silently at render time; missing keys (from added columns) use `column.default` at render time. No migration needed.

---

### Users

Relates a field to one or more User records.

- **Handle:** `users`
- **Storage:** `value_json` — array of integer user IDs: `[3, 14, 27]`

**Why `value_json` and not a pivot table?** A `user_relationships` pivot would be architecturally consistent with `entry_relationships` but adds a migration and a query path ("find all fields referencing user X") that is not yet needed. `value_json` mirrors `FileUpload`'s pattern and keeps the schema stable. This can be upgraded to a pivot later without breaking the `getSetting` / `cast` / `value` contract.

- **`$settings_form`:**

| Setting | Widget | Notes |
|---|---|---|
| `roles` | one-off multi-select from roles table | Restrict selectable users to those with specified roles. Empty = all users. |
| `limit` | `number` | Maximum selectable users (0 = unlimited). |
| `display` | `select` | `dropdown` (searchable), `checkboxes`, or `tokens`. Default: `dropdown`. |

- **`validate()`:** Array of integers; count ≤ `limit`; all IDs exist in `users`; if `roles` configured, all users have at least one matching role.
- **`cast()`:** Returns plain array of integer IDs.
- **`value()`:** Resolves IDs to `Collection<User>`, preserving saved order. Scoped to `['id', 'name', 'email']` columns only — never exposes password hashes, tokens, or remember tokens.
- **`getRules()`:** `['nullable', 'array']`

---

### FileUpload `render()` — AJAX upload bridge

`FileUpload` is the only existing FieldType with no `render()` method. The `file` macro from `_form-fields.twig` is the correct visual foundation (drag-and-drop zone, styled drop target, accessible label), but there is an impedance mismatch: the macro submits raw binary file data, while `FileUpload` stores an array of integer media IDs. Bridging the two requires an AJAX upload flow and a small addition to the existing upload endpoint.

#### 1. Content negotiation on `Library::upload()`

`POST media/libraries/{library_id}/upload` currently always returns a redirect. Add JSON response support via content negotiation so the same endpoint serves both the admin upload page and field-level AJAX calls:

```php
public function upload(UploadMediaRequest $request, string $id)
{
    $library = LibraryModel::find($id);
    if (!$library instanceof LibraryModel) {
        if ($request->expectsJson()) {
            return response()->json(['error' => trans('media.library.not_found')], 404);
        }
        abort(404);
    }

    $media = app(UploadMedia::class)->upload($request, $library);

    if ($request->expectsJson()) {
        return $media
            ? response()->json(['id' => $media->id, 'name' => $media->original_name, 'url' => $media->url])
            : response()->json(['error' => trans('media.upload_failed')], 422);
    }

    return $media
        ? redirect()->route('media.show', $media)->with('success', trans('media.uploaded'))
        : redirect()->route('media.libraries.show', $library)->with('failure', trans('media.upload_failed'));
}
```

The existing redirect behaviour is preserved for non-AJAX callers. No route change needed.

#### 2. The render view — `resources/views/_fields/file_upload.twig`

The view uses the `file` macro as the drop zone, then adds JS that:

1. Intercepts the file input's `change` event before the parent form can submit
2. POSTs each selected file to the library upload endpoint with `Accept: application/json` and `X-CSRF-TOKEN`
3. On success, injects a hidden `<input type="hidden" name="{input_name}[]" value="{id}">` for each uploaded file and renders a dismissible chip showing `original_name`
4. On failure, renders an inline error message under the drop zone

The field's `library` setting (resolved to a library ID via `library_id` or `library_handle`) drives the upload URL. `FileUpload::render()` passes the resolved library ID into the view:

```php
public function render(array $params): string
{
    $params['library_id'] = $this->resolveLibraryId();
    $params['max']        = $this->getSetting('max');
    $params['accept']     = $this->buildAcceptString();   // from allowed_types setting
    return view('_fields.file_upload', $params)->render();
}
```

#### 3. Repopulation from `old()` and existing values

On a validation failure, `old('fields.{handle}')` contains an array of previously submitted media IDs (the hidden inputs from step 2 above). The view re-renders these as pre-populated chips with a remove button — no re-upload needed. The `value` param (a `Collection<Media>` resolved via `FileUpload::value()`) handles the existing-data case on the edit page.

#### 4. `max` enforcement in the view

If the `max` setting is set to `1`, the file input is rendered without `multiple` and the JS replaces any existing chip rather than appending. If `max > 1`, the JS disables the drop zone and hides the add button once the limit is reached, surfacing a short message.

#### 5. No new route needed

The upload endpoint already exists and is already protected by admin auth middleware. The only change is content negotiation in the controller method above.

---

## Edge Cases & Design Decisions

### Type change on existing field

`EditField` currently blocks type changes when field values exist. This should remain. When no values exist and the type is changed: old settings JSON is discarded; the new type's `settingsDefaults()` are stored. The UI should show a brief warning if the settings panel has been interacted with before the type is changed.

### `key_value` repeater serialisation

Options lists (Select, Multi Select, Radio Group) store ordered `{key, label}` pairs as a JSON array. The repeater widget sends `settings[options][0][key]`, `settings[options][0][label]`, etc. `filterSettings()` normalises this into the canonical JSON array before storage. Order is preserved — do not sort alphabetically on save.

### Options-based types and orphaned values

Select, Multi Select, and Radio Group share the same problem: a stored value may no longer be a valid option key if settings are updated. Strategy:
- **On render:** show the stored value with a visual indicator if not in the current options list. Do not throw or silently discard.
- **On entry save:** warn but do not block by default; enable strict rejection via a `strict_options` setting.
- **On field settings save:** if `strict_options` is enabled, count affected entries and surface the count to the admin.

The `ValidatesAgainstOptions` trait implements all of this once; all three types use it.

### DB-sourced one-off settings

For field types whose settings include DB-sourced lists (Relationship's entry groups, FileUpload's media libraries, Users' roles), the AJAX route fetches current DB state on every call and passes it as `options` alongside the settings form definition. Do not cache the settings panel HTML — these lists can change between page load and type dropdown interaction.

### `Number` and `Slider` share `storageColumn()` logic

Both return `value_float` when `decimals > 0`, `value_integer` otherwise. Extract into a `HasDecimalStorage` trait if the duplication becomes maintenance friction. Not strictly necessary at first.

### Structured Rows column schema changes on existing data

Column removal: stored JSON still contains the old key; it is silently ignored at render time. Column addition: existing rows lack the key; `column.default` fills the gap. No migration or data repair needed.

### Users FieldType and sensitive data

`Users::value()` resolves IDs to User models. Scope to `['id', 'name', 'email']` at the query level, not in application code, so the model instances never carry sensitive attributes. This is especially important if field values are surfaced via the public API.

### Backward compatibility

Existing fields have `settings = null`. `getSetting($key, $default)` already handles this. No migration needed.

---

## Testing

- `settingsDefaults()` returns correct defaults for each type.
- `settingsRules()` generates correct Laravel rule strings.
- `Type::instance($fieldSettings)` merges type defaults with field overrides correctly.
- `CreateNewField` / `EditField` strip undeclared settings keys including the `color` macro's `_hex` suffix.
- `StoreFieldRequest` validation rejects invalid settings values.
- `GET /admin/fields/type-settings` returns 200 with correct HTML fragment; DB-sourced options populated.
- Settings panel updates on type dropdown change; `initColorFields`, `initRangeSliders`, `initStructuredRowsFields` re-called after swap.
- **Select / Radio Group:** orphaned value renders with indicator; `strict_options` rejects on entry save.
- **Multi Select:** min/max enforcement; JSON round-trip through `cast()`/`value()`.
- **Slider:** value clamped when stored value falls outside updated `[min, max]` bounds.
- **FileUpload:** AJAX upload returns correct JSON; `max=1` replaces chip instead of appending; `old()` repopulates chips without re-upload; drop zone disabled when `max` reached; content negotiation leaves existing redirect behaviour intact.
- **Structured Rows:** column removal does not corrupt row data; missing keys default at render; min/max row validation.
- **Users:** role filter applied correctly; sensitive columns absent from `value()` output.
- Rendering: `Field::render()` includes settings-driven attributes for all types after `typeInstance()` fix.
- `Boolean::render()` and `ColorPicker::render()` produce output consistent with `toggle` and `color` macros.

---

## Implementation Order

1. **Standardise `$settings_form` schema** — add `settingsForm()`, `settingsDefaults()`, `settingsRules()` to `AbstractField`; update `Text` definition to full schema.
2. **Fix settings propagation** — update `Field\Type::instance()` to accept overrides; add `Field::typeInstance()`; update all call sites (`Field::render()`, `FieldValue::resolvedValue()`).
3. **Update `select` macro** — add `multiple` boolean parameter.
4. **Expose JS init functions globally** — extract `initColorFields`, `initRangeSliders`, `initStructuredRowsFields` from their IIFE wrappers onto `window` (or a shared namespace).
5. **Update all existing field types** — add full `$settings_form`; update `render()` to use macros (especially `Boolean` → `toggle`, `ColorPicker` → `color`); implement `FileUpload::render()` with AJAX bridge and add JSON content negotiation to `Library::upload()`.
6. **Add new field types** — `Select`, `Multi Select`, `Radio Group`, `Slider` (all straightforward); `Users` (straightforward plus role query); `Structured Rows` last (depends on `structured_rows_columns` widget and step 4).
7. **AJAX route + controller** — `GET /admin/fields/type-settings`; DB-sourced option data formatted as `[{value, label}]` to match macro signature.
8. **Twig partials** — `_settings_panel.twig`, `_settings_widget.twig` (dispatches to macros), `_settings_key_value.twig`, `_settings_structured_rows_columns.twig`.
9. **Update create / edit views** — settings card; JS type dropdown handler with re-init calls.
10. **Update requests** — dynamic settings rules in `StoreFieldRequest` / `EditFieldRequest`.
11. **Update actions** — `CreateNewField` / `EditField` extract, filter, and normalise settings on save.
12. **Write tests** — unit tests for steps 1–6; feature tests for steps 7–11.

Steps 1–4 are pure PHP/Twig with no UI risk and can be merged independently. Step 6's first five types are also pure PHP. Structured Rows should be last in step 6 because it depends on the `structured_rows_columns` widget from step 8. Steps 7–9 and steps 10–11 touch disjoint files and can proceed in parallel once step 6 is done.
