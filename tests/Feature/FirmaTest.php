<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Firma;
use App\Models\Fiscalizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests CRUD de Firmas — FASE 6.4.4.6
 * BD: price_api_test (phpunit.xml)
 *
 * Cubre casos de autorización, validaciones y reglas de negocio.
 * Relación 1:N con fiscalización (una fiscalización puede tener múltiples firmas).
 */
class FirmaTest extends TestCase
{
    use RefreshDatabase;

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

    private function firmaData(array $override = []): array
    {
        $fiscalizacion = $this->crearFiscalizacion();

        return array_merge([
            'fiscalizacion_id' => $fiscalizacion->id,
            'tipo_firma' => 'FISCALIZADOR',
            'nombre_completo' => 'Juan Perez Fiscalizador',
            'dni' => '12345678',
            'relacion_agente' => null,
            'imagen_firma' => null,
            'fecha_firma' => '2024-01-15',
        ], $override);
    }

    private function crearFirma(array $override = []): Firma
    {
        return Firma::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/firmas/{$id}" : '/api/firmas';
    }

    #[Test]
    public function get_index_autenticado(): void
    {
        $this->crearFirma();
        $this->crearFirma();

        $response = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url());

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['data', 'current_page', 'total']])
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function get_index_sin_autenticacion(): void
    {
        $this->getJson($this->url())->assertStatus(401);
    }

    #[Test]
    public function get_index_como_consulta(): void
    {
        $this->crearFirma();

        $this->actingAs($this->consulta(), 'sanctum')->getJson($this->url())
            ->assertStatus(200)->assertJson(['success' => true]);
    }

    #[Test]
    public function get_show_autenticado(): void
    {
        $firma = $this->crearFirma(['nombre_completo' => 'Firma Test']);

        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($firma->id))
            ->assertStatus(200)
            ->assertJsonPath('data.firma.id', $firma->id)
            ->assertJsonPath('data.firma.nombre_completo', 'Firma Test');
    }

    #[Test]
    public function get_show_inexistente(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url(99999))
            ->assertStatus(404)->assertJson(['success' => false]);
    }

    #[Test]
    public function post_valido_como_admin(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->url(), $this->firmaData());

        $response->assertStatus(201)->assertJson(['success' => true])
            ->assertJsonPath('data.firma.tipo_firma', 'FISCALIZADOR');

        $this->assertDatabaseHas('firmas', ['tipo_firma' => 'FISCALIZADOR']);
    }

    #[Test]
    public function post_valido_como_fiscalizador(): void
    {
        $data = $this->firmaData(['tipo_firma' => 'AGENTE', 'relacion_agente' => 'Propietario']);

        $this->actingAs($this->fiscalizador(), 'sanctum')->postJson($this->url(), $data)
            ->assertStatus(201)->assertJson(['success' => true]);
    }

    #[Test]
    public function post_rechazado_como_consulta(): void
    {
        $this->actingAs($this->consulta(), 'sanctum')->postJson($this->url(), $this->firmaData())
            ->assertStatus(403)->assertJson(['success' => false]);
    }

    #[Test]
    public function post_sin_autenticacion(): void
    {
        $this->postJson($this->url(), $this->firmaData())->assertStatus(401);
    }

    #[Test]
    public function validacion_campos_obligatorios(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [])
            ->assertStatus(422)->assertJsonValidationErrors(['fiscalizacion_id', 'tipo_firma']);
    }

    #[Test]
    public function validacion_claves_foraneas(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->url(), ['fiscalizacion_id' => 99999, 'tipo_firma' => 'FISCALIZADOR'])
            ->assertStatus(422)->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    #[Test]
    public function tipo_firma_invalido_es_rechazado(): void
    {
        $fiscalizacion = $this->crearFiscalizacion();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->url(), ['fiscalizacion_id' => $fiscalizacion->id, 'tipo_firma' => 'INVALIDO'])
            ->assertStatus(422)->assertJsonValidationErrors(['tipo_firma']);
    }
    #[Test]
    public function put_valido_como_admin(): void
    {
        $firma = $this->crearFirma();
        $r = $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url($firma->id), ['nombre_completo' => 'Nombre actualizado']);
        $r->assertStatus(200)->assertJson(['success' => true]);
        $r->assertJsonPath('data.firma.nombre_completo', 'Nombre actualizado');
        $this->assertDatabaseHas('firmas', ['id' => $firma->id, 'nombre_completo' => 'Nombre actualizado']);
    }

    #[Test]
    public function put_valido_como_fiscalizador(): void
    {
        $firma = $this->crearFirma();
        $this->actingAs($this->fiscalizador(), 'sanctum')
            ->putJson($this->url($firma->id), ['dni' => '87654321'])
            ->assertStatus(200)->assertJson(['success' => true]);
    }

    #[Test]
    public function put_rechazado_como_consulta(): void
    {
        $firma = $this->crearFirma();
        $this->actingAs($this->consulta(), 'sanctum')
            ->putJson($this->url($firma->id), ['nombre_completo' => 'X'])
            ->assertStatus(403)->assertJson(['success' => false]);
    }

    #[Test]
    public function put_sobre_registro_inexistente(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url(99999), ['nombre_completo' => 'X'])
            ->assertStatus(404);
    }

    #[Test]
    public function delete_valido_como_admin(): void
    {
        $firma = $this->crearFirma();
        $this->actingAs($this->admin(), 'sanctum')->deleteJson($this->url($firma->id))
            ->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('firmas', ['id' => $firma->id]);
    }

    #[Test]
    public function delete_rechazado_como_fiscalizador(): void
    {
        $firma = $this->crearFirma();
        $this->actingAs($this->fiscalizador(), 'sanctum')->deleteJson($this->url($firma->id))
            ->assertStatus(403);
        $this->assertDatabaseHas('firmas', ['id' => $firma->id]);
    }

    #[Test]
    public function delete_rechazado_como_consulta(): void
    {
        $firma = $this->crearFirma();
        $this->actingAs($this->consulta(), 'sanctum')->deleteJson($this->url($firma->id))
            ->assertStatus(403);
        $this->assertDatabaseHas('firmas', ['id' => $firma->id]);
    }

    #[Test]
    public function delete_sin_autenticacion(): void
    {
        $firma = $this->crearFirma();
        $this->deleteJson($this->url($firma->id))->assertStatus(401);
    }

    #[Test]
    public function comprobacion_persistencia(): void
    {
        $data = $this->firmaData([
            'tipo_firma' => 'AGENTE',
            'nombre_completo' => 'Agente Persistente',
            'dni' => '11223344',
            'relacion_agente' => 'Administrador',
            'fecha_firma' => '2024-02-20',
        ]);
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), $data)->assertStatus(201);
        $this->assertDatabaseHas('firmas', [
            'fiscalizacion_id' => $data['fiscalizacion_id'],
            'tipo_firma' => 'AGENTE',
            'nombre_completo' => 'Agente Persistente',
            'dni' => '11223344',
            'relacion_agente' => 'Administrador',
        ]);
    }

    #[Test]
    public function eager_loading_carga_relaciones(): void
    {
        $firma = $this->crearFirma();
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($firma->id))
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['firma' => ['id', 'fiscalizacion_id', 'tipo_firma', 'fiscalizacion']]]);
    }

    #[Test]
    public function campos_opcionales_pueden_ser_null(): void
    {
        $f = $this->crearFiscalizacion();
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id, 'tipo_firma' => 'FISCALIZADOR',
            'nombre_completo' => null, 'dni' => null, 'relacion_agente' => null,
            'imagen_firma' => null, 'fecha_firma' => null,
        ])->assertStatus(201);
        $firma = Firma::latest()->first();
        $this->assertNull($firma->nombre_completo);
        $this->assertNull($firma->dni);
        $this->assertNull($firma->fecha_firma);
    }

    #[Test]
    public function longitudes_maximas_son_validadas(): void
    {
        $f = $this->crearFiscalizacion();
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id, 'tipo_firma' => 'FISCALIZADOR',
            'nombre_completo' => str_repeat('a', 201),
            'dni' => str_repeat('1', 21),
            'relacion_agente' => str_repeat('b', 101),
        ])->assertStatus(422)->assertJsonValidationErrors(['nombre_completo', 'dni', 'relacion_agente']);
    }

    #[Test]
    public function fecha_invalida_es_rechazada(): void
    {
        $f = $this->crearFiscalizacion();
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id, 'tipo_firma' => 'FISCALIZADOR',
            'fecha_firma' => 'no-es-una-fecha',
        ])->assertStatus(422)->assertJsonValidationErrors(['fecha_firma']);
    }

    #[Test]
    public function fiscalizacion_puede_tener_multiples_firmas(): void
    {
        $admin = $this->admin();
        $f = $this->crearFiscalizacion();
        $this->actingAs($admin, 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id, 'tipo_firma' => 'FISCALIZADOR',
            'nombre_completo' => 'Fiscalizador Uno',
        ])->assertStatus(201);
        $this->actingAs($admin, 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id, 'tipo_firma' => 'AGENTE',
            'nombre_completo' => 'Agente Dos', 'relacion_agente' => 'Propietario',
        ])->assertStatus(201);
        $this->actingAs($admin, 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id, 'tipo_firma' => 'FISCALIZADOR',
            'nombre_completo' => 'Fiscalizador Tres',
        ])->assertStatus(201);
        $this->assertCount(3, Firma::where('fiscalizacion_id', $f->id)->get());
    }

    #[Test]
    public function update_con_tipo_firma_invalido_es_rechazado(): void
    {
        $firma = $this->crearFirma();
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url($firma->id), ['tipo_firma' => 'TESTIGO'])
            ->assertStatus(422)->assertJsonValidationErrors(['tipo_firma']);
    }

    #[Test]
    public function update_permite_cambiar_de_fiscalizacion(): void
    {
        $firma = $this->crearFirma();
        $nueva = $this->crearFiscalizacion();
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url($firma->id), ['fiscalizacion_id' => $nueva->id])
            ->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('firmas', ['id' => $firma->id, 'fiscalizacion_id' => $nueva->id]);
    }
}
