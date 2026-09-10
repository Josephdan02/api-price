<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\User;
use App\Models\Verificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Verificaciones — FASE 6.4.4.2
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre casos de autorización, validaciones y reglas de negocio.
 */
class VerificacionTest extends TestCase
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

    private function verificacionData(array $override = []): array
    {
        $fiscalizacion = $this->crearFiscalizacion();

        return array_merge([
            'fiscalizacion_id' => $fiscalizacion->id,
            'telefono_publicado' => '01-2345678',
            'telefono_actualizado_price' => '01-8765432',
            'horario_publicado' => 'Lun-Sab 08:00-20:00',
            'observaciones' => 'Verificación completada',
        ], $override);
    }

    private function crearVerificacion(array $override = []): Verificacion
    {
        $fiscalizacion = $this->crearFiscalizacion();

        return Verificacion::factory()->create(array_merge([
            'fiscalizacion_id' => $fiscalizacion->id,
            'telefono_publicado' => '01-1111111',
            'telefono_actualizado_price' => '01-2222222',
            'horario_publicado' => 'Lun-Dom 07:00-21:00',
        ], $override));
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/verificaciones/{$id}" : '/api/verificaciones';
    }

    // ── Test 1: ADMIN puede listar verificaciones ───────────────────────────

    #[Test]
    public function admin_puede_listar_verificaciones(): void
    {
        $this->crearVerificacion();
        $this->crearVerificacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['data', 'current_page', 'total'],
                 ])
                 ->assertJson(['success' => true]);
    }

    // ── Test 2: FISCALIZADOR puede listar verificaciones ──────────────────────

    #[Test]
    public function fiscalizador_puede_listar_verificaciones(): void
    {
        $this->crearVerificacion();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 3: CONSULTA puede listar verificaciones ────────────────────────

    #[Test]
    public function consulta_puede_listar_verificaciones(): void
    {
        $this->crearVerificacion();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 4: usuario no autenticado recibe 401 ────────────────────────────

    #[Test]
    public function usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson($this->url())->assertStatus(401);
        $this->postJson($this->url(), $this->verificacionData())->assertStatus(401);

        $verificacion = $this->crearVerificacion();
        $this->getJson($this->url($verificacion->id))->assertStatus(401);
        $this->putJson($this->url($verificacion->id), [])->assertStatus(401);
    }

    // ── Test 5: puede consultar verificación existente ────────────────────────

    #[Test]
    public function puede_consultar_verificacion_existente(): void
    {
        $verificacion = $this->crearVerificacion(['telefono_publicado' => '01-9999999']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($verificacion->id));

        $response->assertStatus(200)
                 ->assertJsonPath('data.verificacion.id', $verificacion->id)
                 ->assertJsonPath('data.verificacion.telefono_publicado', '01-9999999');
    }

    // ── Test 6: devuelve 404 para verificación inexistente ─────────────────

    #[Test]
    public function devuelve_404_para_verificacion_inexistente(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->url(99999))
             ->assertStatus(404)
             ->assertJson(['success' => false]);

        $this->actingAs($admin, 'sanctum')
             ->putJson($this->url(99999), ['telefono_publicado' => '01-0000000'])
             ->assertStatus(404);
    }

    // ── Test 7: ADMIN puede crear ─────────────────────────────────────────────

    #[Test]
    public function admin_puede_crear_verificacion(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->postJson($this->url(), $this->verificacionData());

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.verificacion.telefono_publicado', '01-2345678');

        $this->assertDatabaseHas('verificaciones', ['telefono_publicado' => '01-2345678']);
    }

    // ── Test 8: FISCALIZADOR puede crear ─────────────────────────────────────

    #[Test]
    public function fiscalizador_puede_crear_verificacion(): void
    {
        $data = $this->verificacionData(['telefono_publicado' => '01-5555555']);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // ── Test 9: CONSULTA no puede crear ──────────────────────────────────────

    #[Test]
    public function consulta_no_puede_crear_verificacion(): void
    {
        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->postJson($this->url(), $this->verificacionData());

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 10: POST con fiscalización inexistente falla ──────────────────────

    #[Test]
    public function post_con_fiscalizacion_inexistente_falla(): void
    {
        $admin = $this->admin();
        $data = $this->verificacionData(['fiscalizacion_id' => 99999]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 11: no permite duplicar verificación en misma fiscalización ───────

    #[Test]
    public function no_permite_duplicar_verificacion_en_misma_fiscalizacion(): void
    {
        $admin = $this->admin();
        $data = $this->verificacionData();

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
        $this->assertCount(1, Verificacion::where('fiscalizacion_id', $data['fiscalizacion_id'])->get());
    }

    // ── Test 12: validación de longitud de campos ────────────────────────────

    #[Test]
    public function validacion_de_longitud_de_campos(): void
    {
        $admin = $this->admin();

        // telefono_publicado excede 50 caracteres
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($this->verificacionData(), [
                 'telefono_publicado' => str_repeat('1', 51),
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['telefono_publicado']);

        // horario_publicado excede 200 caracteres
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($this->verificacionData(), [
                 'horario_publicado' => str_repeat('A', 201),
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['horario_publicado']);
    }

    // ── Test 13: ADMIN puede actualizar ───────────────────────────────────────

    #[Test]
    public function admin_puede_actualizar_verificacion(): void
    {
        $verificacion = $this->crearVerificacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($verificacion->id), [
                             'telefono_publicado' => '01-8888888',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.verificacion.telefono_publicado', '01-8888888');

        $this->assertDatabaseHas('verificaciones', [
            'id' => $verificacion->id,
            'telefono_publicado' => '01-8888888',
        ]);
    }

    // ── Test 14: FISCALIZADOR puede actualizar ────────────────────────────────

    #[Test]
    public function fiscalizador_puede_actualizar_verificacion(): void
    {
        $verificacion = $this->crearVerificacion();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->putJson($this->url($verificacion->id), [
                             'horario_publicado' => 'Lun-Dom 06:00-22:00',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 15: CONSULTA no puede actualizar ────────────────────────────────

    #[Test]
    public function consulta_no_puede_actualizar_verificacion(): void
    {
        $verificacion = $this->crearVerificacion();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->putJson($this->url($verificacion->id), ['telefono_publicado' => '01-0000000']);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 16: update permite misma fiscalización (el mismo registro) ───────

    #[Test]
    public function update_permite_misma_fiscalizacion(): void
    {
        $admin = $this->admin();
        $verificacion = $this->crearVerificacion();

        // Actualizar el mismo registro sin cambiar fiscalización → debe pasar
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($verificacion->id), [
                             'telefono_publicado' => '01-7777777',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 17: eager loading carga relaciones correctamente ────────────────

    #[Test]
    public function eager_loading_carga_relaciones_correctamente(): void
    {
        $verificacion = $this->crearVerificacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($verificacion->id));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'verificacion' => [
                             'fiscalizacion',
                         ],
                     ],
                 ]);
    }

    // ── Test 18: campos opcionales pueden ser null ─────────────────────────

    #[Test]
    public function campos_opcionales_pueden_ser_null(): void
    {
        $admin = $this->admin();
        $fiscalizacion = $this->crearFiscalizacion();

        $data = [
            'fiscalizacion_id' => $fiscalizacion->id,
            'telefono_publicado' => null,
            'telefono_actualizado_price' => null,
            'horario_publicado' => null,
            'observaciones' => null,
        ];

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $verificacion = Verificacion::latest()->first();

        $this->assertNull($verificacion->telefono_publicado);
        $this->assertNull($verificacion->telefono_actualizado_price);
        $this->assertNull($verificacion->horario_publicado);
        $this->assertNull($verificacion->observaciones);
    }

    // ── Test 19: no permite cambiar fiscalización a una que ya tiene verificación ─

    #[Test]
    public function no_permite_cambiar_fiscalizacion_a_una_que_ya_tiene_verificacion(): void
    {
        $admin = $this->admin();
        
        // Crear dos fiscalizaciones con sus verificaciones
        $fiscalizacion1 = $this->crearFiscalizacion();
        $fiscalizacion2 = $this->crearFiscalizacion();
        
        $verificacion1 = Verificacion::factory()->create(['fiscalizacion_id' => $fiscalizacion1->id]);
        Verificacion::factory()->create(['fiscalizacion_id' => $fiscalizacion2->id]);

        // Intentar cambiar la fiscalización de verificacion1 a fiscalizacion2
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($verificacion1->id), [
                             'fiscalizacion_id' => $fiscalizacion2->id,
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 20: registro correctamente relacionado con fiscalización ────────

    #[Test]
    public function registro_correctamente_relacionado_con_fiscalizacion(): void
    {
        $admin = $this->admin();
        $fiscalizacion = $this->crearFiscalizacion();

        $data = $this->verificacionData(['fiscalizacion_id' => $fiscalizacion->id]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $verificacion = Verificacion::latest()->first();

        $this->assertEquals($fiscalizacion->id, $verificacion->fiscalizacion_id);
        $this->assertEquals($fiscalizacion->numero_expediente, $verificacion->fiscalizacion->numero_expediente);
    }
}
