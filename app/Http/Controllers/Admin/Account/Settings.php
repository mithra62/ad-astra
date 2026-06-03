<?php

namespace App\Http\Controllers\Admin\Account;

use App\Actions\Settings\UpdateUserSettings;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Settings\UpdateUserSettingsRequest;
use App\Models\SettingDomain;
use App\Models\SettingValue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class Settings extends Controller
{
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

                return [
                    'domain' => $domain,
                    'fields' => $this->hydrateOptions($overridableFields),
                    'field_values' => $this->settings->all($domain->handle, $user),
                    'override_handles' => SettingValue::where('domain', $domain->handle)
                        ->where('user_id', $user->id)
                        ->pluck('field_handle')
                        ->toArray(),
                ];
            })
            ->filter()
            ->values();

        return $this->view('account.settings', [
            'domains' => $domainData,
            'all_domains' => $allDomains,
            'user' => $user,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function hydrateOptions(array $fields): array
    {
        return array_map(function (array $field): array {
            if (isset($field['options_callback']) && is_callable($field['options_callback'])) {
                $field['options'] = ($field['options_callback'])();
                unset($field['options_callback']);
            }

            return $field;
        }, $fields);
    }

    /**
     * Persist the authenticated user's personal setting overrides.
     */
    public function update(UpdateUserSettingsRequest $request): RedirectResponse
    {
        app(UpdateUserSettings::class)->execute(Auth::user(), $request->settingsPayload());

        return redirect()
            ->route('account.settings')
            ->with('success', 'Your preferences have been saved.');
    }
}
