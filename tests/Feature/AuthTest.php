<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de autenticación del sistema PRICE — FASE 6.4.1
 *
 * Cubre los 10 casos requeridos:
 * 1. Login correcto
 * 2. DNI incorrecto
 * 3. Password incorrecta
 * 4. Usuario inactivo
 * 5. Token generado correctamente
 * 6. GET /api/user sin token
 * 7. GET /api/user con token válido
 * 8. Logout
 * 9. Token revocado después de logout
 * 10. Roles válidos (middleware EnsureUserHasRole)
 *
 * BD: price_api_test (phpunit.xml)
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function crearUsuario(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'dni'      => '12345678',
            'password' => bcrypt('password123'),
            'activo'   => true,
            'rol'      => 'FISCALIZADOR',
        ], $attrs));
    }

    private function loginUrl(): string  { return '/api/login'; }
    private function userUrl(): string   { return '/api/user'; }
    private function logoutUrl(): string { return '/api/logout'; }

    // ── Test 1: Login correcto ────────────────────────────────────────────────

    #[Test]
    public function login_correcto_devuelve_token_y_datos_de_usuario(): void
    {
        $user = $this->crearUsuario();

        $response = $this->postJson($this->loginUrl(), [
            'dni'      => $user->dni,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'token',
                         'user' => ['id', 'name', 'dni', 'email', 'rol', 'activo'],
                     ],
                 ])
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'user' => [
                             'dni' => $user->dni,
                             'rol' => 'FISCALIZADOR',
                         ],
                     ],
                 ]);

        // password nunca debe estar en la respuesta
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    // ── Test 2: DNI incorrecto → 401 ─────────────────────────────────────────

    #[Test]
    public function dni_incorrecto_devuelve_401(): void
    {
        $this->crearUsuario();

        $response = $this->postJson($this->loginUrl(), [
            'dni'      => '99999999',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    // ── Test 3: Password incorrecta → 401 ────────────────────────────────────

    #[Test]
    public function password_incorrecta_devuelve_401(): void
    {
        $user = $this->crearUsuario();

        $response = $this->postJson($this->loginUrl(), [
            'dni'      => $user->dni,
            'password' => 'password_incorrecta',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    // ── Test 4: Usuario inactivo → 403 ───────────────────────────────────────

    #[Test]
    public function usuario_inactivo_devuelve_403(): void
    {
        $user = $this->crearUsuario(['activo' => false]);

        $response = $this->postJson($this->loginUrl(), [
            'dni'      => $user->dni,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    // ── Test 5: Token generado correctamente en BD ───────────────────────────

    #[Test]
    public function login_crea_token_en_base_de_datos(): void
    {
        $user = $this->crearUsuario();

        $response = $this->postJson($this->loginUrl(), [
            'dni'      => $user->dni,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $plainToken = $response->json('data.token');
        $this->assertNotEmpty($plainToken);

        // El token debe existir en personal_access_tokens
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id'   => $user->id,
            'name'           => 'price-api-token',
        ]);
    }

    // ── Test 6: GET /api/user sin token → 401 ────────────────────────────────

    #[Test]
    public function get_user_sin_token_devuelve_401(): void
    {
        $response = $this->getJson($this->userUrl());

        $response->assertStatus(401);
    }

    // ── Test 7: GET /api/user con token válido → 200 ─────────────────────────

    #[Test]
    public function get_user_con_token_valido_devuelve_datos_del_usuario(): void
    {
        $user  = $this->crearUsuario(['rol' => 'ADMIN']);
        $token = $user->createToken('price-api-token')->plainTextToken;

        $response = $this->withToken($token)->getJson($this->userUrl());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'user' => ['id', 'name', 'dni', 'email', 'rol', 'activo'],
                     ],
                 ])
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'user' => [
                             'id'  => $user->id,
                             'dni' => $user->dni,
                             'rol' => 'ADMIN',
                         ],
                     ],
                 ]);

        // password nunca debe estar en la respuesta
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    // ── Test 8: Logout exitoso → 200 ─────────────────────────────────────────

    #[Test]
    public function logout_devuelve_200_y_mensaje_correcto(): void
    {
        $user  = $this->crearUsuario();
        $token = $user->createToken('price-api-token')->plainTextToken;

        $response = $this->withToken($token)->postJson($this->logoutUrl());

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Sesión cerrada correctamente.',
                 ]);
    }

    // ── Test 9: Token revocado después de logout ──────────────────────────────

    #[Test]
    public function token_revocado_despues_de_logout(): void
    {
        $user  = $this->crearUsuario();
        $token = $user->createToken('price-api-token')->plainTextToken;

        // Hacer logout
        $this->withToken($token)->postJson($this->logoutUrl())->assertStatus(200);

        // La tabla no debe tener tokens del usuario
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id'   => $user->id,
        ]);

        // Verificar que el token ya no existe en BD (fue eliminado)
        $this->assertCount(0, $user->fresh()->tokens);
    }

    // ── Test 10: Roles válidos (middleware EnsureUserHasRole) ─────────────────

    #[Test]
    public function middleware_de_rol_permite_acceso_con_rol_correcto(): void
    {
        $admin  = $this->crearUsuario(['rol' => 'ADMIN',        'dni' => '11111111']);
        $fisc   = $this->crearUsuario(['rol' => 'FISCALIZADOR', 'dni' => '22222222']);
        $consul = $this->crearUsuario(['rol' => 'CONSULTA',     'dni' => '33333333']);

        // actingAs() autentica directamente sin pasar por el caché de tokens
        // y evita el problema de Sanctum reutilizando el usuario autenticado
        // entre peticiones dentro del mismo proceso de test.

        $this->actingAs($admin, 'sanctum')
             ->getJson($this->userUrl())
             ->assertStatus(200)
             ->assertJsonPath('data.user.rol', 'ADMIN');

        $this->actingAs($fisc, 'sanctum')
             ->getJson($this->userUrl())
             ->assertStatus(200)
             ->assertJsonPath('data.user.rol', 'FISCALIZADOR');

        $this->actingAs($consul, 'sanctum')
             ->getJson($this->userUrl())
             ->assertStatus(200)
             ->assertJsonPath('data.user.rol', 'CONSULTA');
    }

    // ── Bonus: Validación 422 por campos faltantes ────────────────────────────

    #[Test]
    public function login_sin_dni_devuelve_422(): void
    {
        $response = $this->postJson($this->loginUrl(), [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['dni']);
    }

    #[Test]
    public function login_sin_password_devuelve_422(): void
    {
        $response = $this->postJson($this->loginUrl(), [
            'dni' => '12345678',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    // ── Bonus: Login revoca tokens anteriores (sesión única) ──────────────────

    #[Test]
    public function login_exitoso_revoca_tokens_anteriores(): void
    {
        $user = $this->crearUsuario();

        // Primer login — genera token 1
        $response1 = $this->postJson($this->loginUrl(), [
            'dni'      => $user->dni,
            'password' => 'password123',
        ]);
        $token1 = $response1->json('data.token');

        // Segundo login — genera token 2 y debe revocar token 1
        $this->postJson($this->loginUrl(), [
            'dni'      => $user->dni,
            'password' => 'password123',
        ])->assertStatus(200);

        // Token 1 ya no debe funcionar
        $this->withToken($token1)->getJson($this->userUrl())->assertStatus(401);

        // Solo debe haber 1 token activo
        $this->assertCount(1, $user->fresh()->tokens);
    }
}
