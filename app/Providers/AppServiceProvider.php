<?php

namespace App\Providers;

use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\CompanyEloquentRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompanyRepositoryInterface::class, CompanyEloquentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
