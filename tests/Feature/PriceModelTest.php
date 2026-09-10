<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\FiscalizacionIncumplimiento;
use App\Models\HechoVerificado;
use App\Models\IncumplimientoCatalogo;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\User;
use App\Models\Verificacion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de modelo de datos PRICE — FASE 6.3
 * Verifica la integridad del esquema, relaciones Eloquent y constraints.
 * Usa price_api_test (configurado en phpunit.xml).
 */
class PriceModelTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function crearFiscalizacion(array $attrs = []): Fiscalizacion
    {
        $establecimiento = Establecimiento::factory()->create();
        $user            = User::factory()->create();

        return Fiscalizacion::create(array_merge([
            'establecimiento_id'     => $establecimiento->id,
            'user_id'                => $user->id,
            'numero_expediente'      => 'EXP-TEST-001',
            'agente_fiscalizado'     => $establecimiento->razon_social,
            'codigo_osinergmin'      => $establecimiento->codigo_osinergmin,
            'registro_hidrocarburos' => $establecimiento->registro_hidrocarburos,
            'fecha_diligencia'       => '2026-09-09',
            'hora_apertura'          => '09:00',
            'hora_cierre'            => '12:00',
            'direccion'              => $establecimiento->direccion,
            'distrito'               => $establecimiento->distrito,
            'provincia'              => $establecimiento->provincia,
            'departamento'           => $establecimiento->departamento,
            'ruc_dni'                => $establecimiento->ruc_dni,
            'telefono_fax'           => $establecimiento->telefono,
            'estado'                 => Fiscalizacion::ESTADO_BORRADOR,
        ], $attrs));
    }

    private function crearProducto(int $id, string $nombre): Producto
    {
        return Producto::create([
            'id'        => $id,
            'nombre'    => $nombre,
            'categoria' => 'Líquidos',
            'unidad'    => 'Galón',
            'activo'    => true,
        ]);
    }

    private function crearIncumplimiento(string $codigo): IncumplimientoCatalogo
    {
        return IncumplimientoCatalogo::create([
            'codigo'      => $codigo,
            'descripcion' => "Descripción de prueba para $codigo",
            'base_legal'  => 'Art. de prueba',
            'activo'      => true,
        ]);
    }

    // ── Test 1: Se puede crear un establecimiento ────────────────────────────

    #[Test]
    public function se_puede_crear_un_establecimiento(): void
    {
        $establecimiento = Establecimiento::factory()->create([
            'razon_social'      => 'Grifo San Martín S.A.C.',
            'codigo_osinergmin' => 'DH12345',
            'ruc_dni'           => '20123456789',
        ]);

        $this->assertDatabaseHas('establecimientos', [
            'razon_social'      => 'Grifo San Martín S.A.C.',
            'codigo_osinergmin' => 'DH12345',
            'ruc_dni'           => '20123456789',
        ]);
        $this->assertTrue($establecimiento->activo);
    }

    // ── Test 2: Se puede crear una fiscalización con usuario y establecimiento

    #[Test]
    public function se_puede_crear_una_fiscalizacion_con_usuario_y_establecimiento(): void
    {
        $fiscalizacion = $this->crearFiscalizacion();

        $this->assertDatabaseHas('fiscalizaciones', [
            'id'               => $fiscalizacion->id,
            'numero_expediente' => 'EXP-TEST-001',
            'estado'           => 'BORRADOR',
        ]);

        // Relaciones Eloquent
        $this->assertInstanceOf(Establecimiento::class, $fiscalizacion->establecimiento);
        $this->assertInstanceOf(User::class, $fiscalizacion->user);
        $this->assertEquals($fiscalizacion->establecimiento_id, $fiscalizacion->establecimiento->id);
    }

    // ── Test 3: Se pueden registrar productos y precios ──────────────────────

    #[Test]
    public function se_pueden_registrar_productos_y_precios(): void
    {
        $fiscalizacion = $this->crearFiscalizacion();
        $producto      = $this->crearProducto(1, 'Diesel B5 / B5 S-50');

        $precio = Precio::create([
            'fiscalizacion_id' => $fiscalizacion->id,
            'producto_id'      => $producto->id,
            'precio_price'     => '15.5000',
            'precio_publicado' => '15.6000',
            'precio_surtidor'  => '15.5500',
            'tiene_descuento'  => false,
        ]);

        $this->assertDatabaseHas('precios', [
            'fiscalizacion_id' => $fiscalizacion->id,
            'producto_id'      => $producto->id,
        ]);

        // Relaciones
        $this->assertEquals($producto->id, $precio->producto->id);
        $this->assertEquals($fiscalizacion->id, $precio->fiscalizacion->id);
        $this->assertCount(1, $fiscalizacion->precios);
    }

    // ── Test 4: No se permiten precios negativos ─────────────────────────────

    #[Test]
    public function no_se_permiten_precios_negativos(): void
    {
        $this->expectException(QueryException::class);

        $fiscalizacion = $this->crearFiscalizacion();
        $producto      = $this->crearProducto(2, 'Gasohol 84');

        Precio::create([
            'fiscalizacion_id' => $fiscalizacion->id,
            'producto_id'      => $producto->id,
            'precio_price'     => '-5.0000',   // valor negativo → debe fallar en BD
            'tiene_descuento'  => false,
        ]);
    }

    // ── Test 5: Se puede registrar una verificación ──────────────────────────

    #[Test]
    public function se_puede_registrar_una_verificacion(): void
    {
        $fiscalizacion = $this->crearFiscalizacion();

        $verificacion = Verificacion::create([
            'fiscalizacion_id'           => $fiscalizacion->id,
            'telefono_publicado'          => '01-2345678',
            'telefono_actualizado_price'  => '01-2345678',
            'horario_publicado'           => 'Lun-Dom 6:00-22:00',
            'observaciones'              => 'Sin observaciones.',
        ]);

        $this->assertDatabaseHas('verificaciones', [
            'fiscalizacion_id' => $fiscalizacion->id,
        ]);

        // Relación hasOne desde fiscalización
        $this->assertInstanceOf(Verificacion::class, $fiscalizacion->verificacion);
        $this->assertEquals($verificacion->id, $fiscalizacion->verificacion->id);
    }

    // ── Test 6: Se puede registrar un incumplimiento ─────────────────────────

    #[Test]
    public function se_puede_registrar_un_incumplimiento(): void
    {
        $fiscalizacion = $this->crearFiscalizacion();
        $incumplimiento = $this->crearIncumplimiento('I-01-T');

        $pivote = FiscalizacionIncumplimiento::create([
            'fiscalizacion_id'          => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $incumplimiento->id,
            'seleccionado'              => true,
            'observacion'               => 'Precios no actualizados.',
        ]);

        $this->assertDatabaseHas('fiscalizacion_incumplimientos', [
            'fiscalizacion_id'           => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $incumplimiento->id,
            'seleccionado'               => true,
        ]);

        // Relación many-to-many vía incumplimientos()
        $this->assertCount(1, $fiscalizacion->incumplimientos);
        $this->assertEquals($incumplimiento->id, $fiscalizacion->incumplimientos->first()->id);
    }

    // ── Test 7: Se pueden registrar hechos verificados ───────────────────────

    #[Test]
    public function se_pueden_registrar_hechos_verificados(): void
    {
        $user          = User::factory()->create();
        $fiscalizacion = $this->crearFiscalizacion(['user_id' => $user->id]);
        $incumplimiento = $this->crearIncumplimiento('I-02-T');

        $pivote = FiscalizacionIncumplimiento::create([
            'fiscalizacion_id'           => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $incumplimiento->id,
            'seleccionado'               => true,
        ]);

        $hecho = HechoVerificado::create([
            'fiscalizacion_id'               => $fiscalizacion->id,
            'fiscalizacion_incumplimiento_id' => $pivote->id,
            'user_id'                        => $user->id,
            'descripcion'                    => 'Se constató ausencia de lista de precios visible.',
            'fecha_registro'                 => '2026-09-09',
        ]);

        $this->assertDatabaseHas('hechos_verificados', [
            'fiscalizacion_id'               => $fiscalizacion->id,
            'fiscalizacion_incumplimiento_id' => $pivote->id,
            'descripcion'                    => 'Se constató ausencia de lista de precios visible.',
        ]);

        // Relación desde fiscalización
        $this->assertCount(1, $fiscalizacion->hechosVerificados);

        // Relación desde el pivote
        $this->assertCount(1, $pivote->hechosVerificados);
    }

    // ── Test 8: Las relaciones Eloquent funcionan ────────────────────────────

    #[Test]
    public function las_relaciones_eloquent_funcionan_correctamente(): void
    {
        $fiscalizacion  = $this->crearFiscalizacion();
        $producto       = $this->crearProducto(3, 'Gasohol Regular');
        $incumplimiento = $this->crearIncumplimiento('I-03-T');

        // precios
        Precio::create([
            'fiscalizacion_id' => $fiscalizacion->id,
            'producto_id'      => $producto->id,
            'precio_price'     => '14.2000',
            'tiene_descuento'  => false,
        ]);

        // incumplimiento
        FiscalizacionIncumplimiento::create([
            'fiscalizacion_id'           => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $incumplimiento->id,
            'seleccionado'               => true,
        ]);

        $f = Fiscalizacion::with([
            'establecimiento',
            'user',
            'precios.producto',
            'incumplimientos',
        ])->find($fiscalizacion->id);

        $this->assertNotNull($f->establecimiento);
        $this->assertNotNull($f->user);
        $this->assertCount(1, $f->precios);
        $this->assertEquals('Gasohol Regular', $f->precios->first()->producto->nombre);
        $this->assertCount(1, $f->incumplimientos);
        $this->assertEquals('I-03-T', $f->incumplimientos->first()->codigo);

        // Relación inversa: Establecimiento → Fiscalizaciones
        $this->assertCount(1, $f->establecimiento->fiscalizaciones);
    }

    // ── Test 9: No se puede crear fiscalización con referencias inexistentes ─

    #[Test]
    public function no_se_puede_crear_fiscalizacion_con_referencias_inexistentes(): void
    {
        $this->expectException(QueryException::class);

        Fiscalizacion::create([
            'establecimiento_id' => 99999,   // no existe
            'user_id'            => 99999,   // no existe
            'fecha_diligencia'   => '2026-09-09',
            'hora_apertura'      => '09:00',
            'estado'             => Fiscalizacion::ESTADO_BORRADOR,
        ]);
    }

    // ── Test 10: Los estados de fiscalización funcionan correctamente ─────────

    #[Test]
    public function los_estados_de_fiscalizacion_funcionan_correctamente(): void
    {
        $fiscalizacion = $this->crearFiscalizacion([
            'estado' => Fiscalizacion::ESTADO_BORRADOR,
        ]);
        $this->assertEquals('BORRADOR', $fiscalizacion->estado);

        // Transición a EN_PROCESO
        $fiscalizacion->update(['estado' => Fiscalizacion::ESTADO_EN_PROCESO]);
        $this->assertEquals('EN_PROCESO', $fiscalizacion->fresh()->estado);

        // Transición a FINALIZADA
        $fiscalizacion->update(['estado' => Fiscalizacion::ESTADO_FINALIZADA]);
        $this->assertEquals('FINALIZADA', $fiscalizacion->fresh()->estado);

        // Transición a ACTA_GENERADA
        $fiscalizacion->update(['estado' => Fiscalizacion::ESTADO_ACTA_GENERADA]);
        $this->assertEquals('ACTA_GENERADA', $fiscalizacion->fresh()->estado);

        // Estado inválido debe lanzar excepción (ENUM MySQL)
        $this->expectException(QueryException::class);
        $fiscalizacion->update(['estado' => 'ESTADO_INVALIDO']);
    }

    // ── Bonus: Cascade delete funciona correctamente ─────────────────────────

    #[Test]
    public function al_borrar_fiscalizacion_se_eliminan_en_cascada_sus_hijos(): void
    {
        $fiscalizacion  = $this->crearFiscalizacion();
        $producto       = $this->crearProducto(4, 'GLP Automotor');
        $incumplimiento = $this->crearIncumplimiento('I-04-T');

        Precio::create([
            'fiscalizacion_id' => $fiscalizacion->id,
            'producto_id'      => $producto->id,
            'precio_price'     => '2.5000',
            'tiene_descuento'  => false,
        ]);

        $pivote = FiscalizacionIncumplimiento::create([
            'fiscalizacion_id'           => $fiscalizacion->id,
            'incumplimiento_catalogo_id' => $incumplimiento->id,
            'seleccionado'               => true,
        ]);

        HechoVerificado::create([
            'fiscalizacion_id'               => $fiscalizacion->id,
            'fiscalizacion_incumplimiento_id' => $pivote->id,
            'descripcion'                    => 'Hecho de prueba',
            'fecha_registro'                 => '2026-09-09',
        ]);

        $fiscalizacionId = $fiscalizacion->id;
        $pivoteId        = $pivote->id;

        // Borrar la fiscalización
        $fiscalizacion->delete();

        // Todos los hijos deben haberse eliminado en cascada
        $this->assertDatabaseMissing('fiscalizaciones', ['id' => $fiscalizacionId]);
        $this->assertDatabaseMissing('precios', ['fiscalizacion_id' => $fiscalizacionId]);
        $this->assertDatabaseMissing('fiscalizacion_incumplimientos', ['fiscalizacion_id' => $fiscalizacionId]);
        $this->assertDatabaseMissing('hechos_verificados', ['fiscalizacion_incumplimiento_id' => $pivoteId]);
    }
}
