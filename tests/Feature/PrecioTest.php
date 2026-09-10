<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Precios — FASE 6.4.4.1
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre casos de autorización, validaciones y reglas de negocio.
 */
class PrecioTest extends TestCase
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

    private function crearProducto(): Producto
    {
        return Producto::factory()->create([
            'nombre' => 'Gasolina 84',
            'categoria' => 'Líquidos',
            'unidad' => 'galón',
            'activo' => true,
        ]);
    }

    private function precioData(array $override = []): array
    {
        $fiscalizacion = $this->crearFiscalizacion();
        $producto = $this->crearProducto();

        return array_merge([
            'fiscalizacion_id' => $fiscalizacion->id,
            'producto_id' => $producto->id,
            'precio_price' => 15.50,
            'precio_publicado' => 15.80,
            'precio_surtidor' => 15.60,
            'tiene_descuento' => false,
        ], $override);
    }

    private function crearPrecio(array $override = []): Precio
    {
        return Precio::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/precios/{$id}" : '/api/precios';
    }

    // ── Test 1: ADMIN puede listar precios ───────────────────────────────────

    #[Test]
    public function admin_puede_listar_precios(): void
    {
        $this->crearPrecio();
        $this->crearPrecio();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['data', 'current_page', 'total'],
                 ])
                 ->assertJson(['success' => true]);
    }

    // ── Test 2: FISCALIZADOR puede listar precios ──────────────────────────

    #[Test]
    public function fiscalizador_puede_listar_precios(): void
    {
        $this->crearPrecio();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 3: CONSULTA puede listar precios ───────────────────────────────

    #[Test]
    public function consulta_puede_listar_precios(): void
    {
        $this->crearPrecio();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->getJson($this->url());

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 4: usuario no autenticado recibe 401 ────────────────────────────

    #[Test]
    public function usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson($this->url())->assertStatus(401);
        $this->postJson($this->url(), $this->precioData())->assertStatus(401);

        $precio = $this->crearPrecio();
        $this->getJson($this->url($precio->id))->assertStatus(401);
        $this->putJson($this->url($precio->id), [])->assertStatus(401);
    }

    // ── Test 5: puede consultar precio existente ────────────────────────────

    #[Test]
    public function puede_consultar_precio_existente(): void
    {
        $precio = $this->crearPrecio(['precio_price' => 18.50]);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($precio->id));

        $response->assertStatus(200)
                 ->assertJsonPath('data.precio.id', $precio->id)
                 ->assertJsonPath('data.precio.precio_price', '18.5000');
    }

    // ── Test 6: devuelve 404 para precio inexistente ─────────────────────────

    #[Test]
    public function devuelve_404_para_precio_inexistente(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->url(99999))
             ->assertStatus(404)
             ->assertJson(['success' => false]);

        $this->actingAs($admin, 'sanctum')
             ->putJson($this->url(99999), ['precio_price' => 20.00])
             ->assertStatus(404);
    }

    // ── Test 7: ADMIN puede crear ─────────────────────────────────────────────

    #[Test]
    public function admin_puede_crear_precio(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->postJson($this->url(), $this->precioData());

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.precio.precio_price', '15.5000');

        $this->assertDatabaseHas('precios', ['precio_price' => 15.50]);
    }

    // ── Test 8: FISCALIZADOR puede crear ─────────────────────────────────────

    #[Test]
    public function fiscalizador_puede_crear_precio(): void
    {
        $data = $this->precioData(['precio_price' => 16.00]);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // ── Test 9: CONSULTA no puede crear ──────────────────────────────────────

    #[Test]
    public function consulta_no_puede_crear_precio(): void
    {
        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->postJson($this->url(), $this->precioData());

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 10: POST con fiscalización inexistente falla ──────────────────────

    #[Test]
    public function post_con_fiscalizacion_inexistente_falla(): void
    {
        $admin = $this->admin();
        $data = $this->precioData(['fiscalizacion_id' => 99999]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    // ── Test 11: POST con producto inexistente falla ─────────────────────────

    #[Test]
    public function post_con_producto_inexistente_falla(): void
    {
        $admin = $this->admin();
        $data = $this->precioData(['producto_id' => 99999]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['producto_id']);
    }

    // ── Test 12: no permite precio negativo ──────────────────────────────────

    #[Test]
    public function no_permite_precio_negativo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($this->precioData(), [
                 'precio_price' => -5.00,
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['precio_price']);
    }

    // ── Test 13: ADMIN puede actualizar ───────────────────────────────────────

    #[Test]
    public function admin_puede_actualizar_precio(): void
    {
        $precio = $this->crearPrecio(['precio_price' => 15.00]);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($precio->id), [
                             'precio_price' => 18.50,
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.precio.precio_price', '18.5000');

        $this->assertDatabaseHas('precios', [
            'id' => $precio->id,
            'precio_price' => 18.50,
        ]);
    }

    // ── Test 14: FISCALIZADOR puede actualizar ────────────────────────────────

    #[Test]
    public function fiscalizador_puede_actualizar_precio(): void
    {
        $precio = $this->crearPrecio();

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->putJson($this->url($precio->id), [
                             'precio_publicado' => 20.00,
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 15: CONSULTA no puede actualizar ────────────────────────────────

    #[Test]
    public function consulta_no_puede_actualizar_precio(): void
    {
        $precio = $this->crearPrecio();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->putJson($this->url($precio->id), ['precio_price' => 20.00]);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // ── Test 16: no permite duplicar producto en misma fiscalización ─────────

    #[Test]
    public function no_permite_duplicar_producto_en_misma_fiscalizacion(): void
    {
        $admin = $this->admin();
        $data = $this->precioData();

        // Primera creación — debe pasar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(201);

        // Segunda creación con misma fiscalización y producto — debe fallar
        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), $data)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['producto_id']);

        // Solo debe existir uno en la BD
        $this->assertCount(1, Precio::where('fiscalizacion_id', $data['fiscalizacion_id'])
            ->where('producto_id', $data['producto_id'])->get());
    }

    // ── Test 17: requiere precio_descuento cuando tiene_descuento es true ─────

    #[Test]
    public function requiere_precio_descuento_cuando_tiene_descuento_es_true(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->postJson($this->url(), array_merge($this->precioData(), [
                 'tiene_descuento' => true,
                 'precio_descuento' => null,
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['precio_descuento']);
    }

    // ── Test 18: update permite mismo producto en misma fiscalización (el mismo) ─

    #[Test]
    public function update_permite_mismo_producto_en_misma_fiscalizacion(): void
    {
        $admin = $this->admin();
        $precio = $this->crearPrecio();

        // Actualizar el mismo registro sin cambiar fiscalización ni producto → debe pasar
        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson($this->url($precio->id), [
                             'precio_price' => 20.00,
                         ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── Test 19: eager loading carga relaciones correctamente ────────────────

    #[Test]
    public function eager_loading_carga_relaciones_correctamente(): void
    {
        $precio = $this->crearPrecio();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->getJson($this->url($precio->id));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'precio' => [
                             'fiscalizacion',
                             'producto',
                         ],
                     ],
                 ]);
    }

    // ── Test 20: datos numéricos se guardan correctamente ────────────────────

    #[Test]
    public function datos_numericos_se_guardan_correctamente(): void
    {
        $admin = $this->admin();
        $data = $this->precioData([
            'precio_price' => 15.1234,
            'precio_publicado' => 15.5678,
            'precio_surtidor' => 15.3456,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson($this->url(), $data);

        $response->assertStatus(201);

        $precio = Precio::latest()->first();

        $this->assertEquals(15.1234, $precio->precio_price);
        $this->assertEquals(15.5678, $precio->precio_publicado);
        $this->assertEquals(15.3456, $precio->precio_surtidor);
    }
}
