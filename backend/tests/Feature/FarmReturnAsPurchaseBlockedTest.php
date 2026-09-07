<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cuando una finca devuelve producto sobrante a la bodega, el canal correcto es
 * Salidas → Remanente (origen = la finca): descuenta la finca y acredita la
 * bodega.
 *
 * Durante 15 meses esa devolución se registró como una COMPRA a un proveedor
 * inventado, "REMANENTES FINCA": 47 documentos y 178.838,49 unidades, contra
 * 4.271,91 por el canal legítimo. Esa compra acredita la bodega y NUNCA debita
 * la finca.
 *
 * Mientras la finca no tuvo existencias (21-ago → sep-2026) eso solo era una
 * etiqueta equivocada. Desde que la finca volvió a custodiar lo que recibe, el
 * mismo producto queda contado DOS VECES: en la bodega por la compra y en la
 * finca porque nadie lo descontó.
 *
 * Estas pruebas fijan el cierre de ese canal. Si alguien lo reabre, el doble
 * conteo vuelve en silencio y no hay ninguna otra señal que lo delate.
 */
class FarmReturnAsPurchaseBlockedTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 1. La regla, sin montar una petición
    // ------------------------------------------------------------------

    public function test_reconoce_al_proveedor_que_es_en_realidad_una_devolucion(): void
    {
        $this->assertTrue(Supplier::esDevolucionDeFinca('REMANENTES FINCA'));

        // Tolerante a como lo escriban: el nombre lo teclea el usuario.
        $this->assertTrue(Supplier::esDevolucionDeFinca('remanentes finca'));
        $this->assertTrue(Supplier::esDevolucionDeFinca('Remanente Finca La Mansión'));
        $this->assertTrue(Supplier::esDevolucionDeFinca('DEVOLUCIONES / REMANENTES'));
    }

    public function test_no_estorba_a_un_proveedor_de_verdad(): void
    {
        foreach ([
            'Agroinsumos Villa Clara S.A.S',
            'YARA COLOMBIA',
            'ADAMA',
            'Nutrición de Plantas S.A.',
        ] as $nombre) {
            $this->assertFalse(
                Supplier::esDevolucionDeFinca($nombre),
                "'{$nombre}' es un proveedor real: no debe bloquearse su compra."
            );
        }

        // Proveedor borrado o sin nombre: ante la duda NO se bloquea.
        $this->assertFalse(Supplier::esDevolucionDeFinca(null));
        $this->assertFalse(Supplier::esDevolucionDeFinca(''));
    }

    // ------------------------------------------------------------------
    // 2. El bloqueo en el endpoint
    // ------------------------------------------------------------------

    public function test_rechaza_la_compra_al_proveedor_de_devoluciones(): void
    {
        $admin = User::create([
            'name' => 'Admin Devoluciones',
            'email' => 'admin_dev_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $proveedor = Supplier::create([
            'name' => 'REMANENTES FINCA',
            'nit' => 'NA-' . uniqid(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'api')->postJson('/api/purchases', [
            'order_number' => 'PUR-TEST-000001',
            'supplier_id' => $proveedor->id,
            'purchase_date' => '2026-09-07',
            'items' => [],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('supplier_id');

        $mensaje = $response->json('errors.supplier_id.0') ?? '';

        // El mensaje tiene que decir A DÓNDE ir, no solo que no se puede: es lo
        // único que evita que el usuario busque otro rodeo.
        $this->assertStringContainsString('Remanente', $mensaje);
        $this->assertStringContainsString('finca', mb_strtolower($mensaje, 'UTF-8'));
    }

    public function test_deja_pasar_la_compra_a_un_proveedor_real(): void
    {
        $admin = User::create([
            'name' => 'Admin Devoluciones',
            'email' => 'admin_dev_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $proveedor = Supplier::create([
            'name' => 'Agroinsumos Villa Clara S.A.S',
            'nit' => '900123456-' . uniqid(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'api')->postJson('/api/purchases', [
            'order_number' => 'PUR-TEST-000002',
            'supplier_id' => $proveedor->id,
            'purchase_date' => '2026-09-07',
            'items' => [],
        ]);

        // Puede fallar por otras reglas (items vacíos, empresa emisora...), pero
        // NUNCA por el proveedor: eso es lo que fija esta prueba.
        $errores = $response->json('errors') ?? [];
        $this->assertArrayNotHasKey(
            'supplier_id',
            $errores,
            'Un proveedor real no debe quedar bloqueado por la regla de devoluciones.'
        );
    }
}
