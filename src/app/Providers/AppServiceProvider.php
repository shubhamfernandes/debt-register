<?php

namespace App\Providers;

use App\Contracts\CustomerImportServiceInterface;
use App\Services\CustomerImportService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
        CustomerImportServiceInterface::class,
        CustomerImportService::class
    );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
