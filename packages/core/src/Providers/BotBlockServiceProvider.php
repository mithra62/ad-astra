<?php

namespace AdAstra\Providers;

use AdAstra\Models\BbValue;
use Illuminate\Support\ServiceProvider;

class BotBlockServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('bb-field', function ($app) {
            $value = md5(str()->random());
            $field_name = '_' . str()->random();

            session()->put('bb_field_name', $field_name);
            $params = [
                'field_value' => $value,
                'field_name' => $field_name,
                'ip_address' => app('request')->ip(),
            ];

            BbValue::create($params);
            return $params;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

    }
}
