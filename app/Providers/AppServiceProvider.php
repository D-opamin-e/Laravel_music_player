<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MappingService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // MappingService를 Singleton으로 등록
        $this->app->singleton(MappingService::class, function ($app) {
            return new MappingService();
        });
    }
    public function boot(): void
    {
    }
}
