<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\Observacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Observaciones — FASE 6.4.4.5
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre casos de autorización, validaciones y reglas de negocio.
 * Relación 1-a-1 con fiscalización.
 */
class ObservacionTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'ADMIN', 'activo' => true]);
    }

    private function fiscalizador(): User
    {
        return User::factory()->create(['rol' => 'FISCALIZADOR', 'activo' => true]);
    }

    private function consulta(): User
    {
        return User::factory()->create(['rol' => 'CONSULTA', 'activo' => true]);
    }

    private function crearFiscalizacion(): Fiscalizacion
    {
        $establecimiento = Establecimiento::factory()->create([
            'razon_social' => 'Grifo Test S.A.C.',
            'codigo_osinergmin' => 'OSI-TEST-01',
            'direccion' => 'Av. Principal 123',
            'ruc_dni' => '20100001234',
        ]);

        return Fiscalizacion::factory()->create([
            'establecimiento_id' => $establecimiento->id,
            'numero_expediente' => 'EXP-2024-0001',
            'fecha_diligencia' => '2024-01-15',
            'hora_apertura' => '09:00',
            'estado' => 'BORRADOR',
        ]);
    }

    private function observacionData(array $override = []): array
    {
        $fiscalizacion = $this->crearFiscalizacion();

        return array_merge([
            'fiscalizacion_id' => $fiscalizacion->id,
            'otras_ocurrencias' => 'Otras ocurrencias registradas',
            'documentacion_recabada' => 'Documentación recabada durante la fiscalización',
            'manifestaciones_agente' => 'Manifestaciones del agente',
            'negativa_identificacion' => false,
            'negativa_suscripcion' => false,
            'negativa_recepcion' => false,
            'observaciones_generales' => 'Observaciones generales del acta',
        ], $override);
    }

    private function crearObservacion(array $override = []): Observacion
    {
        return Observacion::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/observaciones/{$id}" : '/api/observaciones';
    }

    // ── Test 1: GET index autenticado ─────────────────────────────────────

    #[Test]
    public function get_index_autenticado(): void
    {
        $this->crearObservacion();
        $this->crearObservacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['data', 'current_page', 'total'],
                 ])
                 ->assertJson(['success' => true]);
    }

    // ── Test 2: GET index sin autenticación ─────────────────────────────

    #[Test]
    public function get_index_sin_autenticacion(): void
    {
        $this->getJson($this->url())->assertStatus(401);
    }

    // ── Test 3: GET show autenticado ─────────────────────────────────────

    #[Test]
    public function get_show_autenticado(): void
    {
        $observacion = $this->crearObservacion(['observaciones_generales' => 'Test observación']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($observacion->id));

        $response->assertStatus(200)
                 ->assertJsonPath('data.observacion.id', $observacion->id)
                 ->assertJsonPath('data.observacion.observaciones_generales', 'Test observación');
    }

    // ── Test 4: GET show inexistente ───────────────────────────────────

    #[Test]
    public function get_show_inexistente(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->url(99999))
             ->assertStatus(404)
             ->assertJson(['success' => false]);
    }

    // ── Test 5: POST válido como ADMIN ─────────────────────────────────

    #[Test]
    public function post_valido_como_admin(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->postJson($this->url(), $this->observacionData());

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.observacion.negativa_identificacion', false);

        $this->assertDatabaseHas('observaciones', ['negativa_identificacion' => false]);
    }

    // ── Test 6: POST válido como FISCALIZADOR ───────────────────────────

    #[Test]
    public function post_valido_como_fiscalizador(): void
    {
        $data = $this->observacionData(['negativa_identificacion' => true]);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // ── Test 7: POST rechazado como CONSULTA ───────────────────────────

    #[Test]
    public function post_rechazado_como_consulta(): void
    {
        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->postJson($this->url(), $this->observacionData());

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 8: POST sin autenticación ────────────────────────────────

    #[Test]
    public function post_sin_autenticacion(): void
    {
        $this->postJson($this->url(), $this->observacionData())->assertStatus(401);
    }

    // ── Test 9: validación de campos obligatorios ───────────────────────

    #[Test]
    public function validacion_campos_obligatorios(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 10: validación de claves foráneas ─────────────────────────

    #[Test]
    public function validacion_claves_foraneas(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), ['fiscalizacion_id' => 99999])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 11: no permite duplicar observación en misma fiscalización ─────

    #[Test]
    public function no_permite_duplicar_observacion_misma_fiscalizacion(): void
    {
        $admin = $this->admin();
        $data = $this->observacionData();

        // Primera creación — debe pasar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(201);

        // Segunda creación con misma fiscalización — debe fallar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fiscalizacion_id']);

        // Solo debe existir uno en la BD
        $this->assertCount(1, Observacion::where('fiscalizacion_id', $data['fiscalizacion_id'])->get());
    }

    // ── Test 12: PUT válido como ADMIN ─────────────────────────────────

    #[Test]
    public function put_valido_como_admin(): void
    {
        $observacion = $this->crearObservacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($observacion->id), [
                             'observaciones_generales' => 'Observación actualizada',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.observacion.observaciones_generales', 'Observación actualizada');

        $this->assertDatabaseHas('observaciones', [
            'id' => $observacion->id,
            'observaciones_generales' => 'Observación actualizada',
        ]);
    }

    // ── Test 13: PUT válido como FISCALIZADOR ─────────────────────────

    #[Test]
    public function put_valido_como_fiscalizador(): void
    {
        $observacion = $this->crearObservacion();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->putJson($this->url($observacion->id), [
                             'otras_ocurrencias' => 'Actualizado por fiscalizador',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 14: PUT rechazado como CONSULTA ─────────────────────────

    #[Test]
    public function put_rechazado_como_consulta(): void
    {
        $observacion = $this->crearObservacion();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->putJson($this->url($observacion->id), ['observaciones_generales' => 'Actualizado']);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 15: PUT sobre registro inexistente ────────────────────────

    #[Test]
    public function put_sobre_registro_inexistente(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->putJson($this->url(99999), ['observaciones_generales' => 'Actualizado'])
             ->assertStatus(404);
    }

    // ── Test 16: DELETE válido como ADMIN ─────────────────────────────

    #[Test]
    public function delete_valido_como_admin(): void
    {
        $observacion = $this->crearObservacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->deleteJson($this->url($observacion->id));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('observaciones', ['id' => $observacion->id]);
    }

    // ── Test 17: DELETE rechazado como FISCALIZADOR ────────────────────

    #[Test]
    public function delete_rechazado_como_fiscalizador(): void
    {
        $observacion = $this->crearObservacion();

        $this->actingAs($this->fiscalizador(), 'sanctum')
             ->deleteJson($this->url($observacion->id))
             ->assertStatus(403);

        $this->assertDatabaseHas('observaciones', ['id' => $observacion->id]);
    }

    // ── Test 18: DELETE rechazado como CONSULTA ────────────────────────

    #[Test]
    public function delete_rechazado_como_consulta(): void
    {
        $observacion = $this->crearObservacion();

        $this->actingAs($this->consulta(), 'sanctum')
             ->deleteJson($this->url($observacion->id))
             ->assertStatus(403);

        $this->assertDatabaseHas('observaciones', ['id' => $observacion->id]);
    }

    // ── Test 19: DELETE sin autenticación ─────────────────────────────

    #[Test]
    public function delete_sin_autenticacion(): void
    {
        $observacion = $this->crearObservacion();

        $this->deleteJson($this->url($observacion->id))->assertStatus(401);
    }

    // ── Test 20: comprobación de persistencia ──────────────────────────

    #[Test]
    public function comprobacion_persistencia(): void
    {
        $admin = $this->admin();
        $data = $this->observacionData();

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $observacion = Observacion::latest()->first();

        $this->assertEquals($data['fiscalizacion_id'], $observacion->fiscalizacion_id);
        $this->assertEquals($data['otras_ocurrencias'], $observacion->otras_ocurrencias);
        $this->assertEquals($data['documentacion_recabada'], $observacion->documentacion_recabada);
    }

    // ── Test 21: eager loading carga relaciones correctamente ─────────

    #[Test]
    public function eager_loading_carga_relaciones_correctamente(): void
    {
        $observacion = $this->crearObservacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($observacion->id));

        $response->assertStatus(200);
        
        $data = $response->json('data.observacion');
        $this->assertArrayHasKey('fiscalizacion', $data);
    }

    // ── Test 22: campos opcionales pueden ser null ─────────────────────

    #[Test]
    public function campos_opcionales_pueden_ser_null(): void
    {
        $admin = $this->admin();
        $fiscalizacion = $this->crearFiscalizacion();

        $data = [
            'fiscalizacion_id' => $fiscalizacion->id,
            'otras_ocurrencias' => null,
            'documentacion_recabada' => null,
            'manifestaciones_agente' => null,
            'observaciones_generales' => null,
        ];

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $observacion = Observacion::latest()->first();

        $this->assertNull($observacion->otras_ocurrencias);
        $this->assertNull($observacion->documentacion_recabada);
    }

    // ── Test 23: update permite misma fiscalización (el mismo registro) ─

    #[Test]
    public function update_permite_misma_fiscalizacion(): void
    {
        $admin = $this->admin();
        $observacion = $this->crearObservacion();

        // Actualizar el mismo registro sin cambiar fiscalización → debe pasar
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($observacion->id), [
                             'observaciones_generales' => 'Observación actualizada',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 24: no permite cambiar fiscalización a una que ya tiene observación ─

    #[Test]
    public function no_permite_cambiar_fiscalizacion_a_una_que_ya_tiene_observacion(): void
    {
        $admin = $this->admin();
        
        // Crear dos fiscalizaciones con sus observaciones
        $fiscalizacion1 = $this->crearFiscalizacion();
        $fiscalizacion2 = $this->crearFiscalizacion();
        
        $observacion1 = Observacion::factory()->create([
            'fiscalizacion_id' => $fiscalizacion1->id,
        ]);
        Observacion::factory()->create([
            'fiscalizacion_id' => $fiscalizacion2->id,
        ]);

        // Intentar cambiar la fiscalización de observacion1 a fiscalizacion2
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($observacion1->id), [
                             'fiscalizacion_id' => $fiscalizacion2->id,
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 25: campos booleanos aceptan valores correctos ─────────────

    #[Test]
    public function campos_booleanos_aceptan_valores_correctos(): void
    {
        $admin = $this->admin();
        $fiscalizacion = $this->crearFiscalizacion();

        $data = [
            'fiscalizacion_id' => $fiscalizacion->id,
            'negativa_identificacion' => true,
            'negativa_suscripcion' => true,
            'negativa_recepcion' => true,
        ];

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $observacion = Observacion::latest()->first();

        $this->assertTrue($observacion->negativa_identificacion);
        $this->assertTrue($observacion->negativa_suscripcion);
        $this->assertTrue($observacion->negativa_recepcion);
    }
}
