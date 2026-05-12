# Media and Media Library Layer Overview

_Status: native Media layer complete and in testing as of 2026-05-12._

## Purpose

The Media layer is the first-party file management layer for the CMS. It replaces the previous Spatie MediaLibrary plan/runtime dependency with Laravel-native models, migrations, upload helpers, field integration, transformations, and cleanup jobs.

The layer has two main concepts:

- `App\Models\Media\Library`: an upload container and policy boundary.
- `App\Models\Media`: an individual stored file and its metadata.

## Core Model Shape

### Media Libraries

`media_libraries` defines where and how files can be uploaded.

Key responsibilities:

- Store the library `name` and unique `handle`.
- Choose the storage adapter/disk via `adapter`.
- Hold adapter-specific JSON settings in `adapter_settings`.
- Limit uploads with `allowed_types` and `max_size`.
- Optionally attach a `field_layout_id` so media can carry custom field values.
- Attach category groups and field groups through existing polymorphic group traits.
- Own ordered media through `Library::media()`.

Implementation touchpoints:

- Model: `app/Models/Media/Library.php`
- Upload behavior: `app/Traits/HasMediaItems.php`
- Seeder: `database/seeders/MediaLibrarySeeder.php`

### Media Records

`media` stores one row per uploaded file.

Key responsibilities:

- Store the owning `library_id`.
- Store display and original file names.
- Store MIME type, disk, path, byte size, and sort order.
- Provide URL, temporary URL, existence, and image checks.
- Hold custom field values through `Fieldable`.
- Hold derived image/file variants through `HasTransformations`.
- Soft-delete records so physical files can be purged later.

Implementation touchpoints:

- Model: `app/Models/Media.php`
- Storage facade: `app/Services/MediaStorageService.php`
- Transformations: `app/Traits/HasTransformations.php`, `app/Services/Media/*TransformationDriver.php`

## Upload Flow

The admin upload flow is:

```text
POST /admin/media/libraries/{library_id}/upload
  -> App\Http\Controllers\Admin\Media\Library::upload()
  -> App\Actions\Media\Library\UploadMedia::upload()
  -> App\Services\MediaStorageService::upload()
  -> Media\Library::addMediaFromUpload()
```

`HasMediaItems::addMediaFromUpload()` validates the uploaded file against the library's `max_size` and `allowed_types`, stores the file on the configured disk, then creates the `media` row inside a transaction. If the database write fails after the file has been stored, the method deletes the orphaned file before rethrowing.

`sort_order` is assigned inside the transaction using a locked max query so concurrent uploads do not receive duplicate order values.

## Attachment Flow

Attachable models use `App\Traits\HasMedia`.

Current direct users include:

- `Entry`
- `User`

The polymorphic pivot is `mediables`:

| Column | Purpose |
|---|---|
| `media_id` | The attached media item |
| `mediable_type` / `mediable_id` | The owning model |
| `field_id` | Attachment scope |
| `sort_order` | Owner-specific ordering |

`field_id` is intentionally non-null:

- `0` means direct attachment, such as avatars or direct media picks.
- Any value greater than `0` is a real `fields.id` and means the media is referenced through a `FileUpload` custom field.

This sentinel design lets the unique index prevent duplicate direct attachments while still supporting multiple field-scoped references to the same media item.

## FileUpload Field Integration

`App\Field\Types\FileUpload` stores ordered media IDs in `field_values.value_json`.

On field value save, `App\Observers\FieldValueObserver` detects FileUpload fields and syncs the ordered IDs into `mediables`. On delete, it removes only the pivot rows for that specific field and owner.

Validation currently checks:

- Minimum and maximum selected file counts.
- Submitted media IDs exist.
- Optional library scoping by `library_id` or `library_handle`.
- Optional field-level MIME type restrictions.

Resolved field values return `Collection<Media>` in the saved order, so templates and services can work with Media models rather than raw IDs.

## Transformations

Media transformations are stored in `media_transformations`.

The transformation layer is driver-based:

- `TransformationDriverInterface` defines dispatch/process behavior.
- `ImagickTransformationDriver` is preferred when the extension is loaded.
- `GDTransformationDriver` is used when GD is available.
- `NullTransformationDriver` keeps the system operational when neither image extension is available.

Calling `$media->transform('thumbnail', $params)` creates or reuses a transformation record. Pending records dispatch through the configured driver; completed records are reused; failed records can be retried by calling `transform()` again.

## Deletion and Purging

Media deletion is deliberately staged.

- App-level deletion soft-deletes the `media` row through `MediaStorageService::delete()` or `MediaRepository::delete()`.
- `PurgeDeletedMedia` later finds soft-deleted rows past the grace period.
- Purging deletes transformation files, deletes the original physical file, and force-deletes the media record.
- Deleting a media library dispatches `ProcessMediaLibraryRemoval`, which soft-deletes media by `library_id`; `media.library_id` intentionally has no FK so this async cleanup path can find the rows after the library is deleted.

## Admin Surfaces

Media libraries are managed under `/admin/media/libraries/*`.

Media items are managed under `/admin/media/*`.

The admin layer uses actions and repositories rather than writing model attributes directly:

- `CreateNewMediaLibrary`
- `EditMediaLibrary`
- `DeleteMediaLibrary`
- `UploadMedia`
- `EditMedia`
- `DeleteMedia`
- `MediaRepository`

## Current Testing Stage

The layer is implemented and under test coverage for models, traits, repositories, services, actions, transformations, and upload/delete behavior. Treat it as the current architecture. Future work should extend it rather than revive the old Spatie MediaLibrary approach.

## Planned Follow-Up

`docs/media-status-implementation-plan.md` covers a separate follow-up: status governance for media libraries and media records. That work should be treated as an extension to the completed native Media layer, not part of the original refactor.
