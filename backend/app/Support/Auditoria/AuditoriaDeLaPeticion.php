<?php

namespace App\Support\Auditoria;

use App\Models\Concerns\Auditado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Events\AuditCustom;
use OwenIt\Auditing\Events\Audited;
use WeakReference;

/**
 * Lo que la auditoría necesita recordar mientras dura UNA petición (la acción
 * de un usuario).
 *
 * El problema que resuelve: los controladores guardan el encabezado de un
 * documento ANTES que sus líneas (Purchase::create y después cada
 * PurchaseItem), así que en el evento `created` del encabezado la compra
 * todavía no tiene productos. Y al eliminar o editar, las líneas se borran
 * primero y en lote (`$compra->purchaseItems()->delete()`), sin eventos.
 *
 * Cómo:
 *  1. ANTES de tocar un documento ya existente (editar o borrar el encabezado,
 *     o crear/editar/borrar una de sus líneas) se le toma una foto: así estaba.
 *  2. Cada registro de auditoría de la petición queda anotado. Cuando la
 *     transacción se confirma (o enseguida, si no hay transacción) la foto de
 *     esos registros se rehace con el documento ya completo.
 *  3. Si el documento se creó en esta misma petición, sus ediciones
 *     posteriores (el costo total, lo esperado de una recepción, el estado
 *     tras recibir el lote) son parte de la MISMA acción: no se guardan como
 *     "Editó" sueltos, se integran al registro de creación.
 *  4. Si solo cambiaron las líneas (el encabezado quedó igual), se deja
 *     constancia como edición del documento con lo que cambió.
 *
 * La foto de un registro, entonces, es el documento tal como quedó al
 * terminar la acción del usuario; la de un registro de eliminación, tal como
 * estaba justo antes de borrarlo.
 *
 * REGLA: la auditoría nunca tumba la operación de negocio. Todo lo de aquí
 * corre dentro de los eventos del modelo o después del commit (afterCommit
 * propaga las excepciones): un fallo al armar la foto haría que el usuario
 * reciba 500 con los datos YA guardados, reintente y duplique. Por eso cada
 * punto de entrada atrapa cualquier error, lo reporta al log y sigue; en el
 * peor caso el registro de auditoría queda sin foto (la pantalla lo lee como
 * un registro viejo).
 */
class AuditoriaDeLaPeticion
{
    /** Que un carácter raro nunca impida guardar la foto. */
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /** Clave de caché de "¿audits ya tiene la columna snapshot?" (la migración la borra). */
    public const CACHE_COLUMNA_DE_FOTO = 'auditoria.audits_tiene_snapshot';

    private ?WeakReference $peticion = null;

    /** @var array<string, array> foto del documento antes de tocarlo, por "alias:id" */
    private array $antes = [];

    /** @var array<string, array<int, string>> registros de auditoría de esta petición: "alias:id" → [id del registro → evento] */
    private array $registros = [];

    /** @var array<string, true> documentos creados en esta petición */
    private array $creados = [];

    /** @var array<string, true> documentos cuya foto falta rehacer */
    private array $pendientes = [];

    /**
     * ID → nombre ya resueltos en esta petición: un lote de 50 asignaciones de
     * la misma tarea no busca 50 veces el nombre de la tarea.
     *
     * @var array<string, string>
     */
    private array $nombres = [];

    private bool $refrescando = false;

    private static ?bool $hayColumnaDeFoto = null;

    /** @var array<string, array<int, string>> columnas de cada tabla (para no leer lo que no se audita) */
    private static array $columnas = [];

    public static function actual(): self
    {
        return app(self::class)->alDia();
    }

    /**
     * La auditoría está encendida para este proceso (en consola solo si
     * `audit.console`, como el paquete).
     */
    public static function activa(): bool
    {
        if (app()->runningInConsole()) {
            return Config::get('audit.enabled', true) && Config::get('audit.console', false);
        }

        return Config::get('audit.enabled', true);
    }

    /**
     * Defensa: si la migración de la foto no corrió, auditar sigue funcionando
     * como antes en vez de tumbar cada guardado con "Unknown column". Se
     * guarda en caché para no preguntarle al esquema en cada petición; la
     * migración la borra al aplicarse o revertirse.
     */
    public static function hayColumnaDeFoto(): bool
    {
        if (self::$hayColumnaDeFoto !== null) {
            return self::$hayColumnaDeFoto;
        }

        $consultar = function (): bool {
            $conexion = Config::get('audit.drivers.database.connection');
            $tabla = Config::get('audit.drivers.database.table', 'audits');

            return Schema::connection($conexion)->hasColumn($tabla, 'snapshot');
        };

        try {
            $hay = (bool) Cache::rememberForever(self::CACHE_COLUMNA_DE_FOTO, $consultar);
        } catch (\Throwable $e) {
            // Sin caché disponible se pregunta directo; si tampoco se puede,
            // se audita sin foto.
            try {
                $hay = $consultar();
            } catch (\Throwable $e) {
                report($e);
                $hay = false;
            }
        }

        return self::$hayColumnaDeFoto = $hay;
    }

    // ------------------------------------------------------------------
    // Ganchos de los modelos
    // ------------------------------------------------------------------

    /**
     * Antes de editar o borrar el encabezado.
     *
     * @param  bool  $original  tomar los valores ORIGINALES (en `updating` el modelo ya trae los nuevos)
     */
    public function recordarAntes(Model $documento, bool $original): void
    {
        $this->sinRomperNada(function () use ($documento, $original) {
            $clave = self::clave($documento);
            if (isset($this->antes[$clave]) || isset($this->creados[$clave]) || !$documento->exists) {
                return;
            }

            $copia = $documento;
            if ($original) {
                $copia = clone $documento;
                $copia->setRawAttributes($documento->getRawOriginal(), true);
            }

            $this->antes[$clave] = $this->fotoConNombres($copia);
        });
    }

    /** Antes de crear, editar o borrar una línea del documento. */
    public function recordarAntesDe(string $alias, mixed $id): void
    {
        if (!is_scalar($id) || $id === '') {
            return;
        }

        $this->sinRomperNada(function () use ($alias, $id) {
            $clave = "{$alias}:{$id}";
            if (isset($this->antes[$clave]) || isset($this->creados[$clave])) {
                return;
            }

            if ($documento = self::cargar($alias, (string) $id)) {
                $this->antes[$clave] = $this->fotoConNombres($documento);
            }
        });
    }

    /** Cambió una línea del documento (o el documento se integró a su creación). */
    public function documentoTocado(string $alias, mixed $id): void
    {
        if (!is_scalar($id) || $id === '') {
            return;
        }
        $this->pendientes["{$alias}:{$id}"] = true;
        $this->programarRefresco();
    }

    public function creadoEnEstaPeticion(Model $documento): bool
    {
        return isset($this->creados[self::clave($documento)]);
    }

    /**
     * La foto que se guarda con el registro que se está escribiendo. Si no se
     * puede armar, el registro se guarda igual, sin foto.
     *
     * @param  array<string, mixed>  $datos  lo que el paquete va a insertar en `audits`
     */
    public function conFoto(Model $documento, array $datos): array
    {
        try {
            if (!self::hayColumnaDeFoto()) {
                return $datos;
            }

            $clave = self::clave($documento);

            if (($datos['event'] ?? null) === 'deleted') {
                $foto = $this->antes[$clave] ?? $this->fotoConNombres($documento);

                // Las líneas se borraron sin eventos (p. ej. con DB::table) antes
                // que el encabezado: se usan las de su última foto guardada.
                if ($foto['lineas'] === [] && FotoDeAuditoria::tieneLineas($documento)) {
                    $foto['lineas'] = $this->ultimasLineasGuardadas($documento);
                }
            } else {
                $foto = $this->conLineasDeAntes($clave, $this->fotoConNombres($documento), $datos['event'] ?? null);
            }

            $json = json_encode($foto, self::JSON);
            if ($json !== false) {
                $datos['snapshot'] = $json;
            }
        } catch (\Throwable $e) {
            report($e);
            unset($datos['snapshot']);
        }

        return $datos;
    }

    /**
     * Cada registro escrito queda anotado (escucha `OwenIt\Auditing\Events\Audited`).
     * Solo los documentos con líneas necesitan rehacer la foto después del
     * commit: a los demás su foto les quedó completa al escribirse.
     */
    public function alAuditar(Audited $evento): void
    {
        $this->sinRomperNada(function () use ($evento) {
            $this->alDia();
            $documento = $evento->model;
            if (!$evento->audit || !$documento instanceof Model || !in_array(Auditado::class, class_uses_recursive($documento), true)) {
                return;
            }

            $clave = self::clave($documento);
            $tipo = $evento->audit->getAttribute('event');
            $this->registros[$clave][$evento->audit->getKey()] = $tipo;

            // Si lo que cambió es algo que otras fotos nombran (un producto,
            // una finca), su nombre se vuelve a buscar.
            unset($this->nombres[(string) $documento->getKey()]);

            if ($tipo === 'created') {
                $this->creados[$clave] = true;
            }

            if ($tipo === 'deleted' || $this->refrescando || !FotoDeAuditoria::tieneLineas($documento)) {
                return;
            }

            $this->pendientes[$clave] = true;
            $this->programarRefresco();
        });
    }

    // ------------------------------------------------------------------
    // Refresco al confirmar
    // ------------------------------------------------------------------

    /**
     * Si hay transacción abierta, corre al confirmarla (si se revierte, no
     * corre: los registros tampoco existen). Si no la hay, corre ya.
     */
    private function programarRefresco(): void
    {
        $this->sinRomperNada(fn () => DB::afterCommit(fn () => $this->refrescar()));
    }

    /**
     * Rehace la foto de los registros de esta petición con el documento ya
     * completo. Corre después del commit: un error aquí no puede salir hacia
     * el usuario (los datos ya están guardados), así que cada documento se
     * procesa por separado y su fallo solo se reporta.
     */
    public function refrescar(): void
    {
        if ($this->refrescando || $this->pendientes === [] || !self::hayColumnaDeFoto()) {
            return;
        }

        $this->refrescando = true;

        try {
            foreach (array_keys($this->pendientes) as $clave) {
                unset($this->pendientes[$clave]);
                $this->sinRomperNada(fn () => $this->refrescarDocumento($clave));
            }
        } finally {
            $this->refrescando = false;
        }
    }

    private function refrescarDocumento(string $clave): void
    {
        [$alias, $id] = explode(':', $clave, 2);
        $documento = self::cargar($alias, $id);
        if (!$documento) {
            // Se eliminó en esta misma petición: su registro de eliminación ya
            // lleva la foto de cómo estaba.
            return;
        }

        $registros = $this->registros[$clave] ?? [];
        if ($registros === []) {
            $this->dejarConstanciaDeLineas($documento, $clave);

            return;
        }

        $modelo = Config::get('audit.implementation', \OwenIt\Auditing\Models\Audit::class);
        $foto = $this->fotoConNombres($documento);

        foreach ($registros as $registro => $tipo) {
            if ($tipo === 'deleted') {
                continue;
            }

            $cambios = [];
            if (($json = json_encode($this->conLineasDeAntes($clave, $foto, $tipo), self::JSON)) !== false) {
                $cambios['snapshot'] = $json;
            }

            // Lo que se editó en la misma acción que creó el documento queda
            // en el registro de creación.
            if ($tipo === 'created' && ($json = json_encode($documento->valoresDeCreacionParaAuditoria(), self::JSON)) !== false) {
                $cambios['new_values'] = $json;
            }

            if ($cambios !== []) {
                $modelo::query()->whereKey($registro)->toBase()->update($cambios);
            }
        }
    }

    /**
     * Solo cambiaron las líneas (o lo que la foto dice del documento sin ser
     * una columna suya, como las fincas de una orden técnica) y el encabezado
     * quedó igual: sin esto el cambio no dejaría rastro. Se registra como
     * edición del documento, con lo que cambió.
     */
    private function dejarConstanciaDeLineas(Model $documento, string $clave): void
    {
        $this->sinRomperNada(function () use ($documento, $clave) {
            if (!isset($this->antes[$clave])) {
                return;
            }

            $antes = $this->antes[$clave];
            $ahora = $this->fotoConNombres($documento);

            [$datosAntes, $datosAhora] = $this->datosQueCambiaron($antes['datos'], $ahora['datos']);

            if ($antes['lineas'] == $ahora['lineas'] && $datosAntes === [] && $datosAhora === []) {
                return;
            }

            $documento->auditEvent = 'updated';
            $documento->isCustomEvent = true;
            $documento->auditCustomOld = $datosAntes;
            $documento->auditCustomNew = $datosAhora;

            try {
                Event::dispatch(new AuditCustom($documento));
            } finally {
                $documento->isCustomEvent = false;
                $documento->auditCustomOld = $documento->auditCustomNew = null;
            }
        });
    }

    /**
     * Datos de la foto (con etiqueta) que cambiaron, como pares antes/después.
     *
     * @return array{0: array<string, ?string>, 1: array<string, ?string>}
     */
    private function datosQueCambiaron(array $antes, array $ahora): array
    {
        $porEtiqueta = function (array $datos): array {
            $mapa = [];
            foreach ($datos as [$etiqueta, $valor]) {
                if ($etiqueta !== null) {
                    $mapa[$etiqueta] = $valor;
                }
            }

            return $mapa;
        };
        $a = $porEtiqueta($antes);
        $b = $porEtiqueta($ahora);

        $viejos = $nuevos = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $etiqueta) {
            if (($a[$etiqueta] ?? null) !== ($b[$etiqueta] ?? null)) {
                $viejos[$etiqueta] = $a[$etiqueta] ?? null;
                $nuevos[$etiqueta] = $b[$etiqueta] ?? null;
            }
        }

        return [$viejos, $nuevos];
    }

    private function conLineasDeAntes(string $clave, array $foto, ?string $tipo): array
    {
        if ($tipo !== 'created' && isset($this->antes[$clave]) && $this->antes[$clave]['lineas'] != $foto['lineas']) {
            $foto['lineas_antes'] = $this->antes[$clave]['lineas'];
        }

        return $foto;
    }

    /** @return array<int, array{clave: string, texto: string}> */
    private function ultimasLineasGuardadas(Model $documento): array
    {
        $modelo = Config::get('audit.implementation', \OwenIt\Auditing\Models\Audit::class);

        $fotos = $modelo::query()
            ->where('auditable_type', $documento->getMorphClass())
            ->where('auditable_id', $documento->getKey())
            ->whereNotNull('snapshot')
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('snapshot');

        foreach ($fotos as $foto) {
            $lineas = json_decode((string) $foto, true)['lineas'] ?? [];
            if ($lineas !== []) {
                return $lineas;
            }
        }

        return [];
    }

    // ------------------------------------------------------------------

    /** La foto de un registro (punto único: las pruebas lo reemplazan para simular un fallo). */
    protected function foto(Model $documento): array
    {
        return FotoDeAuditoria::de($documento, $this->nombres);
    }

    /** La foto, recordando los nombres que resolvió para las siguientes. */
    private function fotoConNombres(Model $documento): array
    {
        $foto = $this->foto($documento);
        $this->nombres = ($foto['nombres'] ?? []) + $this->nombres;

        return $foto;
    }

    /** Corre $paso; si falla, lo reporta al log y la operación sigue. */
    private function sinRomperNada(callable $paso): void
    {
        try {
            $paso();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Una petición nueva empieza en limpio. Se compara contra el objeto de la
     * petición (no hay "fin de petición" fiable en las pruebas, donde varias
     * peticiones comparten la misma aplicación).
     */
    private function alDia(): self
    {
        $actual = app()->bound('request') ? app('request') : null;

        if ($this->peticion?->get() !== $actual) {
            $this->antes = $this->registros = $this->creados = $this->pendientes = $this->nombres = [];
            $this->peticion = $actual ? WeakReference::create($actual) : null;
        }

        return $this;
    }

    private static function clave(Model $documento): string
    {
        return $documento->getMorphClass() . ':' . $documento->getKey();
    }

    /**
     * El documento tal como está en la base, sin las columnas que no se
     * auditan (el logo en base64 de una empresa pesa cientos de KB y la foto
     * no lo usa).
     */
    private static function cargar(string $alias, string $id): ?Model
    {
        $clase = Relation::getMorphedModel($alias);
        if (!$clase) {
            return null;
        }

        $modelo = new $clase();
        $consulta = $clase::query();

        $excluir = method_exists($modelo, 'getAuditExclude') ? $modelo->getAuditExclude() : [];
        if ($excluir !== []) {
            $columnas = self::$columnas[$clase] ??= Schema::connection($modelo->getConnectionName())->getColumnListing($modelo->getTable());
            $consulta->select(array_values(array_diff($columnas, $excluir)));
        }

        return $consulta->find($id);
    }
}
