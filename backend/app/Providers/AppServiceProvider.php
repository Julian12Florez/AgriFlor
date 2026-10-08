<?php

namespace App\Providers;

use App\Support\Auditoria\AuditoriaDeLaPeticion;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OwenIt\Auditing\Events\Audited;
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
        // Memoria de la auditoría durante una petición (foto "antes", registros
        // por completar). Ver App\Support\Auditoria\AuditoriaDeLaPeticion.
        $this->app->singleton(AuditoriaDeLaPeticion::class);
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
            'role' => 'App\Models\Role',
            // Faltaba: Company es auditable y sin alias cada guardado de una
            // empresa terminaba en error 500 (ClassMorphViolationException).
            'company' => 'App\Models\Company',
            // Módulos que se empezaron a auditar el 7-oct-2026. La etiqueta en
            // español de cada uno vive en App\Support\Auditoria\Vocabulario.
            'category' => 'App\Models\Category',
            'base_unit' => 'App\Models\BaseUnit',
            'packaging_unit' => 'App\Models\PackagingUnit',
            'output_type' => 'App\Models\OutputType',
            'farm_lot' => 'App\Models\FarmLot',
            'technical_recipe' => 'App\Models\TechnicalRecipe',
            'technical_order' => 'App\Models\TechnicalOrder',
            'task_catalog' => 'App\Models\TaskCatalog',
            'task_schedule' => 'App\Models\TaskSchedule',
            'task_daily_log' => 'App\Models\TaskDailyLog',
            'performance_settings' => 'App\Models\PerformanceSettings',
            'alert' => 'App\Models\Alert',
            'worker' => 'App\Models\Worker',
            'task' => 'App\Models\Task',
            'daily_assignment' => 'App\Models\DailyAssignment',
            'task_deduction' => 'App\Models\TaskDeduction',
        ]);

        // Cada registro de auditoría queda anotado para completar su foto
        // cuando el documento termine de guardarse (las líneas van después).
        Event::listen(Audited::class, [AuditoriaDeLaPeticion::class, 'alAuditar']);

        // Register model observers for automatic inventory management
        ReceptionBatch::observe(ReceptionBatchObserver::class);
        ProductOutput::observe(ProductOutputObserver::class);
        InventoryMovement::observe(InventoryMovementObserver::class);
    }
}
