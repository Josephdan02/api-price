<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Establecimientos — FASE 6.4.2
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre los 17 casos requeridos + extras.
 */
class EstablecimientoTest extends TestCase
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

    private function establecimientoData(array $override = []): array
    {
        return array_merge([
            'razon_social'           => 'Grifo Test S.A.C.',
            'codigo_osinergmin'      => 'OSI-TEST-01',
            'registro_hidrocarburos' => 'DRH-0001',
            'ruc_dni'                => '20100001234',
            'direccion'              => 'Av. Principal 123',
            'distrito'               => 'Lima',
            'provincia'              => 'Lima',
            'departamento'           => 'Lima',
            'telefono'               => '01-2345678',
        ], $override);
    }

    private function crearEstablecimiento(array $override = []): Establecimiento
    {
        return Establecimiento::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/establecimientos/{$id}" : '/api/establecimientos';
    }

    // ── Test 1: ADMIN puede listar establecimientos ───────────────────────────

    #[Test]
    public function admin_puede_listar_establecimientos(): void
    {
        $this->crearEstablecimiento();
        $this->crearEstablecimiento();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['data', 'current_page', 'total'],
                 ])
                 ->assertJson(['success' => true]);
    }

    // ── Test 2: FISCALIZADOR puede listar establecimientos ────────────────────

    #[Test]
    public function fiscalizador_puede_listar_establecimientos(): void
    {
        $this->crearEstablecimiento();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 3: CONSULTA puede listar establecimientos ────────────────────────

    #[Test]
    public function consulta_puede_listar_establecimientos(): void
    {
        $this->crearEstablecimiento();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 4: ADMIN puede consultar uno ────────────────────────────────────

    #[Test]
    public function admin_puede_consultar_un_establecimiento(): void
    {
        $est = $this->crearEstablecimiento(['razon_social' => 'Grifo Consulta S.A.C.']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($est->id));

        $response->assertStatus(200)
                 ->assertJsonPath('data.establecimiento.id', $est->id)
                 ->assertJsonPath('data.establecimiento.razon_social', 'Grifo Consulta S.A.C.');
    }

    // ── Test 5: ADMIN puede crear ─────────────────────────────────────────────

    #[Test]
    public function admin_puede_crear_establecimiento(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->postJson($this->url(), $this->establecimientoData());

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.establecimiento.razon_social', 'Grifo Test S.A.C.');

        $this->assertDatabaseHas('establecimientos', ['razon_social' => 'Grifo Test S.A.C.']);
    }

    // ── Test 6: FISCALIZADOR puede crear ─────────────────────────────────────

    #[Test]
    public function fiscalizador_puede_crear_establecimiento(): void
    {
        $data = $this->establecimientoData([
            'razon_social'      => 'Grifo Fisc S.A.C.',
            'codigo_osinergmin' => 'OSI-FISC-01',
        ]);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // ── Test 7: CONSULTA no puede crear ──────────────────────────────────────

    #[Test]
    public function consulta_no_puede_crear_establecimiento(): void
    {
        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->postJson($this->url(), $this->establecimientoData());

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 8: ADMIN puede actualizar ────────────────────────────────────────

    #[Test]
    public function admin_puede_actualizar_establecimiento(): void
    {
        $est = $this->crearEstablecimiento();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($est->id), [
                             'razon_social' => 'Grifo Actualizado S.A.C.',
                             'telefono'     => '01-9999999',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.establecimiento.razon_social', 'Grifo Actualizado S.A.C.');

        $this->assertDatabaseHas('establecimientos', ['razon_social' => 'Grifo Actualizado S.A.C.']);
    }

    // ── Test 9: FISCALIZADOR puede actualizar ─────────────────────────────────

    #[Test]
    public function fiscalizador_puede_actualizar_establecimiento(): void
    {
        $est = $this->crearEstablecimiento();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->putJson($this->url($est->id), [
                             'razon_social' => 'Actualizado por Fisc.',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 10: CONSULTA no puede actualizar ────────────────────────────────

    #[Test]
    public function consulta_no_puede_actualizar_establecimiento(): void
    {
        $est = $this->crearEstablecimiento();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->putJson($this->url($est->id), ['razon_social' => 'Intento']);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 11: ADMIN puede eliminar ─────────────────────────────────────────

    #[Test]
    public function admin_puede_eliminar_establecimiento(): void
    {
        $est = $this->crearEstablecimiento();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->deleteJson($this->url($est->id));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('establecimientos', ['id' => $est->id]);
    }

    // ── Test 12: FISCALIZADOR no puede eliminar ───────────────────────────────

    #[Test]
    public function fiscalizador_no_puede_eliminar_establecimiento(): void
    {
        $est = $this->crearEstablecimiento();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->deleteJson($this->url($est->id));

        $response->assertStatus(403)->assertJson(['success' => false]);
        $this->assertDatabaseHas('establecimientos', ['id' => $est->id]);
    }

    // ── Test 13: CONSULTA no puede eliminar ──────────────────────────────────

    #[Test]
    public function consulta_no_puede_eliminar_establecimiento(): void
    {
        $est = $this->crearEstablecimiento();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->deleteJson($this->url($est->id));

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 14: Usuario no autenticado recibe 401 ────────────────────────────

    #[Test]
    public function usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson($this->url())->assertStatus(401);
        $this->postJson($this->url(), $this->establecimientoData())->assertStatus(401);

        $est = $this->crearEstablecimiento();
        $this->getJson($this->url($est->id))->assertStatus(401);
        $this->putJson($this->url($est->id), [])->assertStatus(401);
        $this->deleteJson($this->url($est->id))->assertStatus(401);
    }

    // ── Test 15: ID inexistente devuelve 404 ──────────────────────────────────

    #[Test]
    public function id_inexistente_devuelve_404(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->url(99999))
             ->assertStatus(404)
             ->assertJson(['success' => false]);

        $this->actingAs($admin, 'sanctum')
             ->putJson($this->url(99999), ['razon_social' => 'X'])
             ->assertStatus(404);

        $this->actingAs($admin, 'sanctum')
             ->deleteJson($this->url(99999))
             ->assertStatus(404);
    }

    // ── Test 16: Datos inválidos devuelven 422 ────────────────────────────────

    #[Test]
    public function datos_invalidos_devuelven_422(): void
    {
        $admin = $this->admin();

        // razon_social faltante
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['razon_social']);

        // razon_social demasiado larga
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), ['razon_social' => str_repeat('X', 201)])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['razon_social']);

        // latitud fuera de rango
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), [
                 'razon_social' => 'Grifo Validacion',
                 'latitud'      => 999,
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['latitud']);
    }

    // ── Test 17: Se evita duplicar un establecimiento ─────────────────────────

    #[Test]
    public function no_se_puede_crear_establecimiento_con_codigo_osinergmin_duplicado(): void
    {
        $admin = $this->admin();
        $data  = $this->establecimientoData(['codigo_osinergmin' => 'OSI-UNICO-99']);

        // Primera creación — debe pasar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(201);

        // Segunda creación con el mismo código — debe fallar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($data, ['razon_social' => 'Otro Grifo']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['codigo_osinergmin']);

        // Solo debe existir uno en la BD
        $this->assertCount(1, Establecimiento::where('codigo_osinergmin', 'OSI-UNICO-99')->get());
    }

    // ── Bonus: Update no falla por su propio codigo_osinergmin ───────────────

    #[Test]
    public function update_no_falla_por_su_propio_codigo_osinergmin(): void
    {
        $est = $this->crearEstablecimiento(['codigo_osinergmin' => 'OSI-SELF-01']);

        // Actualizar con el mismo código del propio registro → debe pasar
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($est->id), [
                             'razon_social'      => 'Nombre Actualizado',
                             'codigo_osinergmin' => 'OSI-SELF-01',
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Bonus: Respuesta paginada tiene estructura correcta ──────────────────

    #[Test]
    public function listado_tiene_estructura_paginada(): void
    {
        Establecimiento::factory()->count(5)->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => [
                         'current_page', 'data', 'first_page_url',
                         'last_page', 'per_page', 'total',
                     ],
                 ]);

        $this->assertEquals(15, $response->json('data.per_page'));
    }

    // ── FASE 6.4.6: search por razon_social ────────────────────────────────

    #[Test]
    public function search_encuentra_por_razon_social(): void
    {
        $this->crearEstablecimiento(['razon_social' => 'Grifo Huanuco Search ABC']);
        $this->crearEstablecimiento(['razon_social' => 'Grifo Lima Otro']);

        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?search=Huanuco Search');
        $r->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(1, $r->json('data.total'));
        $this->assertStringContainsString('Huanuco', $r->json('data.data.0.razon_social'));
    }

    #[Test]
    public function search_encuentra_por_nombre_comercial(): void
    {
        $this->crearEstablecimiento(['razon_social' => 'RS Uno', 'nombre_comercial' => 'Comercial Buscado XYZ']);
        $this->crearEstablecimiento(['razon_social' => 'RS Dos']);
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?search=Buscado XYZ');
        $r->assertStatus(200);
        $this->assertEquals(1, $r->json('data.total'));
    }

    #[Test]
    public function search_encuentra_por_codigo(): void
    {
        $this->crearEstablecimiento(['codigo_osinergmin' => 'COD-SEARCH-77']);
        $this->crearEstablecimiento(['codigo_osinergmin' => 'COD-OTRO-88']);
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?search=COD-SEARCH-77');
        $r->assertStatus(200);
        $this->assertEquals(1, $r->json('data.total'));
    }

    #[Test]
    public function search_encuentra_por_ruc(): void
    {
        $this->crearEstablecimiento(['ruc_dni' => '20999888777']);
        $this->crearEstablecimiento(['ruc_dni' => '20111222333']);
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?search=20999888777');
        $r->assertStatus(200);
        $this->assertEquals(1, $r->json('data.total'));
    }

    #[Test]
    public function search_sin_coincidencias_vacio(): void
    {
        $this->crearEstablecimiento(['razon_social' => 'Grifo Existente']);
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?search=ZZZ-SIN-COINCIDENCIA');
        $r->assertStatus(200);
        $this->assertEquals(0, $r->json('data.total'));
        $this->assertCount(0, $r->json('data.data'));
    }

    #[Test]
    public function filtro_por_distrito(): void
    {
        $this->crearEstablecimiento(['distrito' => 'Huanuco']);
        $this->crearEstablecimiento(['distrito' => 'Lima']);
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?distrito=Huanuco');
        $r->assertStatus(200);
        $this->assertEquals(1, $r->json('data.total'));
    }

    #[Test]
    public function filtro_por_departamento(): void
    {
        $this->crearEstablecimiento(['departamento' => 'Huanuco']);
        $this->crearEstablecimiento(['departamento' => 'Lima']);
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?departamento=Huanuco');
        $r->assertStatus(200);
        $this->assertEquals(1, $r->json('data.total'));
    }

    #[Test]
    public function filtro_por_activo(): void
    {
        $this->crearEstablecimiento(['activo' => true]);
        $this->crearEstablecimiento(['activo' => false]);
        $r1 = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?activo=1');
        $r1->assertStatus(200);
        $this->assertEquals(1, $r1->json('data.total'));
        $r0 = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?activo=0');
        $r0->assertStatus(200);
        $this->assertEquals(1, $r0->json('data.total'));
    }

    #[Test]
    public function search_mas_filtros_combinados(): void
    {
        $this->crearEstablecimiento(['razon_social' => 'Grifo Combo ABC', 'distrito' => 'Huanuco', 'activo' => true]);
        $this->crearEstablecimiento(['razon_social' => 'Grifo Combo ABC', 'distrito' => 'Lima', 'activo' => true]);
        $r = $this->actingAs($this->admin(), 'sanctum')
            ->getJson($this->url() . '?search=Combo ABC&distrito=Huanuco&activo=1');
        $r->assertStatus(200);
        $this->assertEquals(1, $r->json('data.total'));
    }

    #[Test]
    public function sin_filtros_conserva_listado(): void
    {
        $this->crearEstablecimiento();
        $this->crearEstablecimiento();
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url());
        $r->assertStatus(200);
        $this->assertEquals(2, $r->json('data.total'));
    }

    #[Test]
    public function activo_invalido_no_rompe(): void
    {
        $this->crearEstablecimiento();
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url() . '?activo=quizas')
            ->assertStatus(200)->assertJson(['success' => true]);
    }
}
