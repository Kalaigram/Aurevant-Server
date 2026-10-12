<?php

namespace App\Providers;

use App\Application\Audit\Listeners\PersistAudit;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Audit\Events\AuditTrail;
use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\Purchase\Repositories\GoodsReceiptRepositoryInterface;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierBillRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Domain\Report\Repositories\SalesAggregateRepositoryInterface;
use App\Domain\Report\Repositories\SummaryRepositoryInterface;
use App\Domain\Sale\Repositories\CashShiftRepositoryInterface;
use App\Domain\Sale\Repositories\SaleRepositoryInterface;
use App\Domain\Sale\Repositories\SaleReturnRepositoryInterface;
use App\Domain\Sale\Repositories\SaleSequenceRepositoryInterface;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Domain\Stock\Repositories\StockMovementRepositoryInterface;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\AssignmentEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\AuditLogEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\BranchEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\CashShiftEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\CategoryEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\CompanyEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\CustomerEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\GoodsReceiptEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\ItemEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\ItemPriceEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\PurchaseOrderEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\RoleEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SaleEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SaleReturnEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SalesAggregateEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SaleSequenceEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\ServiceOrderEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockBalanceEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockMovementEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockOpnameEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\StockTransferEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SummaryEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SupplierBillEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\SupplierEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\UserEloquentRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(RoleRepositoryInterface::class, RoleEloquentRepository::class);
        $this->app->bind(AssignmentRepositoryInterface::class, AssignmentEloquentRepository::class);
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
        $this->app->bind(SaleReturnRepositoryInterface::class, SaleReturnEloquentRepository::class);
        $this->app->bind(SaleSequenceRepositoryInterface::class, SaleSequenceEloquentRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerEloquentRepository::class);
        $this->app->bind(ServiceOrderRepositoryInterface::class, ServiceOrderEloquentRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogEloquentRepository::class);
        $this->app->bind(SalesAggregateRepositoryInterface::class, SalesAggregateEloquentRepository::class);
        $this->app->bind(SummaryRepositoryInterface::class, SummaryEloquentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(AuditTrail::class, PersistAudit::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
