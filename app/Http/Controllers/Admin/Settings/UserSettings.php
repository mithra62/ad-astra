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
    use ValidatesSettingFields;

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

                $resolvedValues = $this->settings->all($domain->handle, $user);

                $overrideHandles = SettingValue::where('domain', $domain->handle)
                    ->where('user_id', $user->id)
                    ->pluck('field_handle')
                    ->toArray();

                return [
                    'domain'           => $domain,
                    'fields'           => $overridableFields,
                    'field_values'     => $resolvedValues,
                    'override_handles' => $overrideHandles,
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
     *
     * Validation runs first against each overridable field's declared 'rules'.
     * Only handles marked user_overridable in config are written — anything
     * else in the POST body is silently ignored.
     */
    public function update(Request $request): RedirectResponse
    {
        $user    = Auth::user();
        $domains = SettingDomain::ordered()->get();

        // Collect all overridable fields across domains and validate in one pass.
        $allOverridableFields = [];
        foreach ($domains as $domain) {
            foreach ($domain->overridableConfigFields() as $field) {
                $allOverridableFields[] = $field;
            }
        }

        $this->validateSettingFields($allOverridableFields, $request);

        $allHandles = array_column($allOverridableFields, 'handle');
        $submitted  = $request->only($allHandles);

        // Persist, grouped by domain so setMany busts cache once per domain.
        foreach ($domains as $domain) {
            $overridableFields = $domain->overridableConfigFields();

            if (empty($overridableFields)) {
                continue;
            }

            $toWrite = [];

            foreach ($overridableFields as $field) {
                $handle = $field['handle'];

                if (($field['type'] ?? 'text') === 'boolean') {
                    $toWrite[$handle] = $request->has($handle);
                } elseif (array_key_exists($handle, $submitted)) {
                    $toWrite[$handle] = $submitted[$handle];
                }
            }

            if (! empty($toWrite)) {
                $this->settings->setMany($domain->handle, $toWrite, user: $user);
            }
        }

        return redirect()
            ->route('settings.user')
            ->with('success', 'Your preferences have been saved.');
    }
}
