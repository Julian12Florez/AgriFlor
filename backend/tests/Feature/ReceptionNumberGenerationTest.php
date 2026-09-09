<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Reception;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El consecutivo de recepciones no puede depender de CUÁNTAS hay.
 *
 * Se numeraba con `Reception::count() + 1`. Esa cuenta se rompe sola: basta que
 * se borre UNA recepción para que el contador retroceda y vuelva a proponer un
 * número ya usado. Entonces el índice único lo rechaza y NO SE PUEDE RECEPCIONAR
 * NADA — ni esa ni ninguna otra, porque todas piden el mismo número.
 *
 * Pasó en producción el 09-sep-2026. Al borrar REC-2026-000562 quedaron 607
 * recepciones con máximo REC-2026-000608, así que `count()+1` daba 000608, que ya
 * estaba tomado:
 *
 *     SQLSTATE[23000]: Integrity constraint violation: 1062
 *     Duplicate entry 'REC-2026-000608' for key
 *     'receptions.receptions_reception_number_unique'
 *
 * El bodeguero quedó sin poder recibir hasta que se corrigió.
 */
class ReceptionNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EL CASO DE PRODUCCIÓN: hay un hueco por un borrado, y aun así el siguiente
     * número tiene que ser libre.
     */
    public function test_un_borrado_no_hace_que_se_repita_un_numero(): void
    {
        $f = $this->fixtures();

        // Tres recepciones: 000001, 000002, 000003.
        $this->recepcion($f, 1);
        $segunda = $this->recepcion($f, 2);
        $this->recepcion($f, 3);

        // Se borra una del medio: quedan 2 filas, pero el máximo sigue en 000003.
        $segunda->delete();
        $this->assertSame(2, Reception::count());

        $siguiente = Reception::generateReceptionNumber();

        $this->assertSame(
            'REC-' . date('Y') . '-000004',
            $siguiente,
            'Debe seguir del MÁXIMO, no de la cuenta. Con count()+1 daría 000003, que ya existe.'
        );

        $this->assertFalse(
            Reception::where('reception_number', $siguiente)->exists(),
            'El número propuesto no puede estar tomado: es lo que rompía el insert.'
        );
    }

    /** Sin ninguna recepción todavía, arranca en 1. */
    public function test_arranca_en_uno_cuando_no_hay_ninguna(): void
    {
        $this->assertSame('REC-' . date('Y') . '-000001', Reception::generateReceptionNumber());
    }

    /** Numeración corrida normal. */
    public function test_sigue_el_consecutivo(): void
    {
        $f = $this->fixtures();
        $this->recepcion($f, 7);

        $this->assertSame('REC-' . date('Y') . '-000008', Reception::generateReceptionNumber());
    }

    /**
     * El hueco que deja un borrado se QUEDA como hueco. Un consecutivo documental
     * no se reutiliza: si el número 562 se anuló, nadie más debe llevarlo.
     */
    public function test_no_rellena_los_huecos(): void
    {
        $f = $this->fixtures();
        $this->recepcion($f, 1);
        $this->recepcion($f, 5);

        $this->assertSame(
            'REC-' . date('Y') . '-000006',
            Reception::generateReceptionNumber(),
            'No debe ofrecer el 000002 aunque esté libre.'
        );
    }

    /**
     * Aunque alguien se haya llevado el número entre la consulta y el insert, el
     * generador avanza en vez de devolver uno tomado.
     */
    public function test_esquiva_un_numero_ya_tomado(): void
    {
        $f = $this->fixtures();
        $this->recepcion($f, 1);
        // El 000002 lo toma "otra petición" antes de que insertemos.
        $this->recepcion($f, 2);

        $siguiente = Reception::generateReceptionNumber();

        $this->assertFalse(Reception::where('reception_number', $siguiente)->exists());
        $this->assertSame('REC-' . date('Y') . '-000003', $siguiente);
    }

    // ------------------------------------------------------------------

    private function recepcion(array $f, int $n): Reception
    {
        return Reception::create([
            'reception_number' => 'REC-' . date('Y') . '-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'source_id' => $f['admin']->id, // no importa a qué apunte para esta prueba
            'source_type' => 'purchase',
            'origin_location_id' => $f['bodega']->id,
            'destination_location_id' => $f['bodega']->id,
            'shipment_date' => '2026-09-01',
            'status' => 'pending',
            'total_expected' => 0,
            'total_received' => 0,
            'completion_percentage' => 0,
            'responsible_user' => $f['admin']->id,
        ]);
    }

    private function fixtures(): array
    {
        return [
            'admin' => User::create([
                'name' => 'Admin Consecutivo',
                'email' => 'admin_cons_' . uniqid() . '@agriflor.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'status' => 'active',
            ]),
            'bodega' => Location::create([
                'name' => 'Bodega Consecutivo',
                'type' => 'warehouse',
                'status' => 'active',
            ]),
        ];
    }
}
