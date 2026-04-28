<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Settings\UpdateDomainSettings;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Settings\UpdateDomainSettingsRequest;
use App\Models\SettingDomain;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class Domain extends Controller
{
    /**
     * List all setting domains.
     */
    public function index(): View
    {
        return $this->view('settings.index', [
            'domains' => SettingDomain::ordered()->get(),
        ]);
    }

    /**
     * Show the settings form for a single domain.
     */
    public function show(string $handle): View
    {
        $domain = SettingDomain::where('handle', $handle)->firstOrFail();
        $allDomains = SettingDomain::ordered()->get();

        $groupedFields = $this->groupFields(
            config("settings.{$handle}.fields", []),
            visibleOnly: true
        );

        return $this->view('settings.show', [
            'domain' => $domain,
            'domains' => $allDomains,
            'grouped_fields' => $groupedFields,
            'field_values' => $this->settings->system($handle),
        ]);
    }

    /**
     * Group field definitions by their 'group' key.
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
            $grouped[$field['group'] ?? ''][] = $field;
        }

        return $grouped;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Persist system-level settings for a domain.
     */
    public function update(UpdateDomainSettingsRequest $request, string $handle): RedirectResponse
    {
        SettingDomain::where('handle', $handle)->firstOrFail();

        app(UpdateDomainSettings::class)->execute($handle, $request->settingsPayload());

        return redirect()
            ->route('settings.show', $handle)
            ->with('success', 'Settings saved.');
    }
}
