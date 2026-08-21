<?php

namespace App\Services\Rebaseline;

/**
 * Lo que efectivamente ocurrió en la corrida real del re-baseline. Se llena
 * DENTRO de la transacción y solo llega al llamador si la transacción hizo
 * commit: si algo falla, el rollback se lleva por delante estos números junto
 * con las escrituras que describen.
 */
final class RebaselineResult
{
    /**
     * @param  array<int, PlanRow>  $appliedRows  el plan RECALCULADO dentro de la transacción (el que se aplicó)
     * @param  array<int, PlanRow>  $skippedRows  filas del archivo que no se pudieron escribir (producto fuera del catálogo)
     * @param  array<string, string>  $archivedBackups  tabla de respaldo → nombre al que se archivó la corrida anterior
     */
    public function __construct(
        public readonly int $triplesProcessed,
        public readonly int $movementsCreated,
        public readonly int $adjustmentsCreated,
        public readonly int $oldAdjustmentsDeleted,
        public readonly int $oldAdjustmentMovementsDeleted,
        public readonly int $inventoryRowsBackedUp,
        public readonly int $inventoryRowsDeleted,
        public readonly int $inventoryRowsCreated,
        public readonly int $triplesEmptied,
        public readonly int $rowsWithoutPrice,
        public readonly float $valueBefore,
        public readonly float $valueAfter,
        public readonly int $checksRun,
        public readonly array $appliedRows,
        public readonly array $skippedRows,
        public readonly array $archivedBackups,
        public readonly int $triplesAppearedAfterReplan = 0,
    ) {}

    public function valueDelta(): float
    {
        return round($this->valueAfter - $this->valueBefore, 2);
    }
}
