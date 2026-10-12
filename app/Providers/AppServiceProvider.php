<?php

namespace App\Providers;

use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\Purchase\Repositories\GoodsReceiptRepositoryInterface;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierBillRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Domain\Sale\Repositories\CashShiftRepositoryInterface;
use App\Domain\Sale\Repositories\SaleRepositoryInterface;
use App\Domain\Sale\Repositories\SaleSequenceRepositoryInterface;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Domain\Stock\Repositories\StockMovementRepositoryInterface;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\BranchEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\CashShiftEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\CategoryEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\CompanyEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\GoodsReceiptEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\ItemEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\ItemPriceEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\PurchaseOrderEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SaleEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SaleSequenceEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockBalanceEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockMovementEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockOpnameEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockTransferEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SupplierBillEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SupplierEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\UserEloquentRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompanyRepositoryInterface::class, CompanyEloquentRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserEloquentRepository::class);
        $this->app->bind(BranchRepositoryInterface::class, BranchEloquentRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryEloquentRepository::class);
        $this->app->bind(ItemRepositoryInterface::class, ItemEloquentRepository::class);
        $this->app->bind(ItemPriceRepositoryInterface::class, ItemPriceEloquentRepository::class);
        $this->app->bind(StockBalanceRepositoryInterface::class, StockBalanceEloquentRepository::class);
        $this->app->bind(StockMovementRepositoryInterface::class, StockMovementEloquentRepository::class);
        $this->app->bind(StockTransferRepositoryInterface::class, StockTransferEloquentRepository::class);
        $this->app->bind(StockOpnameRepositoryInterface::class, StockOpnameEloquentRepository::class);
        $this->app->bind(SupplierRepositoryInterface::class, SupplierEloquentRepository::class);
        $this->app->bind(PurchaseOrderRepositoryInterface::class, PurchaseOrderEloquentRepository::class);
        $this->app->bind(GoodsReceiptRepositoryInterface::class, GoodsReceiptEloquentRepository::class);
        $this->app->bind(SupplierBillRepositoryInterface::class, SupplierBillEloquentRepository::class);
        $this->app->bind(CashShiftRepositoryInterface::class, CashShiftEloquentRepository::class);
        $this->app->bind(SaleRepositoryInterface::class, SaleEloquentRepository::class);
        $this->app->bind(SaleSequenceRepositoryInterface::class, SaleSequenceEloquentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
