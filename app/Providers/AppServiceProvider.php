<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MappingService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // MappingService를 Singleton으로 등록
        $this->app->singleton(MappingService::class, function ($app) {
            return new MappingService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 여기에 부트스트랩 관련 코드를 추가할 수 있습니다.
    }
}
