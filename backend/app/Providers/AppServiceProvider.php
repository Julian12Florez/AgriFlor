<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use App\Models\ReceptionBatch;
use App\Models\ProductOutput;
use App\Models\InventoryMovement;
use App\Models\Adjustment;
use App\Observers\ReceptionBatchObserver;
use App\Observers\ProductOutputObserver;
use App\Observers\InventoryMovementObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register morph map for polymorphic relationships
        Relation::enforceMorphMap([
            'purchase' => 'App\Models\Purchase',
            'output' => 'App\Models\ProductOutput',
            'application' => 'App\Models\Application',
            // Modelos auditados (owen-it/laravel-auditing usa morph para auditable_type)
            'product' => 'App\Models\Product',
            'reception' => 'App\Models\Reception',
            'brand' => 'App\Models\Brand',
            'location' => 'App\Models\Location',
            'supplier' => 'App\Models\Supplier',
            'user' => 'App\Models\User',
            'adjustment' => 'App\Models\Adjustment',
        ]);

        // Register model observers for automatic inventory management
        ReceptionBatch::observe(ReceptionBatchObserver::class);
        ProductOutput::observe(ProductOutputObserver::class);
        InventoryMovement::observe(InventoryMovementObserver::class);
    }
}
