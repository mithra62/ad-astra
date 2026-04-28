<?php

namespace App\Http\Requests\Settings;

use App\Models\SettingDomain;
use Illuminate\Support\Facades\Auth;

/**
 * Authorises and validates a per-user settings preferences update.
 *
 * Only fields marked user_overridable in config/settings.php are included
 * in validation and in the normalised payload — everything else is silently
 * ignored regardless of what is submitted.
 *
 * Any authenticated user may update their own preferences; the gate check
 * delegates to the auth middleware that already protects these routes.
 */
class UpdateUserSettingsRequest extends SettingFormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return $this->settingRulesFromFields($this->overridableFields());
    }

    public function attributes(): array
    {
        return $this->settingAttributesFromFields($this->overridableFields());
    }

    /**
     * Return the normalised payload containing only user-overridable fields.
     *
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        return $this->normaliseFields($this->overridableFields());
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Collect every user-overridable field definition across all domains.
     *
     * The result is a flat list suitable for passing directly to the shared
     * SettingFormRequest helpers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function overridableFields(): array
    {
        $fields = [];

        foreach (SettingDomain::ordered()->get() as $domain) {
            foreach ($domain->overridableConfigFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
