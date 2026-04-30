# API Documentation Migration Plan
## REST API → OpenAPI 3.0 / Swagger Playground

---

## 1. Current State Assessment

### What's already in place

- **`darkaonline/l5-swagger ^9.0`** is installed and configured — this is the right tool, no library swap needed.
- **`doctrine/annotations ^2.0`** is present, meaning the `@OA\` docblock annotation style works today.
- **Swagger UI** is served at `/api/documentation` and the generated spec at `/docs/api-docs.json`.
- **Three controllers** have partial annotation coverage: `Entries`, `User`, and `Account`.
- **Two shared schemas** (`User`, `Entry`) live on their Resource classes.
- **Global schemas** (`Meta`, `Links`, `PaginationInfo`, `RelatedItem`) live on the base `Api\Controller`.

### Gaps and problems

| Area | Problem |
|------|---------|
| `Entries::store`, `update`, `destroy` | No annotations at all |
| `User::store`, `update`, `destroy` | No annotations at all |
| `Account::update`, `updatePassword`, `updateAvatar`, `updateEmail` | No annotations at all |
| `fields` payload on Entry create/update | Entirely undocumented — this is the hard problem |
| `EntryGroup`-scoped entry listing | No endpoint exists in the API yet |
| Schema discovery for consumers | No way to know what fields an entry group expects |
| PHP 8 Attributes | Still using `@OA\` docblock style — upgrade path available |

---

## 2. Tooling Strategy: Stay with L5-Swagger, Migrate to PHP 8 Attributes

### Why stay with L5-Swagger

L5-Swagger v9 is already installed, partially wired, and generates a working Swagger UI. Replacing it (e.g. with Scramble) would mean rewriting every annotation. The better investment is completing the existing approach properly.

### Migrate from `@OA\` docblocks → PHP 8 `#[OA\...]` Attributes

PHP 8.2 is the minimum runtime here, and L5-Swagger v9 fully supports PHP Attributes. Attributes are:

- Checked by the compiler — typos break the build rather than silently generating a wrong spec
- Visible to PHPStan / IDE tooling
- Easier to refactor (they're code, not strings)

The migration is mechanical: every `@OA\Foo(bar="baz")` becomes `#[OA\Foo(bar: 'baz')]`. The two styles can coexist during the transition, so you can migrate file by file without a big-bang rewrite.

**Example — before (docblock):**
```php
/**
 * @OA\Get(
 *     path="/api/v1/users/{id}",
 *     operationId="getUser",
 *     tags={"Users"},
 *     ...
 * )
 */
public function show($id) { ... }
```

**After (PHP 8 Attribute):**
```php
#[OA\Get(
    path: '/api/v1/users/{id}',
    operationId: 'getUser',
    tags: ['Users'],
    ...
)]
public function show($id) { ... }
```

---

## 3. The Dynamic Fields Problem (and its Solution)

This is the most complex part of the migration. Entries have a dynamic `fields` payload whose shape depends on the `EntryGroup` and `EntryType` — both of which are database-driven. No static OpenAPI spec can fully describe this without help.

The approach is a **three-layer strategy**:

### Layer 1 — Static: Type-Safe Field Schemas per Field Type

Each `AbstractField` subclass maps to a known value shape. Create a reusable OpenAPI schema for each field type value:

| Field Type | OpenAPI Schema name | Value type |
|------------|---------------------|------------|
| `Text` | `FieldValueText` | `string` |
| `Textarea` | `FieldValueTextarea` | `string` |
| `Number` | `FieldValueNumber` | `number` |
| `Boolean` | `FieldValueBoolean` | `boolean` |
| `Date` | `FieldValueDate` | `string, format: date-time` |
| `EmailAddress` | `FieldValueEmail` | `string, format: email` |
| `Url` | `FieldValueUrl` | `string, format: uri` |
| `Telephone` | `FieldValueTelephone` | `string` |
| `ColorPicker` | `FieldValueColor` | `string, pattern: ^#[0-9a-fA-F]{6}$` |
| `Relationship` | `FieldValueRelationship` | `array of integer` |

The `fields` property on an Entry request body is documented as:
```yaml
fields:
  type: object
  description: >
    Key/value map of custom field values. Keys are field handles.
    Call GET /api/v1/entry-groups/{handle}/schema to discover which
    fields are available and their expected types.
  additionalProperties:
    oneOf:
      - $ref: '#/components/schemas/FieldValueText'
      - $ref: '#/components/schemas/FieldValueNumber'
      - $ref: '#/components/schemas/FieldValueBoolean'
      - $ref: '#/components/schemas/FieldValueDate'
      - $ref: '#/components/schemas/FieldValueRelationship'
```

This makes the playground functional — it accepts any field object — while the descriptions guide the developer.

### Layer 2 — Schema Discovery Endpoint (the key addition)

Add a new read-only endpoint that returns the runtime field schema for a given EntryGroup. This is the "contract" endpoint — API consumers call it first to know what fields to send.

```
GET /api/v1/entry-groups
GET /api/v1/entry-groups/{handle}/schema
```

The schema endpoint response looks like:
```json
{
  "entry_group": "blog-posts",
  "fields": [
    {
      "handle": "body",
      "name": "Body",
      "type": "textarea",
      "required": true,
      "settings": {}
    },
    {
      "handle": "featured_image",
      "name": "Featured Image",
      "type": "url",
      "required": false,
      "settings": {}
    },
    {
      "handle": "related_posts",
      "name": "Related Posts",
      "type": "relationship",
      "required": false,
      "settings": { "limit": 3, "entry_group": "blog-posts" }
    }
  ],
  "entry_types": [
    {
      "handle": "blog-post",
      "name": "Blog Post",
      "fields": [...]
    }
  ]
}
```

This endpoint is fully documented in Swagger. The playground workflow becomes:
1. `GET /api/v1/entry-groups` — find available groups
2. `GET /api/v1/entry-groups/blog-posts/schema` — see what fields exist
3. `POST /api/v1/entries` — send the payload with the correct field handles

### Layer 3 — Named Entry Schemas with `oneOf` Discriminator

For the entry types already in the codebase (`BlogPostEntryType`, `EventEntryType`, `JobListingEntryType`, `NewsArticleEntryType`, `PageEntryType`, `PodcastEpisodeEntryType`, `GeneralEntryType`), generate specific request body schemas using `oneOf` with `type_handle` as the discriminator:

```yaml
EntryStoreRequest:
  oneOf:
    - $ref: '#/components/schemas/BlogPostEntryStoreRequest'
    - $ref: '#/components/schemas/EventEntryStoreRequest'
    - $ref: '#/components/schemas/JobListingEntryStoreRequest'
  discriminator:
    propertyName: type_handle
    mapping:
      blog-post: '#/components/schemas/BlogPostEntryStoreRequest'
      event: '#/components/schemas/EventEntryStoreRequest'
      job-listing: '#/components/schemas/JobListingEntryStoreRequest'
```

Each named schema inherits the base entry fields and adds a concrete `fields` object with specific properties. This makes the Swagger playground show the right form when you pick a `type_handle`.

**Important:** These named schemas must be regenerated whenever a field layout changes in the database. An Artisan command (`php artisan swagger:generate-entry-schemas`) should do this and commit the output to a dedicated annotation class.

---

## 4. Step-by-Step Migration Plan

### Phase 1 — Foundation (no breaking changes)

**Step 1.1 — Create a dedicated annotations file for global schemas**

Move the global schema definitions (`Meta`, `Links`, `PaginationInfo`, `RelatedItem`) out of `Api\Controller` and into a standalone class at `app/OpenApi/Schemas/GlobalSchemas.php`. This class has no methods — it exists purely as an annotation anchor. Add the field type value schemas here too.

**Step 1.2 — Add field type value schemas**

In `app/OpenApi/Schemas/FieldSchemas.php`, define each field type's value schema (see the table in Layer 1 above). These become reusable `$ref` targets across all entry-related schemas.

**Step 1.3 — Migrate existing annotations to PHP 8 Attributes**

File by file, convert `@OA\` docblocks to `#[OA\...]` attributes:
1. `Api\Controller` (global schemas)
2. `EntryResource` / `UserResource` (model schemas)
3. `Entries::index`, `Entries::show`
4. `User::index`, `User::show`
5. `Account::show`

Run `php artisan l5-swagger:generate` after each file and verify the Swagger UI renders correctly before moving on.

---

### Phase 2 — Complete Endpoint Coverage

**Step 2.1 — Document `User` write endpoints**

```
POST   /api/v1/users     → User::store
PUT    /api/v1/users/{id} → User::update
DELETE /api/v1/users/{id} → User::destroy
```

Each needs a request body schema (`UserStoreRequest`, `UserUpdateRequest`) derived from the existing `FormRequest` validation rules. These are straightforward — no dynamic fields.

**Step 2.2 — Document `Account` endpoints**

```
PUT   /api/v1/account              → Account::update
PUT   /api/v1/account/password     → Account::updatePassword
PUT   /api/v1/account/email        → Account::updateEmail
PUT   /api/v1/account/avatar       → Account::updateAvatar (multipart/form-data)
```

Note: these endpoints exist in the controller but are not wired in `routes/api.php` yet — that needs to be fixed alongside the annotations.

**Step 2.3 — Document `Entries` write endpoints**

```
POST   /api/v1/entries        → Entries::store
PUT    /api/v1/entries/{id}   → Entries::update
DELETE /api/v1/entries/{id}   → Entries::destroy
```

The request body for `store` and `update` uses the `oneOf` discriminator approach from Layer 3.

---

### Phase 3 — Schema Discovery Endpoints

**Step 3.1 — Create `EntryGroups` API controller**

New file: `app/Http/Controllers/Api/v1/EntryGroups.php`

Two actions:
- `index()` — lists all EntryGroups with basic metadata
- `schema(string $handle)` — returns the full field layout for an EntryGroup, including entry type sub-schemas

Add to `routes/api.php`:
```php
Route::get('/entry-groups', [EntryGroups::class, 'index'])->name('api.v1.entry-groups.index');
Route::get('/entry-groups/{handle}/schema', [EntryGroups::class, 'schema'])->name('api.v1.entry-groups.schema');
```

**Step 3.2 — Create the Schema Resource**

New file: `app/Http/Resources/Api/EntryGroupSchemaResource.php`

This resource walks the `FieldLayout → Tabs → Elements → Field → FieldType` chain and returns a structured JSON description of every field, its type handle, whether it's required, and its settings.

**Step 3.3 — Annotate the discovery endpoints in Swagger**

Document the response schema as `EntryGroupSchema` and `EntryGroupSchemaCollection`. Include a clear description explaining the workflow — these are the "read the manual" endpoints for integrators.

---

### Phase 4 — Generated Named Entry Schemas

**Step 4.1 — Build an Artisan command**

`app/Console/Commands/GenerateEntryTypeSwaggerSchemas.php`

This command:
1. Loads every `EntryType` with its field layout
2. For each type, generates a PHP Attribute block representing its concrete `fields` object
3. Writes these to `app/OpenApi/Schemas/EntryTypeSchemas.php`
4. Should be run as part of CI or after any field layout change

**Step 4.2 — Wire discriminator into `EntryStoreRequest` / `EntryUpdateRequest` schemas**

Update the `Entries::store` and `Entries::update` annotations to reference the generated `oneOf` discriminator schema. The Swagger playground will then show a dropdown for `type_handle` and update the visible fields accordingly.

---

### Phase 5 — Polish and Guardrails

**Step 5.1 — Add response schemas for all error cases**

Every endpoint should document `401 Unauthenticated`, `403 Forbidden`, `404 Not Found`, and `422 Unprocessable Content` (validation errors). Create a reusable `ValidationErrorResponse` schema that mirrors Laravel's default validation error structure:
```json
{
  "message": "The title field is required.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

**Step 5.2 — Add `x-tagGroups` extension to Swagger UI config**

Update `config/l5-swagger.php` to group tags visually in the playground:
```php
'ui' => [
    'display' => [
        'doc_expansion' => 'list',
        'filter' => true,
    ],
],
'constants' => [
    'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost'),
],
```

And in the `@OA\Info` annotation, add tag groups:
```php
#[OA\Tag(name: 'Account', description: 'Authenticated user account')]
#[OA\Tag(name: 'Entries', description: 'Content entry management')]
#[OA\Tag(name: 'Users', description: 'User management')]
#[OA\Tag(name: 'Schema Discovery', description: 'Runtime field schema introspection')]
```

**Step 5.3 — Regenerate on deploy**

Add `php artisan l5-swagger:generate` to your deployment pipeline so the spec is always in sync. Also add `php artisan swagger:generate-entry-schemas` before it so dynamic entry type schemas are fresh.

**Step 5.4 — Test the generated spec**

Use `openapi-generator` or `spectral` in CI to lint the generated spec:
```bash
npx @stoplight/spectral-cli lint storage/api-docs/api-docs.json --ruleset .spectral.yaml
```

---

## 5. File Map — What Gets Created / Changed

```
app/
  OpenApi/
    Schemas/
      GlobalSchemas.php          ← NEW: Meta, Links, PaginationInfo, RelatedItem
      FieldSchemas.php           ← NEW: FieldValueText, FieldValueNumber, etc.
      EntryTypeSchemas.php       ← NEW: Generated by Artisan command (Phase 4)
      ErrorSchemas.php           ← NEW: ValidationErrorResponse, etc.

  Http/
    Controllers/
      Api/
        Controller.php           ← CHANGED: strip @OA schemas (moved to GlobalSchemas)
        v1/
          Account.php            ← CHANGED: add all missing endpoint annotations
          Entries.php            ← CHANGED: add store/update/destroy + field schema
          User.php               ← CHANGED: add store/update/destroy annotations
          EntryGroups.php        ← NEW: schema discovery controller

    Resources/
      Api/
        EntryGroupSchemaResource.php  ← NEW
        EntryGroupSchemaCollection.php ← NEW
        EntryResource.php        ← CHANGED: migrate to PHP 8 Attributes
        UserResource.php         ← CHANGED: migrate to PHP 8 Attributes

  Console/
    Commands/
      GenerateEntryTypeSwaggerSchemas.php  ← NEW

routes/
  api.php                        ← CHANGED: add entry-groups routes + missing account routes

config/
  l5-swagger.php                 ← CHANGED: UI polish, tag display options
```

---

## 6. Handling the Relationship Field in the Playground

The `Relationship` field type deserves special mention because it takes an array of integer IDs (related entry IDs) rather than a scalar. In the Swagger playground this needs to render as an array input, not a text box.

Document it as:
```yaml
FieldValueRelationship:
  type: array
  items:
    type: integer
  description: >
    Array of related Entry IDs. The available entries and the maximum
    number of selections are defined by the field's settings, which
    you can retrieve from the schema discovery endpoint.
```

The `settings` returned by the schema discovery endpoint for a Relationship field will include `entry_group` (which group to draw entries from) and `limit` (max selections). Integrators read this and know to query `/api/v1/entries?entry_group=blog-posts` first, then pass the IDs.

---

## 7. Priority Order

If you want to phase the work pragmatically:

1. **Immediate** — Phase 1 (move schemas to dedicated file, add field type schemas). Unblocks everything else and has zero risk.
2. **High** — Phase 2 (complete endpoint coverage). Fills the most obvious gaps.
3. **High** — Phase 3 (schema discovery endpoints). This is the feature that makes the API truly self-describing and the Swagger playground actually usable for dynamic fields.
4. **Medium** — PHP 8 Attribute migration (Phase 1, Step 1.3). Low risk, high quality-of-life improvement, can be done incrementally.
5. **Lower** — Phase 4 (generated named entry schemas / discriminator). Adds the best Swagger UX for dynamic fields but requires the Artisan generator and discipline to keep it current.
6. **Ongoing** — Phase 5 (error schemas, spectral linting, CI integration).
