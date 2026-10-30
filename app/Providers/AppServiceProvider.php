<?php

namespace App\Providers;

use App\Repositories\EloquentProdutoRepository;
use App\Repositories\ProdutoRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProdutoRepositoryInterface::class, EloquentProdutoRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
