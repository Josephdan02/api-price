<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\FiscalizacionIncumplimiento;
use App\Models\HechoVerificado;
use App\Models\IncumplimientoCatalogo;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActaFiscalizacionPdfTest extends TestCase
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

    private function crearFiscalizacion(array $ov = []): Fiscalizacion
    {
        $e = Establecimiento::factory()->create();
        return Fiscalizacion::factory()->create(array_merge([
            'establecimiento_id' => $e->id,
            'numero_expediente' => 'EXP-2024-0001',
            'fecha_diligencia' => '2024-01-15',
            'hora_apertura' => '09:00',
            'estado' => 'BORRADOR',
        ], $ov));
    }

    private function url(int $id): string
    {
        return "/api/fiscalizaciones/{$id}/acta/pdf";
    }

    private function escenarioCompleto(): Fiscalizacion
    {
        Storage::fake('public');
        $f = $this->crearFiscalizacion();
        $p1 = Producto::firstOrCreate(['nombre' => 'Diesel B5 Test'],
            ['categoria' => 'Liquidos', 'unidad' => 'Galon', 'activo' => true]);
        $p2 = Producto::firstOrCreate(['nombre' => 'GLP Automotor Test'],
            ['categoria' => 'GLP', 'unidad' => 'Litro', 'activo' => true]);
        Precio::create(['fiscalizacion_id' => $f->id, 'producto_id' => $p1->id,
            'precio_price' => '10.5000', 'precio_publicado' => '10.8000',
            'precio_surtidor' => '10.8000', 'tiene_descuento' => false]);
        Precio::create(['fiscalizacion_id' => $f->id, 'producto_id' => $p2->id,
            'precio_price' => '5.2000', 'tiene_descuento' => false]);
        \App\Models\Verificacion::create(['fiscalizacion_id' => $f->id,
            'telefono_publicado' => '062-111222',
            'telefono_actualizado_price' => '062-333444',
            'horario_publicado' => 'Lun-Sab 08:00-20:00']);
        $cat = IncumplimientoCatalogo::first() ?? IncumplimientoCatalogo::factory()->create();
        $inc = FiscalizacionIncumplimiento::create(['fiscalizacion_id' => $f->id,
            'incumplimiento_catalogo_id' => $cat->id, 'seleccionado' => true]);
        HechoVerificado::create(['fiscalizacion_id' => $f->id,
            'fiscalizacion_incumplimiento_id' => $inc->id,
            'descripcion' => 'Hecho de prueba acta', 'fecha_registro' => '2024-01-15']);
        \App\Models\Observacion::create(['fiscalizacion_id' => $f->id,
            'otras_ocurrencias' => 'Ocurrencia acta', 'negativa_identificacion' => false,
            'negativa_suscripcion' => false, 'negativa_recepcion' => false]);
        \App\Models\Firma::create(['fiscalizacion_id' => $f->id, 'tipo_firma' => 'FISCALIZADOR',
            'nombre_completo' => 'Fiscalizador Acta', 'dni' => '11111111']);
        \App\Models\Firma::create(['fiscalizacion_id' => $f->id, 'tipo_firma' => 'AGENTE',
            'nombre_completo' => 'Agente Acta', 'relacion_agente' => 'Propietario']);
        return $f;
    }

    #[Test]
    public function sin_autenticacion_401(): void
    {
        Storage::fake('public');
        $f = $this->crearFiscalizacion();
        $this->getJson($this->url($f->id))->assertStatus(401);
    }

    #[Test]
    public function roles_lectura_200(): void
    {
        $f = $this->escenarioCompleto();
        $this->actingAs($this->consulta(), 'sanctum')->get($this->url($f->id))->assertStatus(200);
        $this->actingAs($this->fiscalizador(), 'sanctum')->get($this->url($f->id))->assertStatus(200);
        $this->actingAs($this->admin(), 'sanctum')->get($this->url($f->id))->assertStatus(200);
    }

    #[Test]
    public function fiscalizacion_inexistente_404(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin(), 'sanctum')->get($this->url(99999))->assertStatus(404);
    }

    #[Test]
    public function pdf_valido_y_expediente(): void
    {
        $f = $this->escenarioCompleto();
        $r = $this->actingAs($this->admin(), 'sanctum')->get($this->url($f->id));
        $r->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertStringContainsString('EXP-2024-0001', $r->getContent());
        $this->assertStringContainsString('FISCALIZACION', $r->getContent());
    }

    #[Test]
    public function fiscalizacion_minima_no_rompe(): void
    {
        Storage::fake('public');
        $f = $this->crearFiscalizacion(['numero_expediente' => 'EXP-MIN-001']);
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($f->id));
        $r->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
    }

    #[Test]
    public function fiscalizacion_sin_user_no_rompe(): void
    {
        Storage::fake('public');
        $f = $this->crearFiscalizacion(['numero_expediente' => 'EXP-NOUSR-001', 'user_id' => null]);
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($f->id))->assertStatus(200);
    }

    #[Test]
    public function estado_no_cambia(): void
    {
        $f = $this->escenarioCompleto();
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($f->id))->assertStatus(200);
        $this->assertEquals('BORRADOR', $f->fresh()->estado);
    }

    #[Test]
    public function consulta_no_muta_datos(): void
    {
        $f = $this->escenarioCompleto();
        $n = \App\Models\Precio::count();
        $this->actingAs($this->consulta(), 'sanctum')->getJson($this->url($f->id))->assertStatus(200);
        $this->assertEquals($n, \App\Models\Precio::count());
        $this->assertCount(1, Documento::where('fiscalizacion_id', $f->id)->get());
    }

    #[Test]
    public function genera_documento_idempotente(): void
    {
        $f = $this->escenarioCompleto();
        $this->assertDatabaseMissing('documentos', ['fiscalizacion_id' => $f->id]);
        $this->actingAs($this->admin(), 'sanctum')->get($this->url($f->id))->assertStatus(200);
        $this->assertDatabaseHas('documentos', ['fiscalizacion_id' => $f->id]);
        $d = Documento::where('fiscalizacion_id', $f->id)->first();
        $this->assertNotNull($d->nombre_archivo);
        $this->assertNotNull($d->ruta_archivo);
        $this->assertNotNull($d->fecha_generacion);
        Storage::disk('public')->assertExists($d->ruta_archivo);
        $id1 = $d->id;
        $this->actingAs($this->admin(), 'sanctum')->get($this->url($f->id))->assertStatus(200);
        $this->assertCount(1, Documento::where('fiscalizacion_id', $f->id)->get());
        $this->assertEquals($id1, Documento::where('fiscalizacion_id', $f->id)->first()->id);
    }
}
