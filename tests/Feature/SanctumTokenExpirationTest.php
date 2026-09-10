<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de expiración de tokens Sanctum — FASE SEGURIDAD.
 *
 * config/sanctum.php lee 'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null).
 * En los tests la variable se simula con config([...]) para no depender del
 * .env local, y el paso del tiempo se simula con travel() (determinista,
 * sin esperar el reloj real). El tearDown restablece Carbon::setTestNow()
 * para no contaminar el resto de la suite.
 *
 * BD: price_api_test (phpunit.xml)
 */
class SanctumTokenExpirationTest extends TestCase
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

    private function login(User $user): string
    {
        $response = $this->postJson('/api/login', [
            'dni'      => $user->dni,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        return $response->json('data.token');
    }

    protected function tearDown(): void
    {
        if (Carbon::hasTestNow()) {
            $this->travelBack();
        }
        parent::tearDown();
    }

    // ── Test 1: la configuración se obtiene desde SANCTUM_TOKEN_EXPIRATION ──

    #[Test]
    public function la_configuracion_se_lee_desde_sanctum_token_expiration(): void
    {
        config(['sanctum.expiration' => 1440]);

        $this->assertSame(1440, config('sanctum.expiration'));

        // Sin la variable, el default es null (tokens sin expiración)
        config(['sanctum.expiration' => null]);
        $this->assertNull(config('sanctum.expiration'));
    }

    // ── Test 2: un token válido permite acceder con expiración activa ───────

    #[Test]
    public function token_valido_accede_con_expiracion_configurada(): void
    {
        config(['sanctum.expiration' => 1440]);
        $user = $this->crearUsuario();

        $token = $this->login($user);

        $this->withToken($token)->getJson('/api/user')
             ->assertStatus(200)
             ->assertJsonPath('data.user.dni', $user->dni);
    }

    // ── Test 3: la expiración no impide crear tokens ─────────────────────────

    #[Test]
    public function la_expiracion_no_impide_la_creacion_de_tokens(): void
    {
        config(['sanctum.expiration' => 1440]);
        $user = $this->crearUsuario();

        $token = $this->login($user);

        $this->assertNotEmpty($token);
        $this->assertSame(1, $user->tokens()->count());
    }

    // ── Test 4: un token expirado es rechazado con HTTP 401 ─────────────────

    #[Test]
    public function token_expirado_devuelve_401(): void
    {
        config(['sanctum.expiration' => 1440]);
        $user = $this->crearUsuario();

        $token = $this->login($user);

        // Dentro de las 24 horas sigue funcionando
        $this->withToken($token)->getJson('/api/user')->assertStatus(200);

        // Pasadas 1441 minutos, Sanctum rechaza el token
        $this->travel(1441)->minutes();

        // RequestGuard cachea al usuario resuelto y AuthManager cachea la
        // guardia por instancia de app; se descarta la guardia para que esta
        // petición reevalúe el token (expirado) en lugar de reutilizar al
        // usuario ya autenticado de la petición anterior.
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertStatus(401);
    }

    // ── Test 5: sin expiración configurada el token no caduca ───────────────

    #[Test]
    public function sin_expiracion_configurada_el_token_no_caduca(): void
    {
        config(['sanctum.expiration' => null]); // comportamiento por defecto actual
        $user = $this->crearUsuario();

        $token = $this->login($user);

        $this->travel(1441)->minutes();

        $this->withToken($token)->getJson('/api/user')->assertStatus(200);
    }

    // ── Test 6: el logout no se rompe con expiración configurada ─────────────

    #[Test]
    public function logout_funciona_con_expiracion_configurada(): void
    {
        config(['sanctum.expiration' => 1440]);
        $user = $this->crearUsuario();

        $token = $this->login($user);

        $this->withToken($token)->postJson('/api/logout')->assertStatus(200);

        // El token quedó revocado tras el logout. Se descarta la guardia
        // resuelta (RequestGuard cachea al usuario) para que esta petición
        // reevalúe el token revocado de verdad.
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertStatus(401);
    }
}
