<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Fiscalizaciones — FASE 6.4.3
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre los 20 casos requeridos.
 */
class FiscalizacionTest extends TestCase
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

    private function crearEstablecimiento(): Establecimiento
    {
        return Establecimiento::factory()->create([
            'razon_social' => 'Grifo Test S.A.C.',
            'codigo_osinergmin' => 'OSI-TEST-01',
            'direccion' => 'Av. Principal 123',
            'distrito' => 'Lima',
            'provincia' => 'Lima',
            'departamento' => 'Lima',
            'ruc_dni' => '20100001234',
            'telefono' => '01-2345678',
        ]);
    }

    private function fiscalizacionData(array $override = []): array
    {
        $establecimiento = $this->crearEstablecimiento();

        return array_merge([
            'establecimiento_id' => $establecimiento->id,
            'numero_expediente' => 'EXP-2024-0001',
            'fecha_diligencia' => '2024-01-15',
            'hora_apertura' => '09:00',
            'hora_cierre' => '17:00',
            'estado' => 'BORRADOR',
        ], $override);
    }

    private function crearFiscalizacion(array $override = []): Fiscalizacion
    {
        return Fiscalizacion::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/fiscalizaciones/{$id}" : '/api/fiscalizaciones';
    }

    // ── Test 1: ADMIN puede listar fiscalizaciones ───────────────────────────

    #[Test]
    public function admin_puede_listar_fiscalizaciones(): void
    {
        $this->crearFiscalizacion();
        $this->crearFiscalizacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['data', 'current_page', 'total'],
                 ])
                 ->assertJson(['success' => true]);
    }

    // ── Test 2: FISCALIZADOR puede listar fiscalizaciones ────────────────────

    #[Test]
    public function fiscalizador_puede_listar_fiscalizaciones(): void
    {
        $this->crearFiscalizacion();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 3: CONSULTA puede listar fiscalizaciones ────────────────────────

    #[Test]
    public function consulta_puede_listar_fiscalizaciones(): void
    {
        $this->crearFiscalizacion();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 4: usuario no autenticado recibe 401 ────────────────────────────

    #[Test]
    public function usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson($this->url())->assertStatus(401);
        $this->postJson($this->url(), $this->fiscalizacionData())->assertStatus(401);

        $fisc = $this->crearFiscalizacion();
        $this->getJson($this->url($fisc->id))->assertStatus(401);
        $this->putJson($this->url($fisc->id), [])->assertStatus(401);
    }

    // ── Test 5: puede consultar fiscalización existente ─────────────────────

    #[Test]
    public function puede_consultar_fiscalizacion_existente(): void
    {
        $fisc = $this->crearFiscalizacion(['numero_expediente' => 'EXP-CONSULTA-01']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($fisc->id));

        $response->assertStatus(200)
                 ->assertJsonPath('data.fiscalizacion.id', $fisc->id)
                 ->assertJsonPath('data.fiscalizacion.numero_expediente', 'EXP-CONSULTA-01');
    }

    // ── Test 6: devuelve 404 para fiscalización inexistente ─────────────────

    #[Test]
    public function devuelve_404_para_fiscalizacion_inexistente(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->url(99999))
             ->assertStatus(404)
             ->assertJson(['success' => false]);

        $this->actingAs($admin, 'sanctum')
             ->putJson($this->url(99999), ['estado' => 'EN_PROCESO'])
             ->assertStatus(404);
    }

    // ── Test 7: ADMIN puede crear ─────────────────────────────────────────────

    #[Test]
    public function admin_puede_crear_fiscalizacion(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->postJson($this->url(), $this->fiscalizacionData());

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.fiscalizacion.numero_expediente', 'EXP-2024-0001');

        $this->assertDatabaseHas('fiscalizaciones', ['numero_expediente' => 'EXP-2024-0001']);
    }

    // ── Test 8: FISCALIZADOR puede crear ─────────────────────────────────────

    #[Test]
    public function fiscalizador_puede_crear_fiscalizacion(): void
    {
        $data = $this->fiscalizacionData([
            'numero_expediente' => 'EXP-FISC-01',
            'user_id' => $this->fiscalizador()->id,
        ]);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // ── Test 9: CONSULTA no puede crear ──────────────────────────────────────

    #[Test]
    public function consulta_no_puede_crear_fiscalizacion(): void
    {
        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->postJson($this->url(), $this->fiscalizacionData());

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 10: POST sin autenticación ───────────────────────────────────────

    #[Test]
    public function post_sin_autenticacion_devuelve_401(): void
    {
        $this->postJson($this->url(), $this->fiscalizacionData())->assertStatus(401);
    }

    // ── Test 11: POST con establecimiento inexistente ────────────────────────

    #[Test]
    public function post_con_establecimiento_inexistente_falla(): void
    {
        $admin = $this->admin();
        $data = $this->fiscalizacionData(['establecimiento_id' => 99999]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['establecimiento_id']);
    }

    // ── Test 12: POST con datos inválidos ────────────────────────────────────

    #[Test]
    public function post_con_datos_invalidos_falla(): void
    {
        $admin = $this->admin();

        // Falta establecimiento_id
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['establecimiento_id', 'fecha_diligencia', 'hora_apertura']);

        // Fecha inválida
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($this->fiscalizacionData(), [
                 'fecha_diligencia' => 'no-es-una-fecha',
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fecha_diligencia']);

        // Hora de apertura inválida
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($this->fiscalizacionData(), [
                 'hora_apertura' => '25:00',
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['hora_apertura']);
    }

    // ── Test 13: ADMIN puede actualizar ───────────────────────────────────────

    #[Test]
    public function admin_puede_actualizar_fiscalizacion(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($fisc->id), [
                             'estado' => 'EN_PROCESO',
                             'numero_expediente' => 'EXP-ACTUALIZADO',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.fiscalizacion.estado', 'EN_PROCESO');

        $this->assertDatabaseHas('fiscalizaciones', [
            'id' => $fisc->id,
            'estado' => 'EN_PROCESO',
        ]);
    }

    // ── Test 14: FISCALIZADOR puede actualizar ────────────────────────────────

    #[Test]
    public function fiscalizador_puede_actualizar_fiscalizacion(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->putJson($this->url($fisc->id), [
                             'estado' => 'EN_PROCESO',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 15: CONSULTA no puede actualizar ────────────────────────────────

    #[Test]
    public function consulta_no_puede_actualizar_fiscalizacion(): void
    {
        $fisc = $this->crearFiscalizacion();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->putJson($this->url($fisc->id), ['estado' => 'EN_PROCESO']);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 16: PUT sin autenticación ────────────────────────────────────────

    #[Test]
    public function put_sin_autenticacion_devuelve_401(): void
    {
        $fisc = $this->crearFiscalizacion();
        $this->putJson($this->url($fisc->id), [])->assertStatus(401);
    }

    // ── Test 17: validación de hora de cierre anterior a apertura ────────────

    #[Test]
    public function no_permite_hora_cierre_anterior_a_apertura(): void
    {
        $admin = $this->admin();
        $data = $this->fiscalizacionData([
            'hora_apertura' => '17:00',
            'hora_cierre' => '09:00',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['hora_cierre']);
    }

    // ── Test 18: validación de fiscalizador requerido al finalizar ────────────

    #[Test]
    public function no_permite_finalizar_sin_fiscalizador(): void
    {
        $admin = $this->admin();
        $data = $this->fiscalizacionData([
            'estado' => 'FINALIZADA',
            'user_id' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['estado']);
    }

    // ── Test 19: protección contra duplicación por numero_expediente ─────────

    #[Test]
    public function no_permite_duplicar_numero_expediente(): void
    {
        $admin = $this->admin();
        $data = $this->fiscalizacionData(['numero_expediente' => 'EXP-UNICO-001']);

        // Primera creación — debe pasar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(201);

        // Segunda creación con el mismo expediente — debe fallar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($data, [
                 'establecimiento_id' => $this->crearEstablecimiento()->id,
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['numero_expediente']);

        // Solo debe existir uno en la BD
        $this->assertCount(1, Fiscalizacion::where('numero_expediente', 'EXP-UNICO-001')->get());
    }

    // ── Test 20: datos desnormalizados del establecimiento se toman del real ────

    #[Test]
    public function datos_desnormalizados_se_toman_del_establecimiento_real(): void
    {
        $admin = $this->admin();
        $establecimiento = $this->crearEstablecimiento();

        $data = [
            'establecimiento_id' => $establecimiento->id,
            'numero_expediente' => 'EXP-DESNORMAL-01',
            'fecha_diligencia' => '2024-01-20',
            'hora_apertura' => '10:00',
            'estado' => 'BORRADOR',
        ];

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $fisc = Fiscalizacion::where('numero_expediente', 'EXP-DESNORMAL-01')->first();

        $this->assertEquals($establecimiento->razon_social, $fisc->agente_fiscalizado);
        $this->assertEquals($establecimiento->codigo_osinergmin, $fisc->codigo_osinergmin);
        $this->assertEquals($establecimiento->direccion, $fisc->direccion);
        $this->assertEquals($establecimiento->ruc_dni, $fisc->ruc_dni);
    }

    // ── Bonus: no permite asignar CONSULTA como fiscalizador ─────────────────

    #[Test]
    public function no_permite_asignar_consulta_como_fiscalizador(): void
    {
        $admin = $this->admin();
        $consulta = $this->consulta();

        $data = $this->fiscalizacionData(['user_id' => $consulta->id]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['user_id']);
    }

    // ── Bonus: validación de transición de estado ────────────────────────────

    #[Test]
    public function permite_transicion_valida_de_estado(): void
    {
        $admin = $this->admin();
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        // Transición válida: BORRADOR → EN_PROCESO
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($fisc->id), [
                             'estado' => 'EN_PROCESO',
                             'user_id' => $this->fiscalizador()->id,
                             'establecimiento_id' => $fisc->establecimiento_id,
                             'fecha_diligencia' => $fisc->fecha_diligencia,
                             'hora_apertura' => $fisc->hora_apertura,
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.fiscalizacion.estado', 'EN_PROCESO');
    }
}
