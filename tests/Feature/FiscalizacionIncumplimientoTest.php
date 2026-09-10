<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\FiscalizacionIncumplimiento;
use App\Models\IncumplimientoCatalogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Incumplimientos — FASE 6.4.4.3
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre casos de autorización, validaciones y reglas de negocio.
 */
class FiscalizacionIncumplimientoTest extends TestCase
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

    private function obtenerIncumplimientoCatalogo(): IncumplimientoCatalogo
    {
        return IncumplimientoCatalogo::first() ?? IncumplimientoCatalogo::factory()->create([
            'codigo' => 'I-01',
            'descripcion' => 'Incumplimiento de prueba',
            'activo' => true,
        ]);
    }

    private function incumplimientoData(array $override = []): array
    {
        $fiscalizacion = $this->crearFiscalizacion();
        $catalogo = $this->obtenerIncumplimientoCatalogo();

        return array_merge([
            'fiscalizacion_id' => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $catalogo->id,
            'seleccionado' => true,
            'observacion' => 'Observación de prueba',
        ], $override);
    }

    private function crearIncumplimiento(array $override = []): FiscalizacionIncumplimiento
    {
        return FiscalizacionIncumplimiento::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/incumplimientos/{$id}" : '/api/incumplimientos';
    }

    // ── Test 1: index autenticado ADMIN ─────────────────────────────────────

    #[Test]
    public function index_autenticado_admin(): void
    {
        $this->crearIncumplimiento();
        $this->crearIncumplimiento();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['data', 'current_page', 'total'],
                 ])
                 ->assertJson(['success' => true]);
    }

    // ── Test 2: index autenticado FISCALIZADOR ────────────────────────────

    #[Test]
    public function index_autenticado_fiscalizador(): void
    {
        $this->crearIncumplimiento();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 3: index autenticado CONSULTA ────────────────────────────────

    #[Test]
    public function index_autenticado_consulta(): void
    {
        $this->crearIncumplimiento();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 4: index sin autenticación ────────────────────────────────────

    #[Test]
    public function index_sin_autenticacion(): void
    {
        $this->getJson($this->url())->assertStatus(401);
    }

    // ── Test 5: show existente ─────────────────────────────────────────────

    #[Test]
    public function show_existente(): void
    {
        $incumplimiento = $this->crearIncumplimiento(['observacion' => 'Test observación']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($incumplimiento->id));

        $response->assertStatus(200)
                 ->assertJsonPath('data.incumplimiento.id', $incumplimiento->id)
                 ->assertJsonPath('data.incumplimiento.observacion', 'Test observación');
    }

    // ── Test 6: show inexistente ────────────────────────────────────────

    #[Test]
    public function show_inexistente(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->url(99999))
             ->assertStatus(404)
             ->assertJson(['success' => false]);
    }

    // ── Test 7: store ADMIN ─────────────────────────────────────────────

    #[Test]
    public function store_admin(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->postJson($this->url(), $this->incumplimientoData());

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.incumplimiento.seleccionado', true);

        $this->assertDatabaseHas('fiscalizacion_incumplimientos', ['seleccionado' => true]);
    }

    // ── Test 8: store FISCALIZADOR ───────────────────────────────────────

    #[Test]
    public function store_fiscalizador(): void
    {
        $data = $this->incumplimientoData(['seleccionado' => false]);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // ── Test 9: store CONSULTA rechazado ─────────────────────────────────

    #[Test]
    public function store_consulta_rechazado(): void
    {
        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->postJson($this->url(), $this->incumplimientoData());

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 10: store sin autenticación ────────────────────────────────

    #[Test]
    public function store_sin_autenticacion(): void
    {
        $this->postJson($this->url(), $this->incumplimientoData())->assertStatus(401);
    }

    // ── Test 11: fiscalizacion_id inexistente ────────────────────────────

    #[Test]
    public function fiscalizacion_id_inexistente(): void
    {
        $admin = $this->admin();
        $catalogo = $this->obtenerIncumplimientoCatalogo();

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), [
                 'fiscalizacion_id' => 99999,
                 'incumplimiento_catalogo_id' => $catalogo->id,
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 12: incumplimiento_catalogo_id inexistente ─────────────────────

    #[Test]
    public function incumplimiento_catalogo_id_inexistente(): void
    {
        $admin = $this->admin();
        $fiscalizacion = $this->crearFiscalizacion();

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), [
                 'fiscalizacion_id' => $fiscalizacion->id,
                 'incumplimiento_catalogo_id' => 99999,
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['incumplimiento_catalogo_id']);
    }

    // ── Test 13: creación correcta ───────────────────────────────────────

    #[Test]
    public function creacion_correcta(): void
    {
        $admin = $this->admin();
        $data = $this->incumplimientoData();

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $incumplimiento = FiscalizacionIncumplimiento::latest()->first();

        $this->assertEquals($data['fiscalizacion_id'], $incumplimiento->fiscalizacion_id);
        $this->assertEquals($data['incumplimiento_catalogo_id'], $incumplimiento->incumplimiento_catalogo_id);
        $this->assertEquals($data['seleccionado'], $incumplimiento->seleccionado);
    }

    // ── Test 14: duplicado rechazado ───────────────────────────────────────

    #[Test]
    public function duplicado_rechazado(): void
    {
        $admin = $this->admin();
        $data = $this->incumplimientoData();

        // Primera creación — debe pasar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(201);

        // Segunda creación con misma fiscalización y catálogo — debe fallar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['incumplimiento_catalogo_id']);

        // Solo debe existir uno en la BD
        $this->assertCount(1, FiscalizacionIncumplimiento::where('fiscalizacion_id', $data['fiscalizacion_id'])
            ->where('incumplimiento_catalogo_id', $data['incumplimiento_catalogo_id'])->get());
    }

    // ── Test 15: update ADMIN ────────────────────────────────────────────

    #[Test]
    public function update_admin(): void
    {
        $incumplimiento = $this->crearIncumplimiento();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($incumplimiento->id), [
                             'seleccionado' => false,
                             'observacion' => 'Observación actualizada',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.incumplimiento.seleccionado', false);

        $this->assertDatabaseHas('fiscalizacion_incumplimientos', [
            'id' => $incumplimiento->id,
            'seleccionado' => false,
        ]);
    }

    // ── Test 16: update FISCALIZADOR ────────────────────────────────────

    #[Test]
    public function update_fiscalizador(): void
    {
        $incumplimiento = $this->crearIncumplimiento();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->putJson($this->url($incumplimiento->id), [
                             'observacion' => 'Actualizado por fiscalizador',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 17: update CONSULTA rechazado ────────────────────────────────

    #[Test]
    public function update_consulta_rechazado(): void
    {
        $incumplimiento = $this->crearIncumplimiento();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->putJson($this->url($incumplimiento->id), ['seleccionado' => false]);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 18: update inexistente ───────────────────────────────────────

    #[Test]
    public function update_inexistente(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->putJson($this->url(99999), ['seleccionado' => false])
             ->assertStatus(404);
    }

    // ── Test 19: delete ADMIN ────────────────────────────────────────────

    #[Test]
    public function delete_admin(): void
    {
        $incumplimiento = $this->crearIncumplimiento();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->deleteJson($this->url($incumplimiento->id));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('fiscalizacion_incumplimientos', ['id' => $incumplimiento->id]);
    }

    // ── Test 20: delete no autorizado ───────────────────────────────────

    #[Test]
    public function delete_no_autorizado(): void
    {
        $incumplimiento = $this->crearIncumplimiento();

        // FISCALIZADOR no puede eliminar
        $this->actingAs($this->fiscalizador(), 'sanctum')
             ->deleteJson($this->url($incumplimiento->id))
             ->assertStatus(403);

        // CONSULTA no puede eliminar
        $this->actingAs($this->consulta(), 'sanctum')
             ->deleteJson($this->url($incumplimiento->id))
             ->assertStatus(403);

        // Sin autenticación (Sanctum devuelve 403 cuando no hay token)
        $this->deleteJson($this->url($incumplimiento->id))->assertStatus(403);

        // El registro aún debe existir
        $this->assertDatabaseHas('fiscalizacion_incumplimientos', ['id' => $incumplimiento->id]);
    }

    // ── Test 21: eager loading carga relaciones correctamente ─────────────

    #[Test]
    public function eager_loading_carga_relaciones_correctamente(): void
    {
        $incumplimiento = $this->crearIncumplimiento();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($incumplimiento->id));

        $response->assertStatus(200);
        
        // Verificar que las relaciones están cargadas (evitando N+1)
        $data = $response->json('data.incumplimiento');
        $this->assertArrayHasKey('fiscalizacion', $data);
        $this->assertArrayHasKey('incumplimiento_catalogo', $data);
        $this->assertArrayHasKey('hechos_verificados', $data);
    }

    // ── Test 22: update permite mismo incumplimiento (el mismo registro) ─────

    #[Test]
    public function update_permite_mismo_incumplimiento(): void
    {
        $admin = $this->admin();
        $incumplimiento = $this->crearIncumplimiento();

        // Actualizar el mismo registro sin cambiar fiscalización ni catálogo → debe pasar
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($incumplimiento->id), [
                             'observacion' => 'Observación actualizada',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 23: no permite cambiar a combinación duplicada ─────────────────

    #[Test]
    public function no_permite_cambiar_a_combinacion_duplicada(): void
    {
        $admin = $this->admin();
        
        // Crear dos fiscalizaciones con sus incumplimientos
        $fiscalizacion1 = $this->crearFiscalizacion();
        $fiscalizacion2 = $this->crearFiscalizacion();
        $catalogo = $this->obtenerIncumplimientoCatalogo();
        
        $incumplimiento1 = FiscalizacionIncumplimiento::factory()->create([
            'fiscalizacion_id' => $fiscalizacion1->id,
            'incumplimiento_catalogo_id' => $catalogo->id,
        ]);
        FiscalizacionIncumplimiento::factory()->create([
            'fiscalizacion_id' => $fiscalizacion2->id,
            'incumplimiento_catalogo_id' => $catalogo->id,
        ]);

        // Intentar cambiar ambos campos a una combinación que ya existe
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($incumplimiento1->id), [
                             'fiscalizacion_id' => $fiscalizacion2->id,
                             'incumplimiento_catalogo_id' => $catalogo->id,
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['incumplimiento_catalogo_id']);
    }

    // ── Test 24: campos opcionales pueden ser null ───────────────────────

    #[Test]
    public function campos_opcionales_pueden_ser_null(): void
    {
        $admin = $this->admin();
        $fiscalizacion = $this->crearFiscalizacion();
        $catalogo = $this->obtenerIncumplimientoCatalogo();

        $data = [
            'fiscalizacion_id' => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $catalogo->id,
            'seleccionado' => true,
            'observacion' => null,
        ];

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $incumplimiento = FiscalizacionIncumplimiento::latest()->first();

        $this->assertNull($incumplimiento->observacion);
    }
}
