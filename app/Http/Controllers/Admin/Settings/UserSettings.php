<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Admin\Controller;
use App\Models\SettingDomain;
use App\Models\SettingValue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserSettings extends Controller
{
    /**
     * Show the per-user preferences form.
     *
     * Only fields marked user_overridable in config/settings.php are shown.
     * Domains with no overridable fields are excluded entirely.
     */
    public function show(): View
    {
        $user = Auth::user();
        $allDomains = SettingDomain::ordered()->get();

        $domainData = $allDomains
            ->map(function (SettingDomain $domain) use ($user) {
                $overridableFields = $domain->overridableConfigFields();

                if (empty($overridableFields)) {
                    return null;
                }

                // Resolved values (user override wins over system over default)
                $resolvedValues = $this->settings->all($domain->handle, $user);

                // Track which field handles the user has personally overridden
                $overrideHandles = SettingValue::where('domain', $domain->handle)
                    ->where('user_id', $user->id)
                    ->pluck('field_handle')
                    ->toArray();

                return [
                    'domain' => $domain,
                    'fields' => $overridableFields,
                    'field_values' => $resolvedValues,
                    'override_handles' => $overrideHandles,
                ];
            })
            ->filter()
            ->values();

        return $this->view('settings.user', [
            'domains' => $domainData,
            'all_domains' => $allDomains,
            'user' => $user,
        ]);
    }

    /**
     * Persist the authenticated user's personal setting overrides.
     *
     * Only handles marked user_overridable in config are written —
     * anything else in the POST body is silently ignored.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $submitted = $request->input('fields', []);

        foreach (SettingDomain::ordered()->get() as $domain) {
            $overridableFields = $domain->overridableConfigFields();

            if (empty($overridableFields)) {
                continue;
            }

            $toWrite = [];

            foreach ($overridableFields as $field) {
                $handle = $field['handle'];

                // Normalise boolean fields (unchecked checkbox = not submitted)
                if (($field['type'] ?? 'text') === 'boolean') {
                    $toWrite[$handle] = isset($submitted[$handle]);
                } elseif (array_key_exists($handle, $submitted)) {
                    $toWrite[$handle] = $submitted[$handle];
                }
            }

            if (!empty($toWrite)) {
                $this->settings->setMany($domain->handle, $toWrite, user: $user);
            }
        }

        return redirect()
            ->route('settings.user')
            ->with('success', 'Your preferences have been saved.');
    }
}
