<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verificación mínima de infraestructura Sanctum.
 * FASE 6.2 — No testea endpoints PRICE, solo la base de autenticación.
 */
class SanctumInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function personal_access_tokens_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('personal_access_tokens'),
            'La tabla personal_access_tokens debe existir en price_api_test'
        );
    }

    #[Test]
    public function users_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('users'),
            'La tabla users debe existir en price_api_test'
        );
    }

    #[Test]
    public function user_model_uses_has_api_tokens(): void
    {
        $this->assertContains(
            HasApiTokens::class,
            class_uses_recursive(User::class),
            'El modelo User debe usar el trait HasApiTokens'
        );
    }

    #[Test]
    public function user_can_create_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token');

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id'   => $user->id,
            'name'           => 'test-token',
        ]);
    }
}
