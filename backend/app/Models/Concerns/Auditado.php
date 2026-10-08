<?php

namespace App\Models\Concerns;

use App\Support\Auditoria\AuditoriaDeLaPeticion;
use App\Support\Auditoria\Vocabulario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;

/**
 * Auditoría legible: lo mismo que `OwenIt\Auditing\Auditable`, más
 *
 *  - una FOTO del registro en palabras (`audits.snapshot`, ver
 *    {@see \App\Support\Auditoria\FotoDeAuditoria}), tomada en el momento de la
 *    acción y completada cuando el documento termina de guardarse;
 *  - sin ediciones vacías: un cambio que solo toca espacios, saltos de línea
 *    (CRLF ↔ LF), el formato de un número (5.00 ↔ 5) o la hora de una columna
 *    que solo guarda la fecha no es una edición, ni se guarda ni se muestra;
 *  - lo que se edita en la misma petición que creó el registro (el costo de
 *    una salida, lo esperado de una recepción) queda en el registro de
 *    creación, no como un "Editó" suelto.
 *
 * Un modelo auditable usa este trait en vez de `Auditable` y declara su alias
 * en el morph map (AppServiceProvider), o cada guardado revienta con 500.
 */
trait Auditado
{
    use Auditable {
        readyForAuditing as protected listoParaElPaquete;
    }

    public static function bootAuditado(): void
    {
        if (!AuditoriaDeLaPeticion::activa()) {
            return;
        }

        // Cómo estaba ANTES de tocarlo: es la foto de un registro eliminado y
        // el "antes" de las líneas en una edición.
        static::updating(fn (Model $m) => AuditoriaDeLaPeticion::actual()->recordarAntes($m, true));
        static::deleting(fn (Model $m) => AuditoriaDeLaPeticion::actual()->recordarAntes($m, false));
    }

    public function readyForAuditing(): bool
    {
        if (!$this->listoParaElPaquete()) {
            return false;
        }

        if ($this->isCustomEvent || $this->auditEvent !== 'updated') {
            return true;
        }

        try {
            $peticion = AuditoriaDeLaPeticion::actual();

            if ($peticion->creadoEnEstaPeticion($this)) {
                // Parte de la misma acción que lo creó: se integra a ese registro.
                $peticion->documentoTocado($this->getMorphClass(), $this->getKey());

                return false;
            }

            [$antes, $despues] = $this->cambiosConValor();

            return $antes !== [] || $despues !== [];
        } catch (\Throwable $e) {
            // Ante la duda se audita, como lo haría el paquete solo.
            report($e);

            return true;
        }
    }

    public function transformAudit(array $data): array
    {
        try {
            if (!$this->isCustomEvent) {
                [$extraAntes, $extraDespues] = $this->cambiosExtraDeAuditoria();
                $antes = ($data['old_values'] ?? []) + $extraAntes;
                $despues = ($data['new_values'] ?? []) + $extraDespues;

                if (($data['event'] ?? null) === 'updated') {
                    [$antes, $despues] = $this->sinCambiosVacios($antes, $despues);
                }

                $data['old_values'] = $antes;
                $data['new_values'] = $despues;
            }
        } catch (\Throwable $e) {
            // Se guarda lo que el paquete armó, sin depurar.
            report($e);
        }

        // conFoto() ya se protege sola: si la foto falla, el registro va sin ella.
        return AuditoriaDeLaPeticion::actual()->conFoto($this, $data);
    }

    /**
     * Los valores de un registro de creación, leídos de nuevo: los usa el
     * refresco cuando el documento terminó de guardarse en la misma acción.
     *
     * @return array<string, mixed>
     */
    public function valoresDeCreacionParaAuditoria(): array
    {
        $this->auditEvent = 'created';
        $this->resolveAuditExclusions();
        [, $nuevos] = $this->getCreatedEventAttributes();
        [, $extra] = $this->cambiosExtraDeAuditoria();

        return array_filter($nuevos + $extra, fn ($valor) => $valor !== null);
    }

    /**
     * Pares antes/después que se agregan al registro sin venir de una columna
     * (p. ej. "Logo: cambió" en vez de copiar la imagen). Por defecto, nada.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function cambiosExtraDeAuditoria(): array
    {
        return [[], []];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function cambiosConValor(): array
    {
        $this->resolveAuditExclusions();
        [$antes, $despues] = $this->getUpdatedEventAttributes();
        [$extraAntes, $extraDespues] = $this->cambiosExtraDeAuditoria();

        return $this->sinCambiosVacios($antes + $extraAntes, $despues + $extraDespues);
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function sinCambiosVacios(array $antes, array $despues): array
    {
        $casts = $this->getCasts();

        foreach (array_keys($despues + $antes) as $campo) {
            if (Vocabulario::equivalentes($antes[$campo] ?? null, $despues[$campo] ?? null, $casts[$campo] ?? null, (string) $campo)) {
                unset($antes[$campo], $despues[$campo]);
            }
        }

        return [$antes, $despues];
    }
}
