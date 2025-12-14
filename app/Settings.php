<?php
namespace App;

use App\Models\Settings AS SettingsModel;

class Settings
{
    /**
     * @var array|string[]
     */
    protected array $defaults = [
        'date_format' => 'Y-m-d',
    ];

    /**
     * @var array
     */
    protected array $changed = [];

    /**
     * @var array
     */
    protected array $settings = [];

    public function __construct()
    {
        $settings = SettingsModel::all();
        foreach ($settings as $setting) {
            $this->settings[$setting->key] = $setting->value;
        }
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param $value
     * @return $this
     */
    public function set(string $key, $value): Settings
    {
        $this->settings[$key] = $this->changed[$key] = $value;
        return $this;
    }

    /**
     * @return void
     */
    public function save()
    {
        foreach($this->changed AS $key => $value) {
            $check = SettingsModel::where([
                'key' => $key
            ])->first();

            if($check instanceof SettingsModel) {
                $check->value = $value;
                $check->save();
            } else {
                SettingsModel::create([
                    'key' => $key,
                    'value' => $value
                ]);
            }
        }
    }
}
