<?php

namespace App\Http\Requests\Settings;

/**
 * Authorises and validates a system-level settings update for one domain.
 *
 * Field definitions are resolved at runtime from config/settings.php using
 * the 'handle' route parameter, so no migration is required when new fields
 * are added to the config.
 */
class UpdateDomainSettingsRequest extends SettingFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit setting');
    }

    public function rules(): array
    {
        return $this->settingRulesFromFields($this->domainFields());
    }

    /**
     * All field definitions for the domain identified by the route handle.
     *
     * @return array<int, array<string, mixed>>
     */
    private function domainFields(): array
    {
        return config("settings.{$this->route('handle')}.fields", []);
    }

    public function attributes(): array
    {
        return $this->settingAttributesFromFields($this->domainFields());
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Return the fully normalised field payload for this domain.
     *
     * Validated non-boolean values are merged with boolean values derived
     * from checkbox presence, producing a single array ready for persistence.
     *
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        return $this->normaliseFields($this->domainFields());
    }
}
