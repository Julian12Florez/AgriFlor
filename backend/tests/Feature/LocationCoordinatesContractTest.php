<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El contrato de las coordenadas: ENTRAN planas, SALEN anidadas.
 *
 * Es un contrato asimétrico, y por no verlo se perdieron todas las coordenadas
 * del sistema. El formulario de la finca mandaba el objeto anidado que recibía —
 * `coordinates: {lat, lng}`— mientras que StoreLocationRequest y
 * UpdateLocationRequest validan `coordinates_lat` y `coordinates_lng` por
 * separado. Como el nombre no coincidía, la clave nunca entraba en `validated()`
 * y la latitud que el usuario escribía se descartaba sin un solo aviso: el
 * guardado respondía 200 y el dato no llegaba a ninguna parte.
 *
 * Medido en producción el 28-sep-2026: 21 ubicaciones, 0 con latitud, 0 con
 * longitud. Ninguna, nunca.
 *
 * Esta prueba fija las dos mitades del contrato para que quien escriba el
 * siguiente formulario no tenga que adivinarlo.
 */
class LocationCoordinatesContractTest extends TestCase
{
    use RefreshDatabase;

    /** Entran planas. */
    public function test_las_coordenadas_se_guardan_mandandolas_planas(): void
    {
        $admin = $this->admin();
        $finca = $this->finca();

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, [
                'coordinates_lat' => 6.3145,
                'coordinates_lng' => -76.1328,
            ])
            ->assertOk();

        $guardada = $finca->fresh();
        $this->assertEqualsWithDelta(6.3145, (float) $guardada->coordinates_lat, 0.0001);
        $this->assertEqualsWithDelta(-76.1328, (float) $guardada->coordinates_lng, 0.0001);
    }

    /** Salen anidadas: es la forma que el formulario lee al abrir la ficha. */
    public function test_las_coordenadas_vuelven_anidadas(): void
    {
        $admin = $this->admin();
        $finca = $this->finca();
        $finca->update(['coordinates_lat' => 6.3145, 'coordinates_lng' => -76.1328]);

        $datos = $this->actingAs($admin, 'api')
            ->getJson('/api/locations/' . $finca->id)
            ->assertOk()
            ->json('data');

        $this->assertEqualsWithDelta(6.3145, (float) $datos['coordinates']['lat'], 0.0001);
        $this->assertEqualsWithDelta(-76.1328, (float) $datos['coordinates']['lng'], 0.0001);
    }

    /**
     * Y vuelven como NÚMERO, no como texto. La columna está casteada a `decimal:8`
     * y el cast decimal de Laravel serializa string ("6.31450000"); la tabla de
     * Ubicaciones solo pinta la coordenada si `typeof lat === 'number'`
     * (Locations.tsx:347), así que con el string la pantalla decía "No definidas"
     * aunque el dato estuviera guardado — guardar bien y no verlo es el mismo
     * defecto que traía el número de trabajadores.
     */
    public function test_las_coordenadas_vuelven_como_numero_no_como_texto(): void
    {
        $admin = $this->admin();
        $finca = $this->finca();
        $finca->update(['coordinates_lat' => 6.3145, 'coordinates_lng' => -76.1328]);

        $datos = $this->actingAs($admin, 'api')
            ->getJson('/api/locations/' . $finca->id)
            ->json('data');

        $this->assertIsFloat($datos['coordinates']['lat'], 'La pantalla descarta la coordenada si llega como texto.');
        $this->assertIsFloat($datos['coordinates']['lng']);
    }

    /** Sin coordenadas, la clave llega en null y no en cero: cero es un lugar real. */
    public function test_sin_coordenadas_vuelve_null_y_no_cero(): void
    {
        $admin = $this->admin();
        $finca = $this->finca();

        $datos = $this->actingAs($admin, 'api')
            ->getJson('/api/locations/' . $finca->id)
            ->json('data');

        $this->assertNull($datos['coordinates']['lat']);
        $this->assertNull($datos['coordinates']['lng']);
    }

    /** Guardar otro campo no puede borrar las coordenadas: la misma clase de bug. */
    public function test_guardar_otro_campo_no_borra_las_coordenadas(): void
    {
        $admin = $this->admin();
        $finca = $this->finca();
        $finca->update(['coordinates_lat' => 6.3145, 'coordinates_lng' => -76.1328]);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, ['address' => 'vereda La Clara'])
            ->assertOk();

        $this->assertEqualsWithDelta(6.3145, (float) $finca->fresh()->coordinates_lat, 0.0001);
    }

    /**
     * Y el objeto anidado NO se acepta al escribir. No es un capricho: si algún
     * día se aceptara, esta prueba avisa de que el contrato cambió y que el
     * formulario puede volver a mandarlo por el camino viejo sin enterarse.
     */
    public function test_mandar_el_objeto_anidado_no_guarda_nada(): void
    {
        $admin = $this->admin();
        $finca = $this->finca();

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, [
                'coordinates' => ['lat' => 6.3145, 'lng' => -76.1328],
            ])
            ->assertOk();

        $this->assertNull(
            $finca->fresh()->coordinates_lat,
            'Es exactamente lo que hacía el formulario: respondía 200 y no guardaba nada.'
        );
    }

    /** El cero es una latitud válida y no puede tratarse como "vacío". */
    public function test_el_cero_es_una_coordenada_valida(): void
    {
        $admin = $this->admin();
        $finca = $this->finca();

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, [
                'coordinates_lat' => 0,
                'coordinates_lng' => 0,
            ])
            ->assertOk();

        $this->assertEqualsWithDelta(0, (float) $finca->fresh()->coordinates_lat, 0.0001);
    }

    // ------------------------------------------------------------------

    private function finca(): Location
    {
        return Location::create([
            'name' => 'Finca Coordenadas',
            'type' => 'farm',
            'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Administrador',
            'email' => 'coord_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
