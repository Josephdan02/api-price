<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\FiscalizacionIncumplimiento;
use App\Models\HechoVerificado;
use App\Models\IncumplimientoCatalogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Hechos Verificados — FASE 6.4.4.4
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre casos de autorización, validaciones y reglas de negocio.
 */
class HechoVerificadoTest extends TestCase
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

    private function crearIncumplimiento(Fiscalizacion $fiscalizacion): FiscalizacionIncumplimiento
    {
        $catalogo = IncumplimientoCatalogo::first() ?? IncumplimientoCatalogo::factory()->create([
            'codigo' => 'I-01',
            'descripcion' => 'Incumplimiento de prueba',
            'activo' => true,
        ]);

        return FiscalizacionIncumplimiento::factory()->create([
            'fiscalizacion_id' => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $catalogo->id,
        ]);
    }

    private function hechoVerificadoData(array $override = []): array
    {
        $fiscalizacion = $this->crearFiscalizacion();
        $incumplimiento = $this->crearIncumplimiento($fiscalizacion);
        $user = User::factory()->create(['rol' => 'FISCALIZADOR', 'activo' => true]);

        return array_merge([
            'fiscalizacion_id' => $fiscalizacion->id,
            'fiscalizacion_incumplimiento_id' => $incumplimiento->id,
            'user_id' => $user->id,
            'descripcion' => 'Evidencia del incumplimiento',
            'fecha_registro' => '2024-01-15',
        ], $override);
    }

    private function crearHechoVerificado(array $override = []): HechoVerificado
    {
        return HechoVerificado::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/hechos-verificados/{$id}" : '/api/hechos-verificados';
    }

    // ── Test 1: index requiere autenticación ───────────────────────────────

    #[Test]
    public function index_requiere_autenticacion(): void
    {
        $this->getJson($this->url())->assertStatus(401);
    }

    // ── Test 2: show requiere autenticación ───────────────────────────────

    #[Test]
    public function show_requiere_autenticacion(): void
    {
        $hecho = $this->crearHechoVerificado();

        $this->getJson($this->url($hecho->id))->assertStatus(401);
    }

    // ── Test 3: store requiere autenticación ───────────────────────────────

    #[Test]
    public function store_requiere_autenticacion(): void
    {
        $this->postJson($this->url(), $this->hechoVerificadoData())->assertStatus(401);
    }

    // ── Test 4: update requiere autenticación ───────────────────────────────

    #[Test]
    public function update_requiere_autenticacion(): void
    {
        $hecho = $this->crearHechoVerificado();

        $this->putJson($this->url($hecho->id), ['descripcion' => 'Actualizado'])->assertStatus(401);
    }

    // ── Test 5: destroy requiere autenticación ──────────────────────────────

    #[Test]
    public function destroy_requiere_autenticacion(): void
    {
        $hecho = $this->crearHechoVerificado();

        $this->deleteJson($this->url($hecho->id))->assertStatus(401);
    }

    // ── Test 6: ADMIN puede listar ───────────────────────────────────────

    #[Test]
    public function admin_puede_listar(): void
    {
        $this->crearHechoVerificado();
        $this->crearHechoVerificado();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['data', 'current_page', 'total'],
                 ])
                 ->assertJson(['success' => true]);
    }

    // ── Test 7: FISCALIZADOR puede listar ─────────────────────────────────

    #[Test]
    public function fiscalizador_puede_listar(): void
    {
        $this->crearHechoVerificado();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 8: CONSULTA puede listar ──────────────────────────────────────

    #[Test]
    public function consulta_puede_listar(): void
    {
        $this->crearHechoVerificado();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 9: ADMIN puede crear ────────────────────────────────────────

    #[Test]
    public function admin_puede_crear(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->postJson($this->url(), $this->hechoVerificadoData());

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.hecho_verificado.descripcion', 'Evidencia del incumplimiento');

        $this->assertDatabaseHas('hechos_verificados', ['descripcion' => 'Evidencia del incumplimiento']);
    }

    // ── Test 10: FISCALIZADOR puede crear ─────────────────────────────────

    #[Test]
    public function fiscalizador_puede_crear(): void
    {
        $data = $this->hechoVerificadoData(['descripcion' => 'Evidencia registrada por fiscalizador']);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // ── Test 11: CONSULTA no puede crear ───────────────────────────────────

    #[Test]
    public function consulta_no_puede_crear(): void
    {
        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->postJson($this->url(), $this->hechoVerificadoData());

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 12: ADMIN puede actualizar ───────────────────────────────────

    #[Test]
    public function admin_puede_actualizar(): void
    {
        $hecho = $this->crearHechoVerificado();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($hecho->id), [
                             'descripcion' => 'Descripción actualizada',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.hecho_verificado.descripcion', 'Descripción actualizada');

        $this->assertDatabaseHas('hechos_verificados', [
            'id' => $hecho->id,
            'descripcion' => 'Descripción actualizada',
        ]);
    }

    // ── Test 13: FISCALIZADOR puede actualizar ────────────────────────────

    #[Test]
    public function fiscalizador_puede_actualizar(): void
    {
        $hecho = $this->crearHechoVerificado();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->putJson($this->url($hecho->id), [
                             'descripcion' => 'Actualizado por fiscalizador',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 14: CONSULTA no puede actualizar ─────────────────────────────

    #[Test]
    public function consulta_no_puede_actualizar(): void
    {
        $hecho = $this->crearHechoVerificado();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->putJson($this->url($hecho->id), ['descripcion' => 'Actualizado']);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 15: ADMIN puede eliminar ────────────────────────────────────

    #[Test]
    public function admin_puede_eliminar(): void
    {
        $hecho = $this->crearHechoVerificado();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->deleteJson($this->url($hecho->id));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('hechos_verificados', ['id' => $hecho->id]);
    }

    // ── Test 16: FISCALIZADOR no puede eliminar ───────────────────────────

    #[Test]
    public function fiscalizador_no_puede_eliminar(): void
    {
        $hecho = $this->crearHechoVerificado();

        $this->actingAs($this->fiscalizador(), 'sanctum')
             ->deleteJson($this->url($hecho->id))
             ->assertStatus(403);

        $this->assertDatabaseHas('hechos_verificados', ['id' => $hecho->id]);
    }

    // ── Test 17: CONSULTA no puede eliminar ────────────────────────────────

    #[Test]
    public function consulta_no_puede_eliminar(): void
    {
        $hecho = $this->crearHechoVerificado();

        $this->actingAs($this->consulta(), 'sanctum')
             ->deleteJson($this->url($hecho->id))
             ->assertStatus(403);

        $this->assertDatabaseHas('hechos_verificados', ['id' => $hecho->id]);
    }

    // ── Test 18: fiscalizacion_id inválido es rechazado ───────────────────

    #[Test]
    public function fiscalizacion_id_invalido_rechazado(): void
    {
        $admin = $this->admin();
        $data = $this->hechoVerificadoData(['fiscalizacion_id' => 99999]);

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 19: fiscalizacion_incumplimiento_id inválido es rechazado ────

    #[Test]
    public function fiscalizacion_incumplimiento_id_invalido_rechazado(): void
    {
        $admin = $this->admin();
        $data = $this->hechoVerificadoData(['fiscalizacion_incumplimiento_id' => 99999]);

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fiscalizacion_incumplimiento_id']);
    }

    // ── Test 20: datos obligatorios inválidos son rechazados ───────────────

    #[Test]
    public function datos_obligatorios_invalidos_rechazados(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), [])
             ->assertStatus(422)
             ->assertJsonValidationErrors([
                 'fiscalizacion_id',
                 'fiscalizacion_incumplimiento_id',
                 'descripcion',
             ]);
    }

    // ── Test 21: registro inexistente devuelve 404 ────────────────────────

    #[Test]
    public function registro_inexistente_devuelve_404(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->url(99999))
             ->assertStatus(404)
             ->assertJson(['success' => false]);

        $this->actingAs($admin, 'sanctum')
             ->putJson($this->url(99999), ['descripcion' => 'Actualizado'])
             ->assertStatus(404);

        $this->actingAs($admin, 'sanctum')
             ->deleteJson($this->url(99999))
             ->assertStatus(404);
    }

    // ── Test 22: eager loading carga relaciones correctamente ─────────────

    #[Test]
    public function eager_loading_carga_relaciones_correctamente(): void
    {
        $hecho = $this->crearHechoVerificado();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($hecho->id));

        $response->assertStatus(200);
        
        $data = $response->json('data.hecho_verificado');
        $this->assertArrayHasKey('fiscalizacion', $data);
        $this->assertArrayHasKey('fiscalizacion_incumplimiento', $data);
        $this->assertArrayHasKey('user', $data);
    }

    // ── Test 23: campos opcionales pueden ser null ───────────────────────

    #[Test]
    public function campos_opcionales_pueden_ser_null(): void
    {
        $admin = $this->admin();
        $data = $this->hechoVerificadoData([
            'user_id' => null,
            'fecha_registro' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $hecho = HechoVerificado::latest()->first();

        $this->assertNull($hecho->user_id);
        $this->assertNull($hecho->fecha_registro);
    }

    // ── Test 24: fecha_registro acepta formato válido ────────────────────

    #[Test]
    public function fecha_registro_acepta_formato_valido(): void
    {
        $admin = $this->admin();
        $data = $this->hechoVerificadoData(['fecha_registro' => '2024-12-31']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $hecho = HechoVerificado::latest()->first();

        $this->assertEquals('2024-12-31', $hecho->fecha_registro->format('Y-m-d'));
    }

    // ── Test 25: fecha_registro invalida es rechazada ────────────────────

    #[Test]
    public function fecha_registro_invalida_rechazada(): void
    {
        $admin = $this->admin();
        $data = $this->hechoVerificadoData(['fecha_registro' => 'fecha-invalida']);

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fecha_registro']);
    }

    // ── Test 26: update permite campos opcionales ────────────────────────

    #[Test]
    public function update_permite_campos_opcionales(): void
    {
        $admin = $this->admin();
        $hecho = $this->crearHechoVerificado();

        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($hecho->id), [
                             'descripcion' => 'Solo actualizo descripción',
                         ]);

        $response->assertStatus(200);
    }

    // ── Test 27: user_id invalido es rechazado ───────────────────────────

    #[Test]
    public function user_id_invalido_rechazado(): void
    {
        $admin = $this->admin();
        $data = $this->hechoVerificadoData(['user_id' => 99999]);

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['user_id']);
    }
}
