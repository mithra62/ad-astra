<?php

namespace App\Services;

/**
 * Backward-compatible alias for EntryService.
 *
 * Use the Entries facade or EntryService directly for new code.
 * The Content facade resolves to this class so existing call-sites continue to work.
 */
class ContentService extends EntryService
{
}
