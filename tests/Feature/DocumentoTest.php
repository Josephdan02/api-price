<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentoTest extends TestCase
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
        $e = Establecimiento::factory()->create([
            'razon_social' => 'Grifo Test S.A.C.',
            'codigo_osinergmin' => 'OSI-TEST-01',
            'direccion' => 'Av. Principal 123',
            'ruc_dni' => '20100001234',
        ]);
        return Fiscalizacion::factory()->create([
            'establecimiento_id' => $e->id,
            'numero_expediente' => 'EXP-2024-0001',
            'fecha_diligencia' => '2024-01-15',
            'hora_apertura' => '09:00',
            'estado' => 'BORRADOR',
        ]);
    }

    private function documentoData(array $override = []): array
    {
        $f = $this->crearFiscalizacion();
        return array_merge([
            'fiscalizacion_id' => $f->id,
            'nombre_archivo' => 'acta-0001.pdf',
            'ruta_archivo' => 'documentos/acta-0001.pdf',
            'fecha_generacion' => '2024-01-15 10:30:00',
            'numero_paginas' => 5,
            'estado' => 'GENERADO',
        ], $override);
    }

    private function crearDocumento(array $override = []): Documento
    {
        return Documento::factory()->create($override);
    }

    private function url(int $id = null): string
    {
        return $id ? "/api/documentos/{$id}" : '/api/documentos';
    }

    #[Test]
    public function get_index_autenticado_admin(): void
    {
        $this->crearDocumento();
        $this->crearDocumento();
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url())
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['data', 'current_page', 'total']])
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function get_index_como_fiscalizador(): void
    {
        $this->crearDocumento();
        $this->actingAs($this->fiscalizador(), 'sanctum')->getJson($this->url())
            ->assertStatus(200)->assertJson(['success' => true]);
    }

    #[Test]
    public function get_index_como_consulta(): void
    {
        $this->crearDocumento();
        $this->actingAs($this->consulta(), 'sanctum')->getJson($this->url())
            ->assertStatus(200)->assertJson(['success' => true]);
    }

    #[Test]
    public function get_index_sin_autenticacion(): void
    {
        $this->getJson($this->url())->assertStatus(401);
    }

    #[Test]
    public function get_show_existente(): void
    {
        $d = $this->crearDocumento(['nombre_archivo' => 'acta-show.pdf']);
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($d->id))
            ->assertStatus(200)
            ->assertJsonPath('data.documento.id', $d->id)
            ->assertJsonPath('data.documento.nombre_archivo', 'acta-show.pdf');
    }

    #[Test]
    public function get_show_inexistente(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url(99999))
            ->assertStatus(404)->assertJson(['success' => false]);
    }

    #[Test]
    public function post_creacion_correcta_admin(): void
    {
        $r = $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->url(), $this->documentoData());
        $r->assertStatus(201)->assertJson(['success' => true]);
        $r->assertJsonPath('data.documento.nombre_archivo', 'acta-0001.pdf');
        $this->assertDatabaseHas('documentos', ['nombre_archivo' => 'acta-0001.pdf']);
    }

    #[Test]
    public function post_creacion_correcta_fiscalizador(): void
    {
        $this->actingAs($this->fiscalizador(), 'sanctum')
            ->postJson($this->url(), $this->documentoData())
            ->assertStatus(201)->assertJson(['success' => true]);
    }

    #[Test]
    public function post_rechazado_como_consulta(): void
    {
        $this->actingAs($this->consulta(), 'sanctum')
            ->postJson($this->url(), $this->documentoData())
            ->assertStatus(403)->assertJson(['success' => false]);
    }

    #[Test]
    public function post_sin_autenticacion(): void
    {
        $this->postJson($this->url(), $this->documentoData())->assertStatus(401);
    }

    #[Test]
    public function post_fiscalizacion_id_obligatorio(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [])
            ->assertStatus(422)->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    #[Test]
    public function post_fiscalizacion_inexistente(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->url(), ['fiscalizacion_id' => 99999])
            ->assertStatus(422)->assertJsonValidationErrors(['fiscalizacion_id']);
    }
    #[Test]
    public function post_rechaza_segundo_documento(): void
    {
        $admin = $this->admin();
        $data = $this->documentoData();
        $this->actingAs($admin, 'sanctum')->postJson($this->url(), $data)->assertStatus(201);
        $this->actingAs($admin, 'sanctum')->postJson($this->url(), $data)
            ->assertStatus(422)->assertJsonValidationErrors(['fiscalizacion_id']);
        $this->assertCount(1, Documento::where('fiscalizacion_id', $data['fiscalizacion_id'])->get());
    }

    #[Test]
    public function post_campos_opcionales_null(): void
    {
        $f = $this->crearFiscalizacion();
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id, 'nombre_archivo' => null,
            'ruta_archivo' => null, 'fecha_generacion' => null,
            'numero_paginas' => null, 'estado' => null,
        ])->assertStatus(201);
        $d = Documento::latest()->first();
        $this->assertNull($d->nombre_archivo);
        $this->assertNull($d->fecha_generacion);
    }

    #[Test]
    public function post_limites_longitud(): void
    {
        $f = $this->crearFiscalizacion();
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id,
            'nombre_archivo' => str_repeat('a', 301),
            'ruta_archivo' => str_repeat('b', 501),
            'estado' => str_repeat('c', 51),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['nombre_archivo', 'ruta_archivo', 'estado']);
    }

    #[Test]
    public function post_tipos_invalidos(): void
    {
        $f = $this->crearFiscalizacion();
        $this->actingAs($this->admin(), 'sanctum')->postJson($this->url(), [
            'fiscalizacion_id' => $f->id,
            'fecha_generacion' => 'no-fecha',
            'numero_paginas' => 'no-numero',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_generacion', 'numero_paginas']);
    }

    #[Test]
    public function put_actualizacion_correcta(): void
    {
        $d = $this->crearDocumento();
        $r = $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url($d->id), ['nombre_archivo' => 'acta-upd.pdf', 'numero_paginas' => 10]);
        $r->assertStatus(200)->assertJson(['success' => true]);
        $r->assertJsonPath('data.documento.nombre_archivo', 'acta-upd.pdf');
        $this->assertDatabaseHas('documentos', ['id' => $d->id, 'nombre_archivo' => 'acta-upd.pdf']);
    }

    #[Test]
    public function put_conserva_propio_fiscalizacion_id(): void
    {
        $d = $this->crearDocumento();
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url($d->id), [
                'fiscalizacion_id' => $d->fiscalizacion_id, 'estado' => 'FIRMADO',
            ])->assertStatus(200)->assertJson(['success' => true]);
    }

    #[Test]
    public function put_fiscalizacion_inexistente(): void
    {
        $d = $this->crearDocumento();
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url($d->id), ['fiscalizacion_id' => 99999])
            ->assertStatus(422)->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    #[Test]
    public function put_documento_inexistente(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url(99999), ['estado' => 'X'])
            ->assertStatus(404);
    }

    #[Test]
    public function put_unique_contra_otro_documento(): void
    {
        $f1 = $this->crearFiscalizacion();
        $f2 = $this->crearFiscalizacion();
        $d1 = Documento::factory()->create(['fiscalizacion_id' => $f1->id]);
        Documento::factory()->create(['fiscalizacion_id' => $f2->id]);
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson($this->url($d1->id), ['fiscalizacion_id' => $f2->id])
            ->assertStatus(422)->assertJsonValidationErrors(['fiscalizacion_id']);
    }

    #[Test]
    public function put_roles(): void
    {
        $d = $this->crearDocumento();
        $this->actingAs($this->consulta(), 'sanctum')
            ->putJson($this->url($d->id), ['estado' => 'X'])->assertStatus(403);
        $this->actingAs($this->fiscalizador(), 'sanctum')
            ->putJson($this->url($d->id), ['estado' => 'Y'])->assertStatus(200);
    }

    #[Test]
    public function delete_admin_ok(): void
    {
        $d = $this->crearDocumento();
        $this->actingAs($this->admin(), 'sanctum')->deleteJson($this->url($d->id))
            ->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('documentos', ['id' => $d->id]);
    }

    #[Test]
    public function delete_roles_403(): void
    {
        $d1 = $this->crearDocumento();
        $d2 = $this->crearDocumento();
        $this->actingAs($this->fiscalizador(), 'sanctum')
            ->deleteJson($this->url($d1->id))->assertStatus(403);
        $this->actingAs($this->consulta(), 'sanctum')
            ->deleteJson($this->url($d2->id))->assertStatus(403);
        $this->assertDatabaseHas('documentos', ['id' => $d1->id]);
        $this->assertDatabaseHas('documentos', ['id' => $d2->id]);
    }

    #[Test]
    public function delete_inexistente_404(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson($this->url(99999))->assertStatus(404);
    }

    #[Test]
    public function delete_sin_autenticacion(): void
    {
        $d = $this->crearDocumento();
        $this->deleteJson($this->url($d->id))->assertStatus(401);
    }

    #[Test]
    public function eager_loading_y_cascade(): void
    {
        $d = $this->crearDocumento();
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($d->id))
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['documento' => ['id', 'fiscalizacion_id', 'fiscalizacion']]]);
        $fid = $d->fiscalizacion_id;
        Fiscalizacion::find($fid)->delete();
        $this->assertDatabaseMissing('documentos', ['id' => $d->id]);
    }
}
