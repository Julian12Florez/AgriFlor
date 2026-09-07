<?php

namespace App\Services\FarmBackfill;

/**
 * Lo que de verdad se escribió, ya verificado dentro de la transacción.
 */
final class FarmBackfillResult
{
    /**
     * @param  FarmBackfillPlan  $applied  plan RECALCULADO dentro de la transacción (no el del pre-flight)
     * @param  array<string, string>  $archivedBackups  tabla de respaldo → nombre del histórico archivado
     */
    public function __construct(
        public readonly FarmBackfillPlan $applied,
        public readonly int $entryMovements,
        public readonly int $nettingMovements,
        public readonly int $inventoryRowsCreated,
        public readonly int $inventoryRowsUpdated,
        public readonly int $inventoryRowsBackedUp,
        public readonly int $checksRun,
        public readonly array $archivedBackups = [],
    ) {
    }
}
