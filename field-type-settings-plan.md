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
| `key_value` | `_structured-rows.twig` macro | Ordered `{key, label}` pairs; two-column instance with `empty_rows=0` |
| `structured_rows_columns` | `_structured-rows.twig` macro | Column-schema editor for Structured Rows FieldType |

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
    {# two-column instance of the structured-rows macro: key + label, no minimum rows #}
    {# NOTE: matrix.scripts() is NOT called here — it is emitted once by the create/edit view layout #}
    {% import 'admin._inc._structured-rows' as sr %}
    {{ sr.field(
        'settings[' ~ handle ~ ']',
        def.label,
        [{handle: 'key', label: 'Key', type: 'text'}, {handle: 'label', label: 'Label', type: 'text'}],
        value,
        def.instructions,
        null, null,
        'Add option',
        0
    ) }}

{% elseif def.type == 'structured_rows_columns' %}
    {# NOTE: matrix.scripts() is NOT called here — same reason as key_value above #}
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
            if (window.initMatrixFields)  window.initMatrixFields();
        });
});
```

For this to work, the three init functions must be extracted from their IIFE wrappers and attached to `window` (or a shared namespace) so they are callable after the initial page load.

`initMatrixFields` is the existing function name inside the IIFE in `_structured-rows.twig`'s `scripts()` macro. It already guards against double-init via `data-matrix-ready="true"`, so calling it again after a panel swap is safe.

**Critical:** `matrix.scripts()` emits a `<script>` tag. Script tags injected via `innerHTML` do not execute — so the AJAX response fragment (`_settings_panel.twig`) must never call `matrix.scripts()`. Instead, `{{ matrix.scripts() }}` must be called unconditionally in the `{% block scripts %}` of the create and edit views. Because `initMatrixFields` is exposed on `window`, the call after the AJAX swap (`window.initMatrixFields()`) picks up any newly injected `[data-matrix-field]` elements without needing a second script tag.

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

`render()` passes the column schema and current row data to the macro. A thin wrapper view (`resources/views/_fields/structured_rows.twig`) imports `_structured-rows.twig` and calls `matrix.field()` with the resolved column schema and row data. It does **not** call `matrix.scripts()` — that is already emitted by the entry create/edit view's `{% block scripts %}` block, following the same rule as the field settings panel.

#### `validate()`

1. Value is an array or null/empty.
2. Row count ≥ `min_rows` and ≤ `max_rows`.
3. Each row is an associative array containing all declared column handles.
4. Extra keys (from removed columns) are stripped silently at render time; missing keys (from added columns) use `column.default` at render time.

---

### Users

Relates a field to one or more User records.

- **Handle:** `users`
- **Storage:** `value_json` — array of integer user IDs: `[3, 14, 27]`

**Why `value_json` and not a pivot table?** A `user_relationships` pivot would be architecturally consistent with `entry_relationships` but adds a new table and a query path ("find all fields referencing user X") that is not yet needed. `value_json` mirrors `FileUpload`'s pattern and keeps the initial schema minimal. This can be upgraded to a pivot later without breaking the `getSetting` / `cast` / `value` contract.

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

`EditField` currently blocks type changes when field values exist. This should remain.

**Mitigation — no values exist, type changes:**
In `EditField::edit()`, when `field_type_id` changes and `fieldValues()->exists()` is false, clear `$field->settings` and populate it with `$newTypeInstance->settingsDefaults()` before calling `$field->update()`. This prevents leftover settings keys from a prior type (e.g. a `placeholder` key on a Boolean field) from appearing in the AJAX settings panel on the edit page.

**Mitigation — UI warning before type switch:**
In `create.twig` and `edit.twig`, the JS type-dropdown handler checks whether any `settings[*]` inputs have been touched (input/change events on the settings panel). If so, it displays an `<div role="alert">` warning — "Changing the field type will discard your current settings." — immediately above the settings card. The warning is dismissed automatically when the new type's panel loads.

**Mitigation — settings panel depopulation:**
When the type dropdown fires the fetch, the existing settings panel is replaced unconditionally with the new type's panel (or the "no configurable settings" message). No attempt is made to carry over values from the previous type.

---

### `key_value` repeater serialisation

Options lists (Select, Multi Select, Radio Group) store ordered `{key, label}` pairs as a JSON array. The repeater widget sends `settings[options][0][key]`, `settings[options][0][label]`, etc.

**Mitigation:**
`filterSettings()` in both `CreateNewField` and `EditField` normalises `key_value` inputs before storage:

```php
private function normaliseKeyValue(array $raw): array
{
    // $raw = [['key' => 'foo', 'label' => 'Foo'], ...]
    // Strip rows where both key and label are empty (phantom trailing rows).
    return array_values(
        array_filter($raw, fn($row) => trim($row['key'] ?? '') !== '' || trim($row['label'] ?? '') !== '')
    );
}
```

Order is preserved — do not sort alphabetically on save. The stored format is always:
```json
[{"key": "foo", "label": "Foo"}, {"key": "bar", "label": "Bar"}]
```

If the field type expects `key_value` but the submitted `settings[options]` is absent (e.g. the admin deleted all rows), `filterSettings()` stores an empty array `[]`, not `null`. Field types that require at least one option should enforce this via their `settingsRules()` (e.g. `'settings.options' => 'required|array|min:1'`).

---

### Options-based types and orphaned values

Select, Multi Select, and Radio Group share the same problem: a stored entry value may no longer be a valid option key if the field's settings are later edited. On a fresh installation this can only occur from admin actions taken after content has been entered — there is no pre-existing data to clean up. The mechanism is built in from the start so it works correctly as soon as the first entries are created.

**Mitigation — render path (`ValidatesAgainstOptions` trait):**
The trait provides an `isValidOption(mixed $value, array $options): bool` helper. The `render()` method of each type calls this before building the select/radio/checkbox HTML. If the stored value is not in the current options list:
- For Select and Radio Group: add a disabled `<option>` or `<label>` rendered as `"[orphaned: {value}]"` with a `text-red-500` style and a `data-orphaned="true"` attribute. The input is shown, not hidden.
- For Multi Select: each stored value is checked individually; orphaned values are shown as disabled checked options.

**Mitigation — entry save (`strict_options`):**
- Default (`strict_options = false`): orphaned values are stored as-is. No validation error is raised.
- When `strict_options = true`: `validate()` rejects the value with the error message `"The selected value '{value}' is no longer a valid option."`. The trait implements this branching once; all three types call `$this->validateAgainstOptions($value, $this->getSetting('options', []))`.

**Mitigation — field settings save:**
When `EditField::edit()` saves a settings change for an options-based type, after `$field->save()` it checks `$field->fieldValues()->count()`. If `strict_options` is enabled and count > 0, it logs a warning (not an exception) and the controller flashes a notice: "There are N entries with field values that may no longer match the updated options list." No retroactive data repair is performed.

---

### DB-sourced one-off settings

For field types whose settings include DB-sourced lists (Relationship's entry groups, FileUpload's media libraries, Users' roles), the AJAX route fetches current DB state on every call and passes it as `options` alongside the settings form definition.

**Mitigation:**
Do not cache the settings panel HTML. The `typeSettings()` controller method must always query the DB fresh. Each affected type provides a `settingsFormOptions(): array` method that returns a map of `handle => [['value' => ..., 'label' => ...], ...]` for any settings widget that needs DB-sourced options. `AbstractField` provides an empty default:

```php
public function settingsFormOptions(): array { return []; }
```

The AJAX controller merges the returned arrays into the `settings_form` descriptor before passing it to the view:

```php
$form = $instance->settingsForm();
foreach ($instance->settingsFormOptions() as $handle => $options) {
    $form[$handle]['options'] = $options;
}
```

This keeps DB queries out of the type class while letting the type declare which settings need dynamic option lists.

---

### `Number` and `Slider` share `storageColumn()` logic

Both return `value_float` when `decimals > 0`, `value_integer` otherwise.

**Mitigation:**
Extract into `app/Field/Concerns/HasDecimalStorage.php` at the point `Slider` is implemented (Step 6), since that is when the duplication first appears. Do not pre-emptively extract it in Step 5 when `Number` is updated — wait until there is an actual second consumer.

```php
trait HasDecimalStorage
{
    public function storageColumn(): string
    {
        return ((int) $this->getSetting('decimals', 0)) > 0
            ? 'value_float'
            : 'value_integer';
    }
}
```

---

### Structured Rows column schema changes

On a fresh installation there is no pre-existing data to migrate. However, once content editors begin creating entries, an admin may later edit the column schema. The render path must handle this gracefully from day one.

**Column removal:** stored JSON still contains the old key; it is silently ignored at render time because the template iterates only declared columns.

**Column addition:** existing rows lack the new key; the normalisation step fills the gap with `null`.

**Mitigation — render path:**
In `StructuredRows::render()`, each row is normalised before being passed to the view:

```php
$row = array_merge(
    array_fill_keys(array_column($columns, 'handle'), null),
    $row
);
```

This ensures every declared column has a key (defaulting to `null`) regardless of when the row was saved. Extra keys from removed columns exist in the data but are ignored by the template.

**Mitigation — column handle rename:** If an admin renames a column's `handle`, existing entries lose the mapping silently (old key ignored, new key defaults to null). This is identical in effect to column removal followed by column addition. Document this in the `structured_rows_columns` settings widget with a visible inline note: "Renaming a handle will clear existing values for that column."

The stored JSON is never rewritten by a settings save alone. Only an explicit entry save triggers a FieldValue update, at which point the current column set is serialised.

---

### Users FieldType and sensitive data

`Users::value()` resolves IDs to User models.

**Mitigation:**
Scope at the query level, not in application code:
```php
public function value($value): \Illuminate\Support\Collection
{
    if (empty($value)) return collect();
    return \App\Models\User::select(['id', 'name', 'email'])
        ->whereIn('id', (array) $value)
        ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', (array) $value)) . ')')
        ->get();
}
```
The `select()` restriction means model instances never carry password hashes, tokens, `remember_token`, or any other sensitive column — even if the caller passes the collection directly to a template or API response. This is enforced at the query, not via attribute hiding, which can be bypassed.

---

### Validation failure and settings panel repopulation

When a create/edit form fails validation, the full page is re-rendered (not AJAX). The controller passes `old('settings', $field->settings ?? [])` as `current_values` to the view. The `_settings_panel.twig` partial is included server-side on re-render — no AJAX call is made. The macros' internal `old()` calls restore the user's input.

**Mitigation:**
On the create page (no existing field), the initial settings panel must also be rendered server-side on first load — use the type that is pre-selected in the dropdown (defaulting to `text`). This avoids a flash of "no settings" before the JS handler fires.

The controller's `create()` method must therefore resolve and render the initial settings form:
```php
$defaultType = FieldType::where('object', \App\Field\Types\Text::class)->first();
$data['initial_settings_form'] = $defaultType ? $defaultType->instance()->settingsForm() : [];
$data['current_values'] = old('settings', []);
```

---

### Fresh installation — no data migration required

This plan is implemented on a fresh installation. The `fields.settings` column is already `json()->nullable()` in `database/migrations/2026_04_14_000001_create_fields_table.php`, and `field_types.settings` is likewise nullable JSON in `2026_04_13_215842_create_field_types_table.php`. No schema migrations are needed anywhere in this plan.

The `?? []` null-guards throughout the code (`$this->settings ?? []`, `old('settings', [])`, etc.) are retained as standard defensive PHP — the column is nullable, so a field created before settings are introduced would have `null` — but they require no special backward-compat treatment on this installation.

---

## Testing

- `settingsDefaults()` returns correct defaults for each type.
- `settingsRules()` generates correct Laravel rule strings.
- `Type::instance($fieldSettings)` merges type defaults with field overrides correctly.
- `CreateNewField` / `EditField` strip undeclared settings keys including the `color` macro's `_hex` suffix.
- `StoreFieldRequest` validation rejects invalid settings values.
- `GET /admin/fields/type-settings` returns 200 with correct HTML fragment; DB-sourced options populated.
- Settings panel updates on type dropdown change; `initColorFields`, `initRangeSliders`, `initMatrixFields` re-called after swap.
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

Each step below is independently reviewable and shippable. No step introduces a breaking change to the existing UI or data layer. Steps are ordered so that earlier steps lay foundations later steps depend on; within a step, files are listed in the order they should be changed.

---

### Step 1 — Standardise `$settings_form` schema in `AbstractField`

**Goal:** Establish the canonical descriptor schema and helper methods. No UI change; no behaviour change.

**Files to change:**

- `app/Field/AbstractField.php`
  - Add three public methods after `getSetting()`:
    - `settingsForm(): array` — returns `$this->settings_form`
    - `settingsDefaults(): array` — maps `$settings_form` keys to their `default` value (null when absent)
    - `settingsRules(): array` — maps `$settings_form` keys to `"settings.{key}" => $def['rules'] ?? 'nullable'`
  - No constructor change; no property change.

- `app/Field/Types/Text.php`
  - Replace the sparse `$settings_form` definition with the full schema:
    ```php
    protected array $settings_form = [
        'placeholder' => [
            'type'         => 'text',
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
        'min_length' => [
            'type'    => 'number',
            'label'   => 'Min Length',
            'default' => null,
            'rules'   => 'nullable|integer|min:0',
        ],
    ];
    ```

**Tests to add** — new file `tests/Unit/Field/AbstractFieldSettingsTest.php`:
- `settingsForm()` returns the declared `$settings_form` array.
- `settingsDefaults()` returns correct defaults including `null` for undeclared defaults.
- `settingsRules()` produces keys prefixed with `settings.` and falls back to `nullable`.
- A type with empty `$settings_form` (e.g. `EmailAddress`) returns empty arrays from all three helpers.

**Review checkpoint:** Run `php artisan test --filter=AbstractFieldSettings`. Zero UI change.

---

### Step 2 — Fix settings propagation through `Field\Type::instance()` and `Field`

**Goal:** Field-specific settings (`fields.settings`) reach the type instance at render time. Currently only type-level settings are passed.

**Files to change:**

- `app/Models/Field/Type.php`
  - Change `instance(): AbstractField` signature to `instance(array $fieldSettings = []): AbstractField`.
  - Replace `new $class($this->settings ?? [])` with:
    ```php
    $merged = array_merge($this->settings ?? [], $fieldSettings);
    return new $class($merged);
    ```

- `app/Models/Field.php`
  - Add `typeInstance(): AbstractField` method:
    ```php
    public function typeInstance(): AbstractField
    {
        return $this->fieldType->instance($this->settings ?? []);
    }
    ```
  - Update `render()` to call `$this->typeInstance()->render($params)` instead of `$this->fieldType->instance()->render($params)`.

- `app/Models/FieldValue.php`
  - In `resolvedValue()`, replace both calls to `$fieldType->instance()` with `$this->field->typeInstance()`.
  - Result: `$column = $this->field->typeInstance()->storageColumn()` and `return $this->field->typeInstance()->value($this->{$column})`.

**Tests to add/update:**
- `tests/Unit/Models/FieldTest.php` — add: `typeInstance()` returns an `AbstractField`; `typeInstance()` merges field settings over type defaults; `render()` delegates to `typeInstance()`.
- `tests/Unit\Models/FieldValueTest.php` — add: `resolvedValue()` uses field settings (e.g. a `Text` field with `['placeholder' => 'test']` in settings; the instance receives that setting).
- Update existing `tests/Unit/Actions/Field/FieldActionsTest.php` tests that call `instance()` — they pass no args, so they are backward compatible as written.

**Review checkpoint:** Run `composer test`. No behaviour change for fields that have `settings = null` (the `?? []` guards both sides). Fields that do have settings now correctly pass them to the type instance.

---

### Step 3 — Add `multiple` boolean parameter to the `select` macro

**Goal:** Enable the multi-select widget type in the settings panel without any new macro.

**Files to change:**

- `resources/views/admin/_inc/_form-fields.twig`
  - Find the `select` macro signature and add `multiple = false` as the last parameter.
  - Add `{% if multiple %}multiple{% endif %}` to the `<select>` element.
  - Update the `selected` check inside the options loop:
    ```twig
    {% set is_selected = multiple
        ? (option.value in (value ?? []))
        : (selected_value == option.value) %}
    ```
  - Ensure existing callers that pass no `multiple` argument continue to work (default `false`).

**Tests to add** — this is a Twig template; test via feature test. Add a case to whatever existing feature test renders admin forms, or note for Step 12's feature test pass.

**Review checkpoint:** Render any existing admin form that uses the `select` macro. Visual output identical to before.

---

### Step 4 — Expose JS init functions globally

**Goal:** Allow the type-dropdown AJAX handler (Step 9) to re-initialise color pickers, sliders, and structured-rows after the settings panel HTML is swapped in.

**Files to change** — identify the exact files in the asset pipeline that define these inits (likely compiled from `resources/js/` or inlined in admin layout partials). For each:

- `initColorFields` — extract from its IIFE and assign to `window.initColorFields = function() { ... }`. Guard with `data-color-ready` to prevent double-init.
- `initRangeSliders` — same pattern, guard with `data-slider-ready`.
- `initMatrixFields` — this function already exists by that name inside the IIFE in the `scripts()` macro of `resources/views/admin/_inc/_structured-rows.twig`. It already guards against double-init via `if (field.dataset.matrixReady === 'true') return;`. Expose it on `window` by assigning `window.initMatrixFields = initMatrixFields;` before the IIFE closes.

The IIFE wrapper itself can remain; just expose the inner function on `window` before the IIFE closes.

**`matrix.scripts()` placement rule:** The `scripts()` macro emits a `<script>` tag containing the IIFE and `initMatrixFields`. Script tags injected via `innerHTML` (i.e. from the AJAX panel swap) do not execute. Therefore `{{ matrix.scripts() }}` must be called **once, in the `{% block scripts %}` of `create.twig` and `edit.twig`**, not inside any partial or AJAX-returned fragment. The field create/edit views already have a `{% block scripts %}` block — this is where the call goes (Step 9 adds it). No other template in the admin area calls `matrix.scripts()`.

**Tests:** Manual smoke test only. Run `composer run dev`, load a field create/edit page, verify color and slider widgets still initialise on first load.

**Review checkpoint:** No functional change on existing pages. JS test: call `window.initMatrixFields()` twice in the browser console — second call is a no-op because all `[data-matrix-field]` elements already have `data-matrix-ready="true"`.

---

### Step 5 — Update all existing field type `$settings_form` definitions and `render()` methods

**Goal:** Every existing type declares a complete descriptor array; `Boolean` and `ColorPicker` render via the canonical macros.

**Files to change:**

- `app/Field/Types/Textarea.php`
  - Add `$settings_form`: `placeholder` (text), `max_length` (number), `rows` (number, default 4).
  - `render()` already delegates to a view; no change needed unless it does not use the `textarea` macro. Confirm and update to use macro if needed.

- `app/Field/Types/Number.php`
  - Add `$settings_form`: `min` (number), `max` (number), `step` (number, default 1), `decimals` (number, default 0), `default` (number).
  - Update `storageColumn()` to read `decimals` from settings: return `value_float` when `decimals > 0`, otherwise `value_integer`.

- `app/Field/Types/Date.php`
  - Add `$settings_form`: `min_date` (text), `max_date` (text), `default` (text), `format` (text, default `Y-m-d`).

- `app/Field/Types/Boolean.php`
  - Add `$settings_form`: `default` (toggle, default false), `label_on` (text, default `Yes`), `label_off` (text, default `No`).
  - Update `render()` to use the `toggle` macro from `_form-fields.twig` instead of a custom view, passing `label_on`/`label_off` from settings.

- `app/Field/Types/ColorPicker.php`
  - Add `$settings_form`: `format` (select — `hex`/`rgb`/`hsl`), `alpha` (toggle), `presets` (key_value).
  - Update `render()` to delegate to the `color` macro.

- `app/Field/Types/Html.php`
  - Add `$settings_form`: `toolbar` (select — `minimal`/`standard`/`full`), `allowed_tags` (text).

- `app/Field/Types/EmailAddress.php`, `Telephone.php`, `Url.php`
  - Add empty `protected array $settings_form = [];` explicitly (documents the intentional no-settings declaration).
  - Add `settingsFormOptions()` empty default via `AbstractField` (already covered by abstract base default from Step 1).

- `app/Field/Types/FileUpload.php`
  - Add `$settings_form`: `allowed_types` (key_value), `min` (number), `max` (number).
  - Add `settingsFormOptions()` to return library options from `Media\Library::all()` formatted as `[['value' => $l->id, 'label' => $l->name], ...]`.
  - The `library` setting is a one-off multi-select backed by `settingsFormOptions()` — add `library` to `$settings_form` as `type => 'select_multiple'` with no static `options` key (options come from `settingsFormOptions()`).
  - Implement `render()` per the FileUpload AJAX bridge section. This is the most complex change in this step.

- `app/Field/Types/Relationship.php`
  - Add `$settings_form`: `limit` (number).
  - Add `settingsFormOptions()` to return `entry_groups` and `entry_types` option arrays from their respective models.

**Tests to add:**
- `tests/Unit/Field/Types/NumberTest.php` (new or update) — `storageColumn()` returns `value_integer` when `decimals = 0`; returns `value_float` when `decimals > 0`.
- `tests/Unit/Field/Types/ColorPickerTest.php` (new) — `settingsForm()` contains `presets` as `key_value` type.
- For FileUpload: feature test for content negotiation on `Library::upload()` — JSON response when `Accept: application/json`; redirect response otherwise.

**Review checkpoint:** Run `composer test`. All existing tests pass. `Boolean` and `ColorPicker` render correctly via macros (verify visually in browser).

---

### Step 6 — Add new field types

**Goal:** Six new concrete `AbstractField` subclasses, registered in `FieldTypeSeeder`.

Add a `settingsFormOptions()` abstract-default to `AbstractField` (no-op returning `[]`) at the start of this step if not already done in Step 5.

**Files to create:**

- `app/Field/Concerns/ValidatesAgainstOptions.php` — trait with:
  - `isValidOption(mixed $value, array $options): bool` — checks `$value` against `array_column($options, 'key')`.
  - `validateAgainstOptions(mixed $value): bool|string` — reads `strict_options` setting; returns error string or `true`.
  - `renderOrphanedValue(mixed $value, array $options): string` — returns the "orphaned" indicator HTML for use in `render()`.

- `app/Field/Concerns/HasDecimalStorage.php` — trait (described above in Edge Cases section). Used by `Number` (update) and `Slider` (new).

- `app/Field/Types/Select.php`
  - `handle = 'select'`, `storageColumn = 'value_text'`
  - Uses `ValidatesAgainstOptions` trait.
  - `$settings_form`: `options` (key_value, required), `placeholder` (text), `default` (text), `strict_options` (toggle, default false).
  - `render()`: delegates to `select` macro.

- `app/Field/Types/MultiSelect.php`
  - `handle = 'multi_select'`, `storageColumn = 'value_json'`
  - Uses `ValidatesAgainstOptions` trait.
  - `$settings_form`: `options` (key_value, required), `min` (number), `max` (number), `display` (select — `checkboxes`/`multiselect`), `strict_options` (toggle, default false).
  - `cast()` decodes `value_json` to a PHP array of strings.
  - `render()`: delegates to `select` macro with `multiple=true` when `display = multiselect`; renders checkbox list otherwise.

- `app/Field/Types/RadioGroup.php`
  - `handle = 'radio_group'`, `storageColumn = 'value_text'`
  - Uses `ValidatesAgainstOptions` trait.
  - `$settings_form`: `options` (key_value, required), `default` (text), `layout` (select — `stacked`/`inline`), `strict_options` (toggle, default false).
  - `render()`: custom radio group view (no macro equivalent); renders `<label><input type="radio">` loop.

- `app/Field/Types/Slider.php`
  - `handle = 'slider'`
  - Uses `HasDecimalStorage` trait (update `Number` to use it too at this point).
  - `$settings_form`: `min` (number, required), `max` (number, required), `step` (number, default 1), `suffix` (text), `decimals` (number, default 0), `default` (slider — dynamic bounds resolved by AJAX controller).
  - `validate()`: clamps value to `[min, max]` range instead of rejecting.
  - `render()`: delegates to `slider` macro.

- `app/Field/Types/Users.php`
  - `handle = 'users'`, `storageColumn = 'value_json'`
  - `$settings_form`: `limit` (number, default 0), `display` (select — `dropdown`/`checkboxes`/`tokens`).
  - `settingsFormOptions()` returns `roles` option list from `App\Models\Role::all()`.
  - `cast()` returns array of integers.
  - `value()` as described in Edge Cases section.
  - `validate()`: enforces limit; checks all IDs exist; if `roles` set, checks role membership.

- `app/Field/Types/StructuredRows.php`
  - `handle = 'structured_rows'`, `storageColumn = 'value_json'`
  - `$settings_form`: `columns` (structured_rows_columns, required), `min_rows` (number, default 0), `max_rows` (number), `add_label` (text, default `Add row`).
  - `cast()` returns indexed array of row objects (assoc arrays).
  - `validate()` implements row count and column presence checks.
  - `render()`: normalises each row (fills missing column keys with null) then delegates to `_fields/structured_rows.twig` wrapper view.

**Files to update:**
- `app/Field/Types/Number.php` — add `use HasDecimalStorage;`, remove inline `storageColumn()` (now provided by trait).
- `database/seeders/FieldTypeSeeder.php` — append the six new type entries to the `$types` array. The seeder already uses `firstOrCreate(['object' => ...])` so it remains idempotent and safe to re-run. No new seeder file or migration is required — `DatabaseSeeder` already calls `FieldTypeSeeder::class`. The six entries to add:
  ```php
  ['name' => 'Select',          'object' => \App\Field\Types\Select::class],
  ['name' => 'Multi Select',    'object' => \App\Field\Types\MultiSelect::class],
  ['name' => 'Radio Group',     'object' => \App\Field\Types\RadioGroup::class],
  ['name' => 'Slider',          'object' => \App\Field\Types\Slider::class],
  ['name' => 'Users',           'object' => \App\Field\Types\Users::class],
  ['name' => 'Structured Rows', 'object' => \App\Field\Types\StructuredRows::class],
  ```

**Tests to add** — new file `tests/Unit/Field/Types/`:
- `SelectTest.php`: valid/invalid option validation; `strict_options` respected; orphaned value indicator present in render output.
- `MultiSelectTest.php`: `cast()` round-trip; min/max enforcement; JSON storage format.
- `RadioGroupTest.php`: shares most logic with Select via trait — test orphan handling.
- `SliderTest.php`: value clamped when outside `[min, max]`; `storageColumn()` returns `value_float` when `decimals > 0`.
- `UsersTest.php`: sensitive columns absent from `value()` output; role filter applied; limit enforced.
- `StructuredRowsTest.php`: missing column keys default to null at render; removed column keys are silently ignored; min/max row validation.

**Review checkpoint:** Run `composer test`. New types are registered; none of them appear in the UI yet (no UI wiring until Steps 7–9).

---

### Step 7 — AJAX route and controller method

**Goal:** Provide the endpoint the type dropdown fetches. Returns an HTML fragment (the settings panel for a given type).

**Files to change:**

- `routes/admin.php`
  - Add before the existing `fields` resource routes:
    ```php
    Route::get('fields/type-settings', [Field::class, 'typeSettings'])->name('fields.type_settings');
    ```
    (Must be registered before `Route::resource('fields', ...)` to avoid the `{id}` parameter swallowing `type-settings`.)

- `app/Http/Controllers/Admin/Field.php`
  - Add `typeSettings(Request $request): \Illuminate\Http\Response` method:
    1. Validate `type_id` (required integer) and optional `field_id` (nullable integer).
    2. Resolve `FieldType::find($request->type_id)` — 404 if not found.
    3. `$instance = $type->instance()` (no field settings at this point — we want the schema, not populated values).
    4. `$form = $instance->settingsForm()`.
    5. Merge DB-sourced options via `$instance->settingsFormOptions()`.
    6. For the Slider type's `default` setting with `type = 'slider'`: resolve `min`/`max`/`step`/`suffix` from sibling setting keys in `$current_values` if `field_id` is provided, or from the schema defaults otherwise.
    7. If `field_id` provided: `$current_values = FieldModel::find($field_id)?->settings ?? []`; otherwise `$current_values = old('settings', [])`.
    8. Return `response()->view('admin.fields._settings_panel', compact('form', 'current_values'))`.

**Tests to add** — `tests/Feature/Admin/FieldTypeSettingsTest.php` (new):
- Returns 200 for a valid `type_id`.
- Returns 404 for a missing `type_id`.
- Returns the "no configurable settings" message for `EmailAddress`.
- Returns a settings panel containing the `placeholder` input for `Text`.
- DB-sourced options (e.g. FileUpload library list) are present in the response HTML.
- When `field_id` provided, current values are pre-populated in the response HTML.

**Review checkpoint:** Hit `GET /admin/fields/type-settings?type_id=1` in the browser. Returns the settings panel for type 1. No existing pages are affected.

---

### Step 8 — Twig partial templates

**Goal:** Build the server-side template layer the AJAX endpoint and the initial page render both use.

**Files to create:**

- `resources/views/admin/fields/_settings_panel.twig`
  - Iterates `settings_form`; includes `_settings_widget` for each entry; shows empty-state message when `settings_form` is empty.
  - Does **not** call `matrix.scripts()` or any other `scripts()` macro — this fragment is returned by the AJAX endpoint and injected via `innerHTML`, so script tags would not execute. All required scripts are already on the page via Step 9's `{% block scripts %}` additions.

- `resources/views/admin/fields/_settings_widget.twig`
  - Dispatcher template as described in §2 of the Proposed Architecture section.
  - Imports `_form-fields.twig` and delegates to its macros for standard widget types.
  - For `key_value`: imports `_structured-rows.twig` and calls `sr.field()` inline with two text columns (`key`, `label`) and `empty_rows=0`. No separate include file needed.
  - For `structured_rows_columns`: includes `_settings_structured_rows_columns` (a thin wrapper that passes the correct column schema to the same macro).

- `resources/views/admin/fields/_settings_structured_rows_columns.twig`
  - A thin include that imports `_structured-rows.twig` and calls `sr.field()` with the six-column schema for defining Structured Rows output columns: `handle` (text), `label` (text), `type` (select — `text`/`textarea`/`number`/`select`/`checkbox`), `placeholder` (text), `width` (text), `options` (text).
  - Outputs as `settings[columns][{i}][handle]`, `settings[columns][{i}][label]`, etc.
  - Kept as a separate file because the column definition array is verbose enough to warrant it; `_settings_widget.twig` remains readable.

- `resources/views/_fields/radio_group.twig` (new field view)
  - Renders the radio button group for `RadioGroup` type content inputs.

- `resources/views/_fields/structured_rows.twig` (new field view, wraps macro)
  - Thin wrapper that imports `_structured-rows.twig` and calls it with the column schema and row data.

**Tests:** Templates are tested indirectly by the feature test in Step 7. Add one integration assertion per new type's settings panel to that test.

**Review checkpoint:** `GET /admin/fields/type-settings?type_id={select_id}` returns a panel with a `key_value` widget for the `options` setting. Rendered HTML is valid.

---

### Step 9 — Update create and edit views

**Goal:** Embed the settings card in both forms; wire the JS type dropdown handler.

**Files to change:**

- `resources/views/admin/fields/create.twig`
  - Add a second card below the "Item Details" card, titled "Field Type Settings".
  - Inside the card, include `_settings_panel` server-side using the initial type's form (resolved in the controller's `create()` method — see Edge Cases §validation failure):
    ```twig
    <div id="field-type-settings">
        {{ include('admin.fields._settings_panel', {
            settings_form: initial_settings_form,
            current_values: current_values
        }) }}
    </div>
    ```
  - Add the following to `{% block scripts %}`:
    1. `{{ matrix.scripts() }}` — emits the structured-rows JS once. Must be here, not in any partial or AJAX fragment (script tags injected via `innerHTML` do not execute).
    2. The type dropdown handler:
    ```js
    document.getElementById('field_type_id').addEventListener('change', function () {
        const fieldId = document.getElementById('field_id')?.value ?? '';
        fetch(`{{ route('fields.type_settings') }}?type_id=${this.value}&field_id=${fieldId}`)
            .then(r => r.text())
            .then(html => {
                document.getElementById('field-type-settings').innerHTML = html;
                if (window.initColorFields)  window.initColorFields();
                if (window.initRangeSliders) window.initRangeSliders();
                if (window.initMatrixFields)  window.initMatrixFields();
            });
    });
    ```
  - Add the unsaved-settings warning logic (see Edge Cases §type change on existing field).

- `resources/views/admin/fields/edit.twig`
  - Same settings card, `matrix.scripts()` call, and JS handler.
  - The controller already passes `field` to the view; add `initial_settings_form` and `current_values` to `edit()`:
    ```php
    $data['initial_settings_form'] = $field->fieldType?->instance()->settingsForm() ?? [];
    $data['current_values']        = old('settings', $field->settings ?? []);
    ```

- `app/Http/Controllers/Admin/Field.php`
  - Update `create()` to resolve and pass `initial_settings_form` and `current_values` (as described above).
  - Update `edit()` to pass `initial_settings_form` and `current_values`.

**Tests:** Add feature tests to `tests/Feature/Admin/FieldTest.php` (or create it):
- `GET /admin/fields/{group_id}/create` renders the settings card.
- `GET /admin/fields/{id}/edit` renders the settings card with the field's current settings pre-populated.
- After a validation failure, `POST /admin/fields/{group_id}/create` re-renders with `old()` values in the settings panel.

**Review checkpoint:** Load the create and edit pages in the browser. The settings card is visible. Switching the type dropdown updates the settings panel via AJAX. No data is lost on save.

---

### Step 10 — Update `StoreFieldRequest` and `EditFieldRequest`

**Goal:** Validate `settings[*]` keys dynamically based on the submitted field type.

**Files to change:**

- `app/Http/Requests/Field/StoreFieldRequest.php`
  - In `rules()`, after the base rules, resolve the type and merge its settings rules:
    ```php
    $typeId = $this->input('field_type_id');
    if ($typeId && $type = FieldType::find($typeId)) {
        $base = array_merge($base, $type->instance()->settingsRules());
    }
    return $base;
    ```
  - Add `use App\Models\Field\Type as FieldType;` import.

- `app/Http/Requests/Field/EditFieldRequest.php`
  - `EditFieldRequest` extends `StoreFieldRequest`; if the `rules()` method is inherited, no change is needed. If it overrides `rules()`, apply the same merge.
  - Confirm the class structure — the existing file extends `StoreFieldRequest`, so the dynamic rules are inherited automatically.

**Tests to add** — `tests/Feature/Admin/FieldValidationTest.php` (new or extend existing):
- `POST /admin/fields/{group_id}/create` with `field_type_id` for Text and `settings[max_length] = 'abc'` → fails validation with error on `settings.max_length`.
- `POST /admin/fields/{group_id}/create` with valid settings → passes.
- `PUT /admin/fields/{id}` with invalid settings → fails.

**Review checkpoint:** Run `php artisan test --filter=FieldValidation`. Settings validation is enforced; unrelated field rules are unchanged.

---

### Step 11 — Update `CreateNewField` and `EditField` actions

**Goal:** Extract, filter, and normalise settings before storage. Discard old settings when type changes on a value-free field.

**Files to change:**

- `app/Actions/Field/CreateNewField.php`
  - Add a private `filterSettings(array $raw, AbstractField $instance): array` method:
    1. Keep only keys declared in `$instance->settingsForm()` (strips `_hex` suffixes and any injected keys).
    2. For each `key_value` key: call `normaliseKeyValue()` to strip phantom rows.
    3. For each missing key in the form schema: fill in `$instance->settingsDefaults()[$key]` if the submitted value is absent.
  - In `createByGroup()` and `create()`, after resolving the type, call `filterSettings()` and store the result as `$input['settings']`.

- `app/Actions/Field/EditField.php`
  - Add the same `filterSettings()` method (consider extracting to a shared trait `app/Actions/Field/Concerns/FiltersFieldSettings.php`).
  - After the type-change guard block, if the type has changed (and no values exist), reset: `$input['settings'] = $newInstance->settingsDefaults()`.
  - Otherwise: `$input['settings'] = $this->filterSettings($input['settings'] ?? [], $newInstance)`.
  - After `$field->save()`, if the type is options-based and `strict_options` is enabled and values exist, log the warning and return a metadata flag (or add a flash message via the session).

**Tests to add** — extend `tests/Unit/Actions/Field/FieldActionsTest.php`:
- `filterSettings()` strips undeclared keys (including `_hex` suffix from color macro).
- `filterSettings()` fills in defaults for missing keys.
- `filterSettings()` normalises `key_value` arrays (strips empty rows, preserves order).
- `EditField::edit()` clears old settings when type changes on a value-free field.
- `CreateNewField::create()` persists only declared settings keys.

**Review checkpoint:** Run `composer test`. Create a field with type `Text`, submit `settings[placeholder] = 'foo'` and `settings[unknown_key] = 'bar'` — only `placeholder` is stored. The `_hex` suffix from the color macro does not appear in the stored JSON.

---

### Step 12 — `FileUpload` AJAX upload bridge

**Goal:** Give `FileUpload` a working `render()` method. This step is intentionally last because it is the most complex and touches the media layer.

**Files to change:**

- `app/Http/Controllers/Admin/Media/Library.php`
  - Add JSON content negotiation to the `upload()` method per §FileUpload `render()` section.
  - The existing redirect path is preserved exactly. Only add the `$request->expectsJson()` branches.

- `resources/views/_fields/file_upload.twig` (new)
  - Uses the `file` macro as the drop zone.
  - JS intercepts `change` event, POSTs to the library upload endpoint with `Accept: application/json`, injects hidden inputs for returned media IDs, renders chips.
  - Handles `max=1` (replace chip) and `max>1` (append, disable at limit).
  - Repopulates from `old()` or existing `value` collection on page load.

- `app/Field/Types/FileUpload.php`
  - Implement `render()` per the plan's §FileUpload `render()` section.
  - Add private `resolveLibraryId(): ?int` and `buildAcceptString(): string` helpers.

**Tests to add:**
- `tests/Feature/Admin/Media/LibraryUploadTest.php` (extend existing if present):
  - `POST media/libraries/{id}/upload` with `Accept: application/json` returns JSON `{id, name, url}` on success.
  - `POST media/libraries/{id}/upload` with `Accept: application/json` returns 422 JSON on failure.
  - `POST media/libraries/{id}/upload` without JSON accept header returns a redirect (existing behaviour).
- `tests/Unit/Field/Types/FileUploadTest.php`:
  - `resolveLibraryId()` resolves by ID.
  - `buildAcceptString()` returns correct MIME list from `allowed_types` settings.
  - `render()` passes `library_id` and `accept` to the view.

**Review checkpoint:** Run `composer test`. Load an entry form that includes a `FileUpload` field — it renders the drop zone. Upload a file — it posts to the AJAX endpoint and injects the hidden input. No regression on the media library admin page upload form.

---

### Step 13 — Write remaining tests

**Goal:** Fill any gaps left by prior steps; add integration tests for the full save-and-reload cycle.

**New test files:**

- `tests/Feature/Admin/FieldSettingsSaveTest.php`
  - Create a `Select` field with options via the admin form; verify `fields.settings` stored correctly.
  - Edit a `Text` field; change `placeholder`; verify updated in DB.
  - Create a `Slider` field; verify `min`/`max` stored; render the field on an entry form and confirm slider macro receives correct bounds.
  - Create a `StructuredRows` field; add two columns; save; reload; verify columns present in settings panel.

- `tests/Feature/Admin/FieldSettingsValidationTest.php`
  - Submit a `Select` type with empty `options` — fails with `settings.options required`.
  - Submit a `Slider` type without `min` — fails with `settings.min required`.
  - Submit a `Users` type with `limit = -1` — fails.

- `tests/Unit/Field/Types/ValidatesAgainstOptionsTest.php`
  - `isValidOption()` returns true for a key in the list.
  - `isValidOption()` returns false for a key not in the list.
  - `validateAgainstOptions()` returns error string when `strict_options = true` and value is orphaned.
  - `validateAgainstOptions()` returns `true` when `strict_options = false` regardless of orphan status.

**Review checkpoint:** Run `composer test`. Full suite passes. No regressions.

---

### Parallelism notes

- Steps 1–4 are independent of each other and of the UI. They can be reviewed and merged before any UI work starts.
- Step 5 (updating existing types) can proceed after Steps 1 and 2 are merged.
- Step 6 (new types) depends on Step 5 completing the trait extraction for `HasDecimalStorage`.
- Steps 7 and 8 depend on Step 6 being complete so all type classes exist.
- Step 9 depends on Steps 7 and 8.
- Steps 10 and 11 depend on Step 9 (forms must exist to test validation and action filtering).
- Step 12 (FileUpload) is independent of Steps 6–11 after Step 5 adds `render()` scaffolding; it can proceed in parallel once Step 5 is done.
- Step 13 fills gaps; it should be done last but individual test files can be written during their respective steps.
