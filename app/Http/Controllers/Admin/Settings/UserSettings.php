<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Settings\UpdateUserSettings;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Settings\UpdateUserSettingsRequest;
use App\Models\SettingDomain;
use App\Models\SettingValue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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
        $user       = Auth::user();
        $allDomains = SettingDomain::ordered()->get();

        $domainData = $allDomains
            ->map(function (SettingDomain $domain) use ($user) {
                $overridableFields = $domain->overridableConfigFields();

                if (empty($overridableFields)) {
                    return null;
                }

                return [
                    'domain'           => $domain,
                    'fields'           => $overridableFields,
                    'field_values'     => $this->settings->all($domain->handle, $user),
                    'override_handles' => SettingValue::where('domain', $domain->handle)
                        ->where('user_id', $user->id)
                        ->pluck('field_handle')
                        ->toArray(),
                ];
            })
            ->filter()
            ->values();

        return $this->view('settings.user', [
            'domains'     => $domainData,
            'all_domains' => $allDomains,
            'user'        => $user,
        ]);
    }

    /**
     * Persist the authenticated user's personal setting overrides.
     */
    public function update(UpdateUserSettingsRequest $request): RedirectResponse
    {
        app(UpdateUserSettings::class)->execute(Auth::user(), $request->settingsPayload());

        return redirect()
            ->route('settings.user')
            ->with('success', 'Your preferences have been saved.');
    }
}
