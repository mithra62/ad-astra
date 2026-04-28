<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Admin\Controller;
use App\Models\SettingDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class Domain extends Controller
{
    /**
     * List all setting domains.
     */
    public function index(): View
    {
        $domains = SettingDomain::ordered()->get();

        return $this->view('settings.index', [
            'domains' => $domains,
        ]);
    }

    /**
     * Show the settings form for a single domain.
     */
    public function show(string $handle): View
    {
        $domain     = SettingDomain::where('handle', $handle)->firstOrFail();
        $allDomains = SettingDomain::ordered()->get();
        $fieldValues = $this->settings->system($handle);

        // Group visible fields by their 'group' key for sectioned rendering.
        $groupedFields = $this->groupFields(
            config("settings.{$handle}.fields", []),
            visibleOnly: true
        );

        return $this->view('settings.show', [
            'domain'         => $domain,
            'domains'        => $allDomains,
            'grouped_fields' => $groupedFields,
            'field_values'   => $fieldValues,
        ]);
    }

    /**
     * Persist system-level settings for a domain.
     */
    public function update(Request $request, string $handle): RedirectResponse
    {
        SettingDomain::where('handle', $handle)->firstOrFail();

        $submitted = $request->input('fields', []);
        $fields    = config("settings.{$handle}.fields", []);

        // Normalise boolean fields — unchecked checkboxes are not submitted.
        foreach ($fields as $field) {
            if (($field['type'] ?? 'text') === 'boolean') {
                $submitted[$field['handle']] = isset($submitted[$field['handle']]);
            }
        }

        $this->settings->setMany($handle, $submitted, user: null);

        return redirect()
            ->route('settings.show', $handle)
            ->with('success', 'Settings saved.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Group field definitions by their 'group' key.
     * Returns a plain array: ['Group Name' => [fields], '' => [ungrouped fields]]
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupFields(array $fields, bool $visibleOnly = false): array
    {
        $grouped = [];

        foreach ($fields as $field) {
            if ($visibleOnly && ($field['hidden'] ?? false)) {
                continue;
            }
            $group = $field['group'] ?? '';
            $grouped[$group][] = $field;
        }

        return $grouped;
    }
}
