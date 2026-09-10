<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EstablecimientoFiscalizacionesTest extends TestCase
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

    private function url(int $id): string
    {
        return "/api/establecimientos/{$id}/fiscalizaciones";
    }

    #[Test]
    public function existente_devuelve_sus_fiscalizaciones(): void
    {
        $est = Establecimiento::factory()->create();
        Fiscalizacion::factory()->create(['establecimiento_id' => $est->id]);
        Fiscalizacion::factory()->create(['establecimiento_id' => $est->id]);
        Fiscalizacion::factory()->create();
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($est->id));
        $r->assertStatus(200)->assertJson(['success' => true]);
        $r->assertJsonStructure(['success', 'message', 'data' => ['data', 'current_page', 'total']]);
        $this->assertEquals(2, $r->json('data.total'));
    }

    #[Test]
    public function sin_fiscalizaciones_vacio(): void
    {
        $est = Establecimiento::factory()->create();
        $r = $this->actingAs($this->admin(), 'sanctum')->getJson($this->url($est->id));
        $r->assertStatus(200);
        $this->assertEquals(0, $r->json('data.total'));
        $this->assertCount(0, $r->json('data.data'));
    }

    #[Test]
    public function inexistente_404(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->getJson($this->url(99999))
            ->assertStatus(404)->assertJson(['success' => false]);
    }

    #[Test]
    public function consulta_y_fiscalizador_ok(): void
    {
        $est = Establecimiento::factory()->create();
        $this->actingAs($this->consulta(), 'sanctum')->getJson($this->url($est->id))->assertStatus(200);
        $this->actingAs($this->fiscalizador(), 'sanctum')->getJson($this->url($est->id))->assertStatus(200);
    }

    #[Test]
    public function sin_token_401(): void
    {
        $est = Establecimiento::factory()->create();
        $this->getJson($this->url($est->id))->assertStatus(401);
    }
}
