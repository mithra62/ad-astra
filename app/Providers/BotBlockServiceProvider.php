<?php

namespace App\Providers;

use App\Models\BbValue;
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
            $params = [
                'field_value' => $value,
                'field_name' => '_' . str()->random(),
                'ip_address' => app('request')->ip(),
            ];

            BbValue::create($params);
            return $value;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

    }
}
