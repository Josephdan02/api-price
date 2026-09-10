<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\Precio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de actualización parcial (PATCH) de Fiscalizaciones — FASE 6.4.7
 * BD: price_api_test (phpunit.xml)
 *
 * Prepara el backend para la sincronización desde la futura app Android:
 * envío de JSON parcial sin reenviar todos los campos de la fiscalización.
 */
class FiscalizacionPatchTest extends TestCase
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

    private function crearFiscalizacion(array $override = []): Fiscalizacion
    {
        return Fiscalizacion::factory()->create($override);
    }

    private function url(int $id): string
    {
        return "/api/fiscalizaciones/{$id}";
    }

    // ── I. PATCH sin autenticación devuelve 401 ──────────────────────────────

    #[Test]
    public function patch_sin_autenticacion_devuelve_401(): void
    {
        $fisc = $this->crearFiscalizacion();

        $this->patchJson($this->url($fisc->id), ['estado' => 'EN_PROCESO'])
             ->assertStatus(401);
    }

    // ── J / M. CONSULTA no puede usar PATCH ──────────────────────────────────

    #[Test]
    public function consulta_no_puede_actualizar_con_patch(): void
    {
        $fisc = $this->crearFiscalizacion();

        $response = $this->actingAs($this->consulta(), 'sanctum')
                         ->patchJson($this->url($fisc->id), ['numero_expediente' => 'EXP-HACK']);

        $response->assertStatus(403)->assertJson(['success' => false]);

        $this->assertDatabaseMissing('fiscalizaciones', [
            'id'                => $fisc->id,
            'numero_expediente' => 'EXP-HACK',
        ]);
    }

    // ── K + A. ADMIN puede modificar un solo campo ───────────────────────────

    #[Test]
    public function admin_puede_actualizar_un_solo_campo(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->patchJson($this->url($fisc->id), ['numero_expediente' => 'EXP-PATCH-01']);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.fiscalizacion.numero_expediente', 'EXP-PATCH-01');

        // Los campos no enviados conservan su valor
        $this->assertDatabaseHas('fiscalizaciones', [
            'id'                => $fisc->id,
            'numero_expediente' => 'EXP-PATCH-01',
            'estado'            => 'BORRADOR',
        ]);
    }

    // ── A (caso del prompt). hora_cierre sin hora_apertura en el payload ─────

    #[Test]
    public function patch_de_hora_cierre_sin_hora_apertura_en_payload(): void
    {
        $fisc = $this->crearFiscalizacion();

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->patchJson($this->url($fisc->id), ['hora_cierre' => '16:30']);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSame('16:30', substr((string) $fisc->fresh()->hora_cierre, 0, 5));
    }

    // ── L + B. FISCALIZADOR puede modificar varios campos ────────────────────

    #[Test]
    public function fiscalizador_puede_actualizar_varios_campos(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        $response = $this->actingAs($this->fiscalizador(), 'sanctum')
                         ->patchJson($this->url($fisc->id), [
                             'numero_expediente' => 'EXP-PATCH-02',
                             'hora_cierre'       => '18:00',
                             'estado'            => 'EN_PROCESO',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.fiscalizacion.numero_expediente', 'EXP-PATCH-02')
                 ->assertJsonPath('data.fiscalizacion.estado', 'EN_PROCESO');

        $this->assertDatabaseHas('fiscalizaciones', [
            'id'                => $fisc->id,
            'numero_expediente' => 'EXP-PATCH-02',
            'estado'            => 'EN_PROCESO',
        ]);
    }

    // ── C. PATCH con estado válido ───────────────────────────────────────────

    #[Test]
    public function patch_permite_transicion_valida_de_estado(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->patchJson($this->url($fisc->id), ['estado' => 'EN_PROCESO']);

        $response->assertStatus(200)
                 ->assertJsonPath('data.fiscalizacion.estado', 'EN_PROCESO');

        $this->assertDatabaseHas('fiscalizaciones', [
            'id'     => $fisc->id,
            'estado' => 'EN_PROCESO',
        ]);
    }

    // ── D. PATCH respeta las transiciones de estado existentes ───────────────

    #[Test]
    public function patch_respeta_transiciones_encadenadas(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'EN_PROCESO'])
             ->assertStatus(200);

        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'FINALIZADA'])
             ->assertStatus(200);

        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'ACTA_GENERADA'])
             ->assertStatus(200);

        $this->assertDatabaseHas('fiscalizaciones', [
            'id'     => $fisc->id,
            'estado' => 'ACTA_GENERADA',
        ]);
    }

    // ── E. PATCH rechaza transición inválida ─────────────────────────────────

    #[Test]
    public function patch_rechaza_transicion_invalida(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);
        $admin = $this->admin();

        // BORRADOR → FINALIZADA (salto no permitido)
        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'FINALIZADA'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['estado']);

        // BORRADOR → ACTA_GENERADA (salto no permitido)
        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'ACTA_GENERADA'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['estado']);

        $this->assertDatabaseHas('fiscalizaciones', [
            'id'     => $fisc->id,
            'estado' => 'BORRADOR',
        ]);
    }

    // ── F. PATCH rechaza finalizar sin fiscalizador ──────────────────────────

    #[Test]
    public function patch_rechaza_finalizar_sin_fiscalizador(): void
    {
        $fisc = Fiscalizacion::factory()->create([
            'estado'  => 'EN_PROCESO',
            'user_id' => null,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->patchJson($this->url($fisc->id), ['estado' => 'FINALIZADA']);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseHas('fiscalizaciones', [
            'id'     => $fisc->id,
            'estado' => 'EN_PROCESO',
        ]);
    }

    // ── G. PATCH rechaza datos inválidos ─────────────────────────────────────

    #[Test]
    public function patch_rechaza_datos_invalidos(): void
    {
        $fisc = $this->crearFiscalizacion();
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['fecha_diligencia' => 'no-es-una-fecha'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fecha_diligencia']);

        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['hora_apertura' => '25:00'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['hora_apertura']);

        $this->actingAs($admin, 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'INVALIDO'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['estado']);
    }

    // ── G. PATCH con establecimiento inexistente → 422 ───────────────────────

    #[Test]
    public function patch_con_establecimiento_inexistente_falla(): void
    {
        $fisc = $this->crearFiscalizacion();

        $this->actingAs($this->admin(), 'sanctum')
             ->patchJson($this->url($fisc->id), ['establecimiento_id' => 99999])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['establecimiento_id']);
    }

    // ── G. PATCH no permite asignar un CONSULTA como fiscalizador ────────────

    #[Test]
    public function patch_rechaza_usuario_consulta_como_fiscalizador(): void
    {
        $fisc = $this->crearFiscalizacion();
        $consulta = $this->consulta();

        $this->actingAs($this->admin(), 'sanctum')
             ->patchJson($this->url($fisc->id), ['user_id' => $consulta->id])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['user_id']);
    }

    // ── H. PATCH devuelve 404 para fiscalización inexistente ────────────────

    #[Test]
    public function patch_devuelve_404_para_fiscalizacion_inexistente(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
             ->patchJson($this->url(99999), ['estado' => 'EN_PROCESO'])
             ->assertStatus(404)
             ->assertJson(['success' => false]);
    }

    // ── N. PUT existente continúa funcionando ────────────────────────────────

    #[Test]
    public function put_existente_continua_funcionando(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        $response = $this->actingAs($this->admin(), 'sanctum')
                         ->putJson($this->url($fisc->id), [
                             'estado'            => 'EN_PROCESO',
                             'numero_expediente' => 'EXP-PUT-OK',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.fiscalizacion.estado', 'EN_PROCESO');

        $this->assertDatabaseHas('fiscalizaciones', [
            'id'                => $fisc->id,
            'numero_expediente' => 'EXP-PUT-OK',
            'estado'            => 'EN_PROCESO',
        ]);
    }

    // ── O. GET /api/fiscalizaciones/{id} continúa funcionando ────────────────

    #[Test]
    public function get_show_continua_funcionando_tras_patch(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);

        $this->actingAs($this->admin(), 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'EN_PROCESO'])
             ->assertStatus(200);

        $this->actingAs($this->admin(), 'sanctum')
             ->getJson($this->url($fisc->id))
             ->assertStatus(200)
             ->assertJsonPath('data.fiscalizacion.id', $fisc->id)
             ->assertJsonPath('data.fiscalizacion.estado', 'EN_PROCESO');
    }

    // ── O/P. index continúa funcionando ──────────────────────────────────────

    #[Test]
    public function index_continua_funcionando_tras_patch(): void
    {
        $fisc = $this->crearFiscalizacion();

        $this->actingAs($this->admin(), 'sanctum')
             ->patchJson($this->url($fisc->id), ['numero_expediente' => 'EXP-IDX-01'])
             ->assertStatus(200);

        $this->actingAs($this->consulta(), 'sanctum')
             ->getJson('/api/fiscalizaciones')
             ->assertStatus(200)
             ->assertJson(['success' => true])
             ->assertJsonStructure(['data' => ['data', 'current_page', 'total']]);
    }

    // ── P. Las relaciones existentes no se rompen tras el PATCH ──────────────

    #[Test]
    public function patch_no_rompe_relaciones_existentes(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);
        Precio::factory()->create(['fiscalizacion_id' => $fisc->id]);

        $this->actingAs($this->admin(), 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'EN_PROCESO'])
             ->assertStatus(200);

        // La relación fiscalización → establecimiento sigue cargando
        $fisc->refresh()->loadMissing('establecimiento', 'precios');
        $this->assertInstanceOf(Establecimiento::class, $fisc->establecimiento);
        $this->assertCount(1, $fisc->precios);
    }

    // ── Uso esperado desde Android: PATCH parcial incremental ────────────────

    #[Test]
    public function sincronizacion_parcial_estilo_android(): void
    {
        $fisc = $this->crearFiscalizacion(['estado' => 'BORRADOR']);
        $fiscalizador = $this->fiscalizador();

        // Paso 1: Android marca la fiscalización en proceso y asigna fiscalizador
        $this->actingAs($fiscalizador, 'sanctum')
             ->patchJson($this->url($fisc->id), [
                 'estado'  => 'EN_PROCESO',
                 'user_id' => $fiscalizador->id,
             ])
             ->assertStatus(200);

        // Paso 2: Android envía solo la hora de cierre al terminar la diligencia
        $this->actingAs($fiscalizador, 'sanctum')
             ->patchJson($this->url($fisc->id), ['hora_cierre' => '17:00'])
             ->assertStatus(200);

        // Paso 3: Android finaliza
        $this->actingAs($fiscalizador, 'sanctum')
             ->patchJson($this->url($fisc->id), ['estado' => 'FINALIZADA'])
             ->assertStatus(200);

        $fisc->refresh();
        $this->assertSame('FINALIZADA', $fisc->estado);
        $this->assertSame($fiscalizador->id, $fisc->user_id);
        $this->assertSame('17:00', substr((string) $fisc->hora_cierre, 0, 5));
    }
}
