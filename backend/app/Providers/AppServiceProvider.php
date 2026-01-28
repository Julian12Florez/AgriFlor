<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use App\Models\ReceptionBatch;
use App\Models\ProductOutput;
use App\Models\InventoryMovement;
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
        ]);

        // Register model observers for automatic inventory management
        ReceptionBatch::observe(ReceptionBatchObserver::class);
        ProductOutput::observe(ProductOutputObserver::class);
        InventoryMovement::observe(InventoryMovementObserver::class);
    }
}
